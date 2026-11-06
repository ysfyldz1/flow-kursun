<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/**
 * Hizmet dökümü yaşam döngüsü:
 *   taslak (zaman kayıtları bağlanır, satırlar düzenlenebilir)
 *   → kesinleşmiş (numara verilir, kilitlenir; fatura yüklenir, ödemeler girilir)
 *   → iptal (ödeme yoksa; bağlı zaman kayıtları serbest kalır)
 * Ödeme durumu ödenen tutardan türetilir: unpaid / partial / paid (+ vadesi geçmişse overdue).
 */
final class StatementService
{
    public const METHODS = ['transfer', 'cash', 'card', 'other'];

    public static function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];
        $status = $filters['status'] ?? 'open';
        if ($status === 'draft') {
            $where[] = "s.status = 'draft'";
        } elseif ($status === 'open') {
            $where[] = "s.status = 'issued' AND s.paid_amount < s.total";
        } elseif ($status === 'paid') {
            $where[] = "s.status = 'issued' AND s.paid_amount >= s.total";
        } elseif ($status === 'cancelled') {
            $where[] = "s.status = 'cancelled'";
        }
        if (!empty($filters['client_id'])) {
            $where[] = 's.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(s.number LIKE ? OR c.name LIKE ? OR s.invoice_number LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        return DB::all(
            'SELECT s.*, c.name AS client_name, c.code AS client_code
             FROM statements s JOIN clients c ON c.id = s.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY COALESCE(s.issue_date, s.created_at) DESC, s.id DESC',
            $params
        );
    }

    public static function find(int $id): ?array
    {
        $s = DB::one(
            'SELECT s.*, c.name AS client_name, c.code AS client_code, c.type AS client_type,
                    c.tax_id AS client_tax_id, c.tax_office AS client_tax_office,
                    c.address AS client_address, c.city AS client_city, c.country AS client_country
             FROM statements s JOIN clients c ON c.id = s.client_id WHERE s.id = ?',
            [$id]
        );
        if ($s) {
            $s['lines'] = DB::all(
                'SELECT l.*, p.number AS project_number, p.name AS project_name
                 FROM statement_lines l LEFT JOIN projects p ON p.id = l.project_id
                 WHERE l.statement_id = ? ORDER BY l.project_id, l.is_info, l.sort, l.work_date, l.id',
                [$id]
            );
            $s['payments'] = DB::all(
                'SELECT p.*, u.name AS user_name FROM payments p LEFT JOIN users u ON u.id = p.created_by
                 WHERE p.statement_id = ? ORDER BY p.paid_at, p.id',
                [$id]
            );
        }
        return $s;
    }

    /** unpaid | partial | paid | overdue | draft | cancelled */
    public static function paymentStatus(array $s): string
    {
        if ($s['status'] !== 'issued') {
            return $s['status'];
        }
        if ((float) $s['paid_amount'] >= (float) $s['total'] - 0.005) {
            return 'paid';
        }
        if ($s['due_date'] && $s['due_date'] < date('Y-m-d')) {
            return 'overdue';
        }
        return (float) $s['paid_amount'] > 0 ? 'partial' : 'unpaid';
    }

    public static function balance(array $s): float
    {
        return round((float) $s['total'] - (float) $s['paid_amount'], 2);
    }

    /** Dökülmemiş faturalanabilir kayıtları olan müvekkil / para birimi ikilileri */
    public static function unbilledSummary(): array
    {
        return DB::all(
            "SELECT c.id AS client_id, c.code, c.name, t.currency,
                    COUNT(*) AS entries, SUM(t.minutes) AS minutes, SUM(COALESCE(t.amount, 0)) AS amount,
                    MIN(t.work_date) AS first_date, MAX(t.work_date) AS last_date
             FROM time_entries t
             JOIN clients c ON c.id = t.client_id
             JOIN projects p ON p.id = t.project_id
             WHERE t.statement_id IS NULL AND t.is_billable = 1 AND p.billing_type <> 'non_billable'
             GROUP BY c.id, c.code, c.name, t.currency
             ORDER BY c.name"
        );
    }

    public static function countOpen(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM statements WHERE status = 'issued' AND paid_amount < total");
    }

    // --- Taslak ---

    public static function createDraft(array $in, string $channel = 'web'): int
    {
        $clientId = (int) ($in['client_id'] ?? 0);
        $currency = (string) ($in['currency'] ?? '');
        $start = (string) ($in['period_start'] ?? '') ?: null;
        $end = (string) ($in['period_end'] ?? '') ?: date('Y-m-d');

        $client = DB::one('SELECT * FROM clients WHERE id = ?', [$clientId]);
        $errors = [];
        if (!$client) {
            $errors['client_id'] = __('validation.required');
        }
        if (!in_array($currency, UserService::CURRENCIES, true)) {
            $errors['currency'] = __('validation.required');
        }
        foreach (['period_start' => $start, 'period_end' => $end] as $k => $d) {
            if ($d !== null && !self::validDate($d)) {
                $errors[$k] = __('validation.date');
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return DB::transaction(function () use ($client, $currency, $start, $end, $channel) {
            $isCompany = $client['type'] === 'company';
            $id = DB::insert('statements', [
                'client_id'        => $client['id'],
                'currency'         => $currency,
                'locale'           => $client['locale'],
                'period_start'     => $start,
                'period_end'       => $end,
                'vat_rate'         => (float) Settings::get('billing.vat_rate', 20),
                'withholding_rate' => $isCompany ? (float) Settings::get('billing.withholding_rate', 20) : 0,
                'show_bank'        => Settings::get('billing.bank_accounts') ? 1 : 0,
                'created_by'       => Auth::id(),
            ]);

            $projects = DB::all(
                "SELECT * FROM projects WHERE client_id = ? AND currency = ? AND billing_type <> 'non_billable' ORDER BY number",
                [$client['id'], $currency]
            );
            $sort = 0;
            foreach ($projects as $p) {
                $entries = DB::all(
                    'SELECT t.*, u.name AS user_name FROM time_entries t JOIN users u ON u.id = t.user_id
                     WHERE t.project_id = ? AND t.statement_id IS NULL AND t.is_billable = 1
                       AND t.work_date <= ? ' . ($start ? 'AND t.work_date >= ?' : '') . '
                     ORDER BY t.work_date, t.id',
                    $start ? [$p['id'], $end, $start] : [$p['id'], $end]
                );

                $flat = null;
                if ($p['billing_type'] === 'retainer' && $p['retainer_amount'] !== null) {
                    $flat = ['type' => 'retainer', 'amount' => (float) $p['retainer_amount']];
                } elseif ($p['billing_type'] === 'fixed' && $p['fixed_fee'] !== null && !self::fixedFeeBilled((int) $p['id'])) {
                    $flat = ['type' => 'fixed', 'amount' => (float) $p['fixed_fee']];
                }
                if (!$entries && !$flat) {
                    continue;
                }

                if ($flat) {
                    DB::insert('statement_lines', [
                        'statement_id' => $id,
                        'type'         => $flat['type'],
                        'project_id'   => $p['id'],
                        'description'  => $p['name'],
                        'amount'       => $flat['amount'],
                        'sort'         => $sort++,
                    ]);
                }
                $isInfo = $p['billing_type'] !== 'hourly';
                foreach ($entries as $t) {
                    DB::insert('statement_lines', [
                        'statement_id'  => $id,
                        'type'          => 'time',
                        'project_id'    => $p['id'],
                        'time_entry_id' => $t['id'],
                        'work_date'     => $t['work_date'],
                        'user_name'     => $t['user_name'],
                        'description'   => $t['description'],
                        'hours'         => round($t['minutes'] / 60, 2),
                        'rate'          => $isInfo ? null : $t['rate'],
                        'amount'        => $isInfo ? null : $t['amount'],
                        'is_info'       => (int) $isInfo,
                        'sort'          => $sort++,
                    ]);
                    DB::update('time_entries', ['statement_id' => $id], 'id = ?', [$t['id']]);
                }
            }

            if (!DB::value('SELECT COUNT(*) FROM statement_lines WHERE statement_id = ?', [$id])) {
                throw new ValidationException(['client_id' => __('billing.nothing_to_bill')]);
            }
            self::recalculate($id);
            Audit::log('statement.drafted', 'statement', $id, ['client_id' => $client['id'], 'currency' => $currency], $channel);
            return $id;
        });
    }

    public static function updateDraft(int $id, array $in, string $channel = 'web'): void
    {
        $s = self::draftOrFail($id);
        $errors = [];

        $vat = parse_money($in['vat_rate'] ?? '0');
        $wh = parse_money($in['withholding_rate'] ?? '0');
        if ($vat === false || $vat > 100) {
            $errors['vat_rate'] = __('validation.number');
        }
        if ($wh === false || $wh > 100) {
            $errors['withholding_rate'] = __('validation.number');
        }
        $due = (string) ($in['due_date'] ?? '') ?: null;
        if ($due !== null && !self::validDate($due)) {
            $errors['due_date'] = __('validation.date');
        }

        // Satır düzenlemeleri: açıklama, saat, ücret, tutar
        $lines = [];
        foreach ((array) ($in['lines'] ?? []) as $lineId => $l) {
            $lineId = (int) $lineId;
            $amount = parse_money($l['amount'] ?? '');
            if ($amount === false) {
                $errors["lines.$lineId.amount"] = __('validation.number');
            }
            $desc = trim((string) ($l['description'] ?? ''));
            if ($desc === '') {
                $errors["lines.$lineId.description"] = __('validation.required');
            }
            $lines[$lineId] = ['description' => $desc, 'amount' => $amount === false ? null : $amount];
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        DB::transaction(function () use ($id, $s, $in, $vat, $wh, $due, $lines, $channel) {
            foreach ($lines as $lineId => $l) {
                $line = DB::one('SELECT * FROM statement_lines WHERE id = ? AND statement_id = ?', [$lineId, $id]);
                if (!$line) {
                    continue;
                }
                $data = ['description' => $l['description']];
                if (!$line['is_info']) {
                    $data['amount'] = $l['amount'];
                }
                DB::update('statement_lines', $data, 'id = ?', [$lineId]);
            }
            DB::update('statements', [
                'vat_rate'         => $vat ?? 0,
                'withholding_rate' => $wh ?? 0,
                'due_date'         => $due,
                'notes'            => trim((string) ($in['notes'] ?? '')) ?: null,
                'show_bank'        => !empty($in['show_bank']) ? 1 : 0,
            ], 'id = ?', [$id]);
            self::recalculate($id);
            Audit::log('statement.updated', 'statement', $id, [], $channel);
        });
    }

    /** Masraf ya da diğer kalem ekler. */
    public static function addLine(int $id, array $in, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        $type = in_array($in['type'] ?? '', ['expense', 'other'], true) ? $in['type'] : 'other';
        $desc = trim((string) ($in['description'] ?? ''));
        $amount = parse_money($in['amount'] ?? '');
        $errors = [];
        if ($desc === '') {
            $errors['new_description'] = __('validation.required');
        }
        if ($amount === null || $amount === false) {
            $errors['new_amount'] = __('validation.number');
        }
        $projectId = (int) ($in['project_id'] ?? 0) ?: null;
        if ($errors) {
            throw new ValidationException($errors);
        }
        DB::insert('statement_lines', [
            'statement_id' => $id,
            'type'         => $type,
            'project_id'   => $projectId,
            'description'  => $desc,
            'amount'       => $amount,
            'sort'         => 1000,
        ]);
        self::recalculate($id);
        Audit::log('statement.line_added', 'statement', $id, ['type' => $type, 'amount' => $amount], $channel);
    }

    /** Satırı çıkarır; zaman kaydıysa kayıt serbest kalır (sonraki döküme girebilir). */
    public static function removeLine(int $id, int $lineId, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        $line = DB::one('SELECT * FROM statement_lines WHERE id = ? AND statement_id = ?', [$lineId, $id]);
        if (!$line) {
            return;
        }
        DB::transaction(function () use ($id, $line, $channel) {
            if ($line['time_entry_id']) {
                DB::update('time_entries', ['statement_id' => null], 'id = ? AND statement_id = ?', [$line['time_entry_id'], $id]);
            }
            DB::query('DELETE FROM statement_lines WHERE id = ?', [$line['id']]);
            self::recalculate($id);
            Audit::log('statement.line_removed', 'statement', $id, ['line' => $line['description']], $channel);
        });
    }

    public static function issue(int $id, string $channel = 'web'): void
    {
        $s = self::draftOrFail($id);
        $missing = DB::value('SELECT COUNT(*) FROM statement_lines WHERE statement_id = ? AND is_info = 0 AND amount IS NULL', [$id]);
        if ($missing) {
            throw new ValidationException(['issue' => __('billing.missing_amounts', ['count' => $missing])]);
        }
        if ((float) $s['total'] <= 0) {
            throw new ValidationException(['issue' => __('billing.zero_total')]);
        }
        DB::transaction(function () use ($id, $s, $channel) {
            $year = date('Y');
            $number = sprintf('HD-%s-%04d', $year, Counter::next("statement.$year"));
            DB::update('statements', [
                'number'     => $number,
                'status'     => 'issued',
                'issue_date' => date('Y-m-d'),
                'due_date'   => $s['due_date'] ?: date('Y-m-d', strtotime('+' . (int) Settings::get('billing.due_days', 15) . ' days')),
                'issued_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            Audit::log('statement.issued', 'statement', $id, ['number' => $number, 'total' => $s['total']], $channel);
        });
    }

    /** Taslak silinir; kesinleşmiş döküm ödeme yoksa iptal edilir (numara boşa düşmez, kayıt kalır). */
    public static function cancel(int $id, string $channel = 'web'): void
    {
        $s = self::find($id) ?? throw new ValidationException(['statement' => __('error.404')]);
        if ($s['status'] === 'cancelled') {
            return;
        }
        if ((float) $s['paid_amount'] > 0) {
            throw new ValidationException(['statement' => __('billing.has_payments')]);
        }
        DB::transaction(function () use ($id, $s, $channel) {
            DB::update('time_entries', ['statement_id' => null], 'statement_id = ?', [$id]);
            if ($s['status'] === 'draft') {
                DB::query('DELETE FROM statements WHERE id = ?', [$id]);
                Audit::log('statement.draft_deleted', 'statement', $id, [], $channel);
            } else {
                DB::update('statements', ['status' => 'cancelled'], 'id = ?', [$id]);
                Audit::log('statement.cancelled', 'statement', $id, ['number' => $s['number']], $channel);
            }
        });
    }

    // --- Resmi fatura ---

    public static function attachInvoice(int $id, array $file, string $invoiceNumber, string $channel = 'web'): void
    {
        $s = self::find($id);
        if (!$s || $s['status'] !== 'issued') {
            throw new ValidationException(['invoice' => __('billing.not_issued')]);
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['invoice' => __('billing.invoice_required')]);
        }
        $mime = mime_content_type($file['tmp_name']);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/xml' => 'xml', 'text/xml' => 'xml'][$mime] ?? null;
        if (!$ext) {
            throw new ValidationException(['invoice' => __('billing.invoice_type')]);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new ValidationException(['invoice' => __('billing.invoice_size')]);
        }

        $dir = BASE_PATH . '/storage/uploads/invoices';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = sprintf('%s-%s.%s', $s['number'], bin2hex(random_bytes(4)), $ext);
        move_uploaded_file($file['tmp_name'], "$dir/$name");
        if ($s['invoice_file'] && is_file("$dir/{$s['invoice_file']}")) {
            unlink("$dir/{$s['invoice_file']}");
        }
        DB::update('statements', [
            'invoice_file'        => $name,
            'invoice_number'      => trim($invoiceNumber) ?: null,
            'invoice_uploaded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        Audit::log('statement.invoice_uploaded', 'statement', $id, ['invoice_number' => $invoiceNumber], $channel);
    }

    public static function invoicePath(array $s): ?string
    {
        $path = BASE_PATH . '/storage/uploads/invoices/' . basename((string) $s['invoice_file']);
        return $s['invoice_file'] && is_file($path) ? $path : null;
    }

    // --- Ödemeler ---

    public static function addPayment(int $id, array $in, string $source = 'office', string $channel = 'web'): void
    {
        $s = self::find($id);
        if (!$s || $s['status'] !== 'issued') {
            throw new ValidationException(['payment' => __('billing.not_issued')]);
        }
        $amount = parse_money($in['amount'] ?? '');
        $date = (string) ($in['paid_at'] ?? '') ?: date('Y-m-d');
        $errors = [];
        if (!$amount) {
            $errors['pay_amount'] = __('validation.number');
        } elseif ($amount > self::balance($s) + 0.005) {
            $errors['pay_amount'] = __('billing.overpayment', ['balance' => format_money(self::balance($s), $s['currency'])]);
        }
        if (!self::validDate($date)) {
            $errors['paid_at'] = __('validation.date');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        DB::transaction(function () use ($id, $in, $amount, $date, $source, $channel) {
            $pid = DB::insert('payments', [
                'statement_id' => $id,
                'amount'       => $amount,
                'paid_at'      => $date,
                'method'       => in_array($in['method'] ?? '', self::METHODS, true) ? $in['method'] : 'transfer',
                'reference'    => trim((string) ($in['reference'] ?? '')) ?: null,
                'notes'        => trim((string) ($in['notes'] ?? '')) ?: null,
                'source'       => $source,
                'created_by'   => Auth::id(),
            ]);
            self::recalculatePaid($id);
            Audit::log('payment.recorded', 'statement', $id, ['payment_id' => $pid, 'amount' => $amount], $channel);
        });
    }

    public static function deletePayment(int $id, int $paymentId, string $channel = 'web'): void
    {
        $p = DB::one('SELECT * FROM payments WHERE id = ? AND statement_id = ?', [$paymentId, $id]);
        if (!$p) {
            return;
        }
        DB::transaction(function () use ($id, $p, $channel) {
            DB::query('DELETE FROM payments WHERE id = ?', [$p['id']]);
            self::recalculatePaid($id);
            Audit::log('payment.deleted', 'statement', $id, $p, $channel);
        });
    }

    // --- Yardımcılar ---

    private static function recalculate(int $id): void
    {
        $s = DB::one('SELECT vat_rate, withholding_rate FROM statements WHERE id = ?', [$id]);
        $subtotal = round((float) DB::value('SELECT COALESCE(SUM(amount), 0) FROM statement_lines WHERE statement_id = ? AND is_info = 0', [$id]), 2);
        $vat = round($subtotal * (float) $s['vat_rate'] / 100, 2);
        $wh = round($subtotal * (float) $s['withholding_rate'] / 100, 2);
        DB::update('statements', [
            'subtotal'           => $subtotal,
            'vat_amount'         => $vat,
            'withholding_amount' => $wh,
            'total'              => round($subtotal + $vat - $wh, 2),
        ], 'id = ?', [$id]);
    }

    private static function recalculatePaid(int $id): void
    {
        DB::update('statements', [
            'paid_amount' => (float) DB::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE statement_id = ?', [$id]),
        ], 'id = ?', [$id]);
    }

    private static function fixedFeeBilled(int $projectId): bool
    {
        return (bool) DB::value(
            "SELECT COUNT(*) FROM statement_lines l JOIN statements s ON s.id = l.statement_id
             WHERE l.project_id = ? AND l.type = 'fixed' AND s.status <> 'cancelled'",
            [$projectId]
        );
    }

    private static function draftOrFail(int $id): array
    {
        $s = DB::one('SELECT * FROM statements WHERE id = ?', [$id]);
        if (!$s || $s['status'] !== 'draft') {
            throw new ValidationException(['statement' => __('billing.not_draft')]);
        }
        return $s;
    }

    private static function validDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    }
}
