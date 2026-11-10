<?php

declare(strict_types=1);

namespace Modules\Advances;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Audit;

/**
 * Müvekkil masraf avansı. Avans girişleri client_advances'ta, düşümler masrafın kendisinde
 * (expenses.from_advance) tutulur; masraf düzeltilince ya da silinince bakiye kendiliğinden doğru kalır.
 */
final class AdvanceService
{
    public const TYPES = ['deposit', 'refund', 'adjust'];
    public const METHODS = ['transfer', 'cash', 'card', 'other'];

    /** Para birimi başına: alınan (giriş − iade ± düzeltme), harcanan, kalan. */
    public static function balances(int $clientId): array
    {
        return DB::all(
            'SELECT currency, SUM(received) AS received, SUM(spent) AS spent, SUM(received) - SUM(spent) AS balance
             FROM (
                SELECT currency, amount AS received, 0 AS spent FROM client_advances WHERE client_id = ?
                UNION ALL
                SELECT currency, 0, amount FROM expenses WHERE client_id = ? AND from_advance = 1
             ) x
             GROUP BY currency ORDER BY currency',
            [$clientId, $clientId]
        );
    }

    /** Avans hesabı açık ya da hareketi olan müvekkiller, para birimi başına bakiyeyle. */
    public static function overview(): array
    {
        return DB::all(
            'SELECT c.id AS client_id, c.code, c.name, c.uses_advance, x.currency,
                    SUM(x.received) AS received, SUM(x.spent) AS spent, SUM(x.received) - SUM(x.spent) AS balance,
                    MAX(x.d) AS last_date
             FROM clients c
             LEFT JOIN (
                SELECT client_id, currency, amount AS received, 0 AS spent, entry_date AS d FROM client_advances
                UNION ALL
                SELECT client_id, currency, 0, amount, expense_date FROM expenses WHERE from_advance = 1
             ) x ON x.client_id = c.id
             WHERE c.uses_advance = 1 OR x.client_id IS NOT NULL
             GROUP BY c.id, c.code, c.name, c.uses_advance, x.currency
             ORDER BY balance < 0 DESC, c.name'
        );
    }

    /**
     * Dönem ekstresi: önceki dönemden devir, dönem hareketleri tarih sırasıyla (yürüyen bakiyeli), dönem toplamları ve kalan.
     * @return array{opening: float, rows: array, in: float, out: float, closing: float}
     */
    public static function ledger(int $clientId, string $currency, string $from, string $to): array
    {
        $opening = (float) DB::value(
            'SELECT COALESCE((SELECT SUM(amount) FROM client_advances WHERE client_id = ? AND currency = ? AND entry_date < ?), 0)
                  - COALESCE((SELECT SUM(amount) FROM expenses WHERE client_id = ? AND currency = ? AND from_advance = 1 AND expense_date < ?), 0)',
            [$clientId, $currency, $from, $clientId, $currency, $from]
        );
        $rows = DB::all(
            "SELECT 'advance' AS kind, a.id, a.type, a.entry_date AS d, a.amount, a.method, a.reference,
                    a.notes AS description, NULL AS category, NULL AS project_number, NULL AS project_name, a.created_at
             FROM client_advances a WHERE a.client_id = ? AND a.currency = ? AND a.entry_date BETWEEN ? AND ?
             UNION ALL
             SELECT 'expense', e.id, NULL, e.expense_date, -e.amount, NULL, NULL,
                    e.description, e.category, p.number, p.name, e.created_at
             FROM expenses e JOIN projects p ON p.id = e.project_id
             WHERE e.client_id = ? AND e.currency = ? AND e.from_advance = 1 AND e.expense_date BETWEEN ? AND ?
             ORDER BY d, created_at, id",
            [$clientId, $currency, $from, $to, $clientId, $currency, $from, $to]
        );
        $balance = $opening;
        $in = $out = 0.0;
        foreach ($rows as &$r) {
            $amount = (float) $r['amount'];
            $amount > 0 ? $in += $amount : $out -= $amount;
            $balance += $amount;
            $r['balance'] = round($balance, 2);
        }
        return ['opening' => round($opening, 2), 'rows' => $rows, 'in' => round($in, 2), 'out' => round($out, 2), 'closing' => round($balance, 2)];
    }

    public static function currencies(int $clientId): array
    {
        $own = array_column(self::balances($clientId), 'currency');
        $default = (string) DB::one('SELECT currency FROM clients WHERE id = ?', [$clientId])['currency'];
        return array_values(array_unique([$default, ...$own]));
    }

    public static function setEnabled(int $clientId, bool $on, string $channel = 'web'): void
    {
        DB::update('clients', ['uses_advance' => (int) $on], 'id = ?', [$clientId]);
        Audit::log($on ? 'advance.enabled' : 'advance.disabled', 'client', $clientId, [], $channel);
    }

    public static function create(int $clientId, array $in, string $channel = 'web'): int
    {
        $errors = [];
        $type = in_array($in['type'] ?? '', self::TYPES, true) ? $in['type'] : '';
        if ($type === '') {
            $errors['type'] = __('validation.required');
        }
        // parse_money eksiyi kabul etmez; işaret yalnız düzeltmede anlamlı
        $raw = trim((string) ($in['amount'] ?? ''));
        $negative = $type === 'adjust' && str_starts_with($raw, '-');
        $amount = parse_money(ltrim($raw, '-'));
        if ($amount && $negative) {
            $amount = -$amount;
        }
        if (!$amount) {
            $errors['amount'] = __('validation.number');
        }
        $currency = strtoupper(trim((string) ($in['currency'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = __('validation.required');
        }
        $date = (string) ($in['entry_date'] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors['entry_date'] = __('validation.date');
        }
        if ($type === 'adjust' && trim((string) ($in['notes'] ?? '')) === '') {
            $errors['notes'] = __('adv.adjust_note_required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $data = [
            'client_id'  => $clientId,
            'currency'   => $currency,
            'type'       => $type,
            'amount'     => $type === 'refund' ? -abs($amount) : $amount,
            'entry_date' => $date,
            'method'     => in_array($in['method'] ?? '', self::METHODS, true) ? $in['method'] : null,
            'reference'  => trim((string) ($in['reference'] ?? '')) ?: null,
            'notes'      => trim((string) ($in['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ];
        $id = DB::insert('client_advances', $data);
        Audit::log('advance.created', 'client', $clientId, $data, $channel);
        return $id;
    }

    public static function delete(int $clientId, int $id, string $channel = 'web'): void
    {
        $row = DB::one('SELECT * FROM client_advances WHERE id = ? AND client_id = ?', [$id, $clientId]);
        if (!$row) {
            throw new ValidationException(['advance' => __('adv.not_found')]);
        }
        DB::query('DELETE FROM client_advances WHERE id = ?', [$id]);
        Audit::log('advance.deleted', 'client', $clientId, $row, $channel);
    }

    /** Masraf kaydında: avanslı müvekkilin yansıtılacak masrafı avanstan düşülür. */
    public static function coversExpense(int $clientId, bool $billable, ?array $current): bool
    {
        if (!$billable) {
            return false;
        }
        // Daha önce avanstan düşülmüş masraf, müvekkil sonradan kapatılsa da avansta kalır
        if ($current && (int) $current['from_advance'] === 1 && (int) $current['client_id'] === $clientId) {
            return true;
        }
        return (bool) (DB::one('SELECT uses_advance FROM clients WHERE id = ?', [$clientId])['uses_advance'] ?? false);
    }
}
