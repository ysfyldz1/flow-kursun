<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Rapor sorguları. Tutarlar her zaman para birimine göre ayrı tutulur (kur çevrimi yok). */
final class ReportService
{
    public const PERIODS = ['this_month', 'last_month', 'this_quarter', 'this_year', 'last_year', 'custom'];
    public const GROUPS = ['user', 'client', 'project'];

    /** @return array{0: string, 1: string} [başlangıç, bitiş] */
    public static function period(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = new \DateTimeImmutable('today');
        return match ($preset) {
            'last_month'   => [$today->modify('first day of last month')->format('Y-m-d'), $today->modify('last day of last month')->format('Y-m-d')],
            'this_quarter' => [
                $today->setDate((int) $today->format('Y'), (intdiv((int) $today->format('n') - 1, 3) * 3) + 1, 1)->format('Y-m-d'),
                $today->format('Y-m-d'),
            ],
            'this_year'    => [$today->format('Y-01-01'), $today->format('Y-m-d')],
            'last_year'    => [($today->format('Y') - 1) . '-01-01', ($today->format('Y') - 1) . '-12-31'],
            'custom'       => [self::date($from) ?? $today->format('Y-m-01'), self::date($to) ?? $today->format('Y-m-d')],
            default        => [$today->format('Y-m-01'), $today->format('Y-m-d')],
        };
    }

    /** Zaman raporu: gruplama kişi / müvekkil / proje. */
    public static function time(string $from, string $to, string $group, array $filters = []): array
    {
        [$key, $label, $sub] = match ($group) {
            'client'  => ['c.id', 'c.name', 'c.code'],
            'project' => ['p.id', 'p.name', "CONCAT(p.number, ' · ', c.name)"],
            default   => ['u.id', 'u.name', 'u.title'],
        };
        $where = ['t.work_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        foreach (['user_id' => 't.user_id', 'client_id' => 't.client_id'] as $f => $col) {
            if (!empty($filters[$f])) {
                $where[] = "$col = ?";
                $params[] = (int) $filters[$f];
            }
        }

        $rows = DB::all(
            "SELECT $key AS id, $label AS label, $sub AS sublabel, t.currency,
                    SUM(t.minutes) AS minutes,
                    SUM(CASE WHEN t.is_billable = 1 THEN t.minutes ELSE 0 END) AS billable_minutes,
                    SUM(CASE WHEN t.is_billable = 1 THEN COALESCE(t.amount, 0) ELSE 0 END) AS amount,
                    COUNT(*) AS entries
             FROM time_entries t
             JOIN users u ON u.id = t.user_id
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = t.client_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY $key, $label, $sub, t.currency",
            $params
        );

        // Saatler para biriminden bağımsız birleşir; tutarlar para birimine göre ayrı kalır
        $merged = [];
        foreach ($rows as $r) {
            $m = &$merged[$r['id']];
            $m ??= ['id' => $r['id'], 'label' => $r['label'], 'sublabel' => $r['sublabel'], 'minutes' => 0, 'billable_minutes' => 0, 'entries' => 0, 'amounts' => []];
            $m['minutes'] += (int) $r['minutes'];
            $m['billable_minutes'] += (int) $r['billable_minutes'];
            $m['entries'] += (int) $r['entries'];
            if ((float) $r['amount'] > 0) {
                $m['amounts'][$r['currency']] = ($m['amounts'][$r['currency']] ?? 0) + (float) $r['amount'];
            }
            unset($m);
        }
        usort($merged, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
        return $merged;
    }

    /** Zaman raporu satırlarındaki tutarları para birimine göre toplar. */
    public static function timeAmounts(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            foreach ($r['amounts'] as $cur => $v) {
                $out[$cur]['amount'] = ($out[$cur]['amount'] ?? 0) + $v;
            }
        }
        ksort($out);
        return $out;
    }

    /** Faturalanmamış iş: faturalanabilir, dökülmemiş kayıtlar (proje bazında). */
    public static function unbilled(?int $clientId = null): array
    {
        return DB::all(
            "SELECT c.id AS client_id, c.name AS client_name, c.code AS client_code,
                    p.id AS project_id, p.number, p.name AS project_name, p.billing_type, t.currency,
                    COUNT(*) AS entries, SUM(t.minutes) AS minutes, SUM(COALESCE(t.amount, 0)) AS amount,
                    SUM(CASE WHEN t.amount IS NULL AND p.billing_type = 'hourly' THEN 1 ELSE 0 END) AS missing_rate,
                    MIN(t.work_date) AS oldest, MAX(t.work_date) AS newest
             FROM time_entries t
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = t.client_id
             WHERE t.statement_id IS NULL AND t.is_billable = 1 AND p.billing_type <> 'non_billable'
             " . ($clientId ? 'AND c.id = ' . (int) $clientId : '') . "
             GROUP BY c.id, c.name, c.code, p.id, p.number, p.name, p.billing_type, t.currency
             ORDER BY oldest"
        );
    }

    /** Alacak yaşlandırma: vadeye göre gecikme dilimleri, müvekkil + para birimi bazında. */
    public static function aging(): array
    {
        return DB::all(
            "SELECT c.id AS client_id, c.name AS client_name, c.code AS client_code, s.currency,
                    COUNT(*) AS statements,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) <= 0 THEN s.total - s.paid_amount ELSE 0 END) AS current_amount,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 1 AND 30 THEN s.total - s.paid_amount ELSE 0 END) AS d30,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 31 AND 60 THEN s.total - s.paid_amount ELSE 0 END) AS d60,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 61 AND 90 THEN s.total - s.paid_amount ELSE 0 END) AS d90,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) > 90 THEN s.total - s.paid_amount ELSE 0 END) AS d90plus,
                    SUM(s.total - s.paid_amount) AS total
             FROM statements s JOIN clients c ON c.id = s.client_id
             WHERE s.status = 'issued' AND s.paid_amount < s.total
             GROUP BY c.id, c.name, c.code, s.currency
             ORDER BY total DESC"
        );
    }

    /** Tahsilat: dönemde düzenlenen dökümler ve alınan ödemeler, müvekkil + para birimi bazında. */
    public static function collections(string $from, string $to): array
    {
        return DB::all(
            "SELECT c.id AS client_id, c.name AS client_name, c.code AS client_code, x.currency,
                    SUM(x.invoiced) AS invoiced, SUM(x.collected) AS collected, SUM(x.payments) AS payments
             FROM (
                SELECT s.client_id, s.currency, s.total AS invoiced, 0 AS collected, 0 AS payments
                FROM statements s WHERE s.status = 'issued' AND s.issue_date BETWEEN ? AND ?
                UNION ALL
                SELECT s.client_id, s.currency, 0, p.amount, 1
                FROM payments p JOIN statements s ON s.id = p.statement_id
                WHERE p.paid_at BETWEEN ? AND ?
             ) x
             JOIN clients c ON c.id = x.client_id
             GROUP BY c.id, c.name, c.code, x.currency
             ORDER BY collected DESC, invoiced DESC",
            [$from, $to, $from, $to]
        );
    }

    /** Satırları para birimine göre toplar: ['USD' => ['amount' => …], …] */
    public static function totalsByCurrency(array $rows, array $fields): array
    {
        $out = [];
        foreach ($rows as $r) {
            foreach ($fields as $f) {
                $out[$r['currency']][$f] = ($out[$r['currency']][$f] ?? 0) + (float) $r[$f];
            }
        }
        ksort($out);
        return $out;
    }

    /** Excel'de Türkçe karakterlerle açılan CSV (UTF-8 BOM, noktalı virgül). */
    public static function csv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v) => is_float($v) ? number_format($v, 2, ',', '') : $v, $row), ';');
        }
        fclose($out);
        exit;
    }

    private static function date(?string $d): ?string
    {
        $dt = $d ? \DateTime::createFromFormat('Y-m-d', $d) : false;
        return $dt && $dt->format('Y-m-d') === $d ? $d : null;
    }
}
