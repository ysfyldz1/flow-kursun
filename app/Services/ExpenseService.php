<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

final class ExpenseService
{
    public const CATEGORIES = ['court_fee', 'notary', 'expert', 'travel', 'courier', 'translation', 'other'];

    public static function list(array $f): array
    {
        $where = ['e.expense_date BETWEEN ? AND ?'];
        $params = [$f['from'], $f['to']];
        // Herkesin masrafı yalnızca expenses.all ile
        if (!Auth::can('expenses.all')) {
            $where[] = 'e.user_id = ?';
            $params[] = Auth::id();
        } elseif (!empty($f['user_id'])) {
            $where[] = 'e.user_id = ?';
            $params[] = (int) $f['user_id'];
        }
        if (!empty($f['project_id'])) {
            $where[] = 'e.project_id = ?';
            $params[] = (int) $f['project_id'];
        }
        if (($f['state'] ?? '') === 'unbilled') {
            $where[] = 'e.is_billable = 1 AND e.from_advance = 0 AND e.statement_id IS NULL';
        } elseif (($f['state'] ?? '') === 'advance') {
            $where[] = 'e.from_advance = 1';
        } elseif (($f['state'] ?? '') === 'billed') {
            $where[] = 'e.statement_id IS NOT NULL';
        } elseif (($f['state'] ?? '') === 'internal') {
            $where[] = 'e.is_billable = 0';
        }
        return DB::all(
            'SELECT e.*, p.number AS project_number, p.name AS project_name, c.name AS client_name,
                    u.name AS user_name, s.number AS statement_number
             FROM expenses e
             JOIN projects p ON p.id = e.project_id
             JOIN clients c ON c.id = e.client_id
             JOIN users u ON u.id = e.user_id
             LEFT JOIN statements s ON s.id = e.statement_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.expense_date DESC, e.id DESC',
            $params
        );
    }

    public static function forProject(int $projectId): array
    {
        return DB::all(
            'SELECT e.*, u.name AS user_name FROM expenses e JOIN users u ON u.id = e.user_id
             WHERE e.project_id = ? ORDER BY e.expense_date DESC, e.id DESC',
            [$projectId]
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM expenses WHERE id = ?', [$id]);
    }

    public static function canModify(array $e): bool
    {
        return $e['statement_id'] === null
            && ((int) $e['user_id'] === Auth::id() || Auth::can('expenses.all'));
    }

    public static function create(array $in, ?array $file, string $channel = 'web'): int
    {
        $data = self::validate($in, null);
        $data['receipt_file'] = self::storeReceipt($file);
        $data['created_by'] = Auth::id();
        $id = DB::insert('expenses', $data);
        Audit::log('expense.created', 'expense', $id, ['amount' => $data['amount'], 'project_id' => $data['project_id']], $channel);
        return $id;
    }

    public static function update(int $id, array $in, ?array $file, string $channel = 'web'): void
    {
        $e = self::find($id);
        if (!$e || !self::canModify($e)) {
            throw new ValidationException(['expense' => __('expenses.locked')]);
        }
        $data = self::validate($in, $e);
        if ($new = self::storeReceipt($file)) {
            self::deleteReceipt($e);
            $data['receipt_file'] = $new;
        } elseif (!empty($in['remove_receipt'])) {
            self::deleteReceipt($e);
            $data['receipt_file'] = null;
        }
        DB::update('expenses', $data, 'id = ?', [$id]);
        Audit::log('expense.updated', 'expense', $id, $data, $channel);
    }

    public static function delete(int $id, string $channel = 'web'): void
    {
        $e = self::find($id);
        if (!$e || !self::canModify($e)) {
            throw new ValidationException(['expense' => __('expenses.locked')]);
        }
        DB::query('DELETE FROM expenses WHERE id = ?', [$id]);
        self::deleteReceipt($e);
        Audit::log('expense.deleted', 'expense', $id, $e, $channel);
    }

    public static function receiptPath(array $e): ?string
    {
        $path = BASE_PATH . '/storage/uploads/expenses/' . basename((string) $e['receipt_file']);
        return $e['receipt_file'] && is_file($path) ? $path : null;
    }

    private static function validate(array $in, ?array $current): array
    {
        $errors = [];
        $userId = (int) ($in['user_id'] ?? ($current['user_id'] ?? Auth::id()));
        if ($userId !== Auth::id() && !Auth::can('expenses.all')) {
            $userId = (int) ($current['user_id'] ?? Auth::id());
        }

        $projectId = (int) ($in['project_id'] ?? 0);
        $project = DB::one('SELECT id, client_id, status, currency, billing_type FROM projects WHERE id = ?', [$projectId]);
        if ($project && !Access::canSeeProject($projectId) && (int) ($current['project_id'] ?? 0) !== $projectId) {
            $project = null;   // görmediği projeye kayıt giremez
        }
        if (!$project || ($project['status'] === 'closed' && (int) ($current['project_id'] ?? 0) !== $projectId)) {
            $errors['project_id'] = __('expenses.project_required');
        }
        $date = (string) ($in['expense_date'] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) {
            $errors['expense_date'] = __('validation.date');
        }
        $category = (string) ($in['category'] ?? '');
        if (!in_array($category, self::CATEGORIES, true)) {
            $errors['category'] = __('validation.required');
        }
        $amount = parse_money($in['amount'] ?? '');
        if (!$amount) {
            $errors['amount'] = __('validation.number');
        }
        $description = trim((string) ($in['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = __('validation.required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return [
            'user_id'      => $userId,
            'project_id'   => $projectId,
            'client_id'    => (int) $project['client_id'],
            'expense_date' => $date,
            'category'     => $category,
            'description'  => $description,
            'amount'       => $amount,
            'currency'     => $project['currency'],
            'is_billable'  => (int) !empty($in['is_billable']),
            // Avanslı müvekkilin masrafı avanstan düşülür, döküme girmez
            'from_advance' => (int) \Modules\Advances\AdvanceService::coversExpense((int) $project['client_id'], !empty($in['is_billable']), $current),
        ];
    }

    private static function storeReceipt(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $ext = $file['error'] === UPLOAD_ERR_OK
            ? (['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][mime_content_type($file['tmp_name'])] ?? null)
            : null;
        if (!$ext) {
            throw new ValidationException(['receipt' => __('portal.receipt_type')]);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new ValidationException(['receipt' => __('billing.invoice_size')]);
        }
        $dir = BASE_PATH . '/storage/uploads/expenses';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . ".$ext";
        move_uploaded_file($file['tmp_name'], "$dir/$name");
        return $name;
    }

    private static function deleteReceipt(array $e): void
    {
        if ($path = self::receiptPath($e)) {
            unlink($path);
        }
    }
}
