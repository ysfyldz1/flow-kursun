<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Yönetici panosu için ofis geneli göstergeler (reports.view). Saatler para biriminden bağımsız toplanır;
 * tutarlar her zaman tek para biriminde gösterilir (kur çevrimi yok).
 */
final class OfficeStats
{
    public const RANGES = ['month', 'year', '12m'];

    /** Sıralama grafikleri için dönem başlangıç-bitiş. */
    public static function range(string $range): array
    {
        return match ($range) {
            'month' => [date('Y-m-01'), date('Y-m-t')],
            'year'  => [date('Y-01-01'), date('Y-12-31')],
            default => [date('Y-m-01', strtotime('first day of -11 months')), date('Y-m-t')],
        };
    }

    /** Özet kutular: bu ay saat ve geçen aya göre değişim, faturalanabilir oran, faturalanmamış iş, vadesi geçen alacak. */
    public static function kpis(): array
    {
        $month = fn (string $from, string $to) => DB::one(
            'SELECT COALESCE(SUM(minutes), 0) AS total, COALESCE(SUM(CASE WHEN is_billable = 1 THEN minutes END), 0) AS billable
             FROM time_entries WHERE work_date BETWEEN ? AND ?',
            [$from, $to]
        );
        $now = $month(date('Y-m-01'), date('Y-m-t'));
        // Geçen ayın aynı gününe kadar: ay ortasında yarım ayı tam ayla kıyaslamamak için
        $lastFrom = date('Y-m-01', strtotime('first day of last month'));
        $lastTo = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($lastFrom))), strtotime('-1 month')));
        $prev = $month($lastFrom, $lastTo);

        $overdue = DB::one(
            "SELECT COUNT(*) AS n, COUNT(DISTINCT client_id) AS clients FROM statements
             WHERE status = 'issued' AND paid_amount < total AND due_date < CURDATE()"
        );
        $unbilled = DB::one(
            "SELECT COALESCE(SUM(t.minutes), 0) AS minutes, MIN(t.work_date) AS oldest
             FROM time_entries t JOIN projects p ON p.id = t.project_id
             WHERE t.statement_id IS NULL AND t.is_billable = 1 AND p.billing_type <> 'non_billable'"
        );
        return [
            'minutes'        => (int) $now['total'],
            'change'         => (int) $prev['total'] > 0 ? ((int) $now['total'] - (int) $prev['total']) / (int) $prev['total'] : null,
            'billable_ratio' => (int) $now['total'] > 0 ? (int) $now['billable'] / (int) $now['total'] : null,
            'unbilled'       => (int) $unbilled['minutes'],
            'unbilled_oldest'=> $unbilled['oldest'],
            'overdue'        => (int) $overdue['n'],
            'overdue_clients'=> (int) $overdue['clients'],
        ];
    }

    /** Son 12 ay, ay başına faturalanabilir/faturalanmaz saat (boş aylar 0). */
    public static function monthlyHours(): array
    {
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $out[date('Y-m', strtotime("first day of -$i months"))] = ['billable' => 0.0, 'other' => 0.0];
        }
        $rows = DB::all(
            "SELECT DATE_FORMAT(work_date, '%Y-%m') AS ym,
                    SUM(CASE WHEN is_billable = 1 THEN minutes ELSE 0 END) AS billable,
                    SUM(CASE WHEN is_billable = 0 THEN minutes ELSE 0 END) AS other
             FROM time_entries WHERE work_date >= ? GROUP BY ym",
            [array_key_first($out) . '-01']
        );
        foreach ($rows as $r) {
            if (isset($out[$r['ym']])) {
                $out[$r['ym']] = ['billable' => $r['billable'] / 60, 'other' => $r['other'] / 60];
            }
        }
        return $out;
    }

    /** En çok süre harcanan müvekkiller; ilk $limit dışındakiler "Diğer" olarak toplanır. */
    public static function topClients(string $from, string $to, int $limit = 7): array
    {
        $rows = DB::all(
            'SELECT c.id, c.name, SUM(t.minutes) / 60 AS hours
             FROM time_entries t JOIN clients c ON c.id = t.client_id
             WHERE t.work_date BETWEEN ? AND ?
             GROUP BY c.id, c.name ORDER BY hours DESC',
            [$from, $to]
        );
        $top = array_map(fn ($r) => ['label' => $r['name'], 'value' => (float) $r['hours'], 'url' => '/clients/' . $r['id']], array_slice($rows, 0, $limit));
        $rest = array_sum(array_column(array_slice($rows, $limit), 'hours'));
        if ($rest > 0) {
            $top[] = ['label' => __('dash.other_clients', ['count' => count($rows) - $limit]), 'value' => (float) $rest, 'url' => null];
        }
        return $top;
    }

    /** Kişi bazında saat (aktif kullanıcılar, hiç girmeyen de 0 ile görünür). */
    public static function byPerson(string $from, string $to): array
    {
        return array_map(fn ($r) => ['label' => $r['name'], 'value' => (float) $r['hours'], 'billable' => (float) $r['billable']], DB::all(
            'SELECT u.name, COALESCE(SUM(t.minutes), 0) / 60 AS hours,
                    COALESCE(SUM(CASE WHEN t.is_billable = 1 THEN t.minutes END), 0) / 60 AS billable
             FROM users u LEFT JOIN time_entries t ON t.user_id = u.id AND t.work_date BETWEEN ? AND ?
             WHERE u.is_active = 1 AND (u.id IN (SELECT user_id FROM time_entries) OR u.hourly_rate IS NOT NULL)
             GROUP BY u.id, u.name ORDER BY hours DESC, u.name',
            [$from, $to]
        ));
    }

    /** Dökümlerde kullanılan para birimleri, hacme göre (ilk varsayılan). */
    public static function currencies(): array
    {
        return array_column(DB::all(
            "SELECT currency, COUNT(*) AS n FROM statements WHERE status = 'issued' GROUP BY currency ORDER BY n DESC"
        ), 'currency');
    }

    /** Son 6 ay, seçili para biriminde düzenlenen döküm tutarı ve tahsil edilen ödeme. */
    public static function collections(string $currency): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $out[date('Y-m', strtotime("first day of -$i months"))] = ['invoiced' => 0.0, 'collected' => 0.0];
        }
        $from = array_key_first($out) . '-01';
        foreach (DB::all(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym, SUM(total) AS v FROM statements
             WHERE status = 'issued' AND currency = ? AND issue_date >= ? GROUP BY ym",
            [$currency, $from]
        ) as $r) {
            $out[$r['ym']]['invoiced'] = (float) $r['v'];
        }
        foreach (DB::all(
            "SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS ym, SUM(p.amount) AS v FROM payments p
             JOIN statements s ON s.id = p.statement_id WHERE s.currency = ? AND p.paid_at >= ? GROUP BY ym",
            [$currency, $from]
        ) as $r) {
            if (isset($out[$r['ym']])) {
                $out[$r['ym']]['collected'] = (float) $r['v'];
            }
        }
        return $out;
    }
}
