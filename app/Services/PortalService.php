<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\PortalAuth;
use App\Core\ValidationException;

/** Portal kullanıcıları (davet, erişim) ve müvekkilin ödeme bildirimleri. */
final class PortalService
{
    public const INVITE_DAYS = 7;

    // --- Kullanıcılar ---

    public static function usersFor(int $clientId): array
    {
        return DB::all('SELECT * FROM portal_users WHERE client_id = ? ORDER BY is_active DESC, name', [$clientId]);
    }

    /** Davet oluşturur ya da yeniler; bağlantıda kullanılacak açık belirteci döndürür (yalnızca bir kez görünür). */
    public static function invite(int $clientId, array $in, string $channel = 'web'): string
    {
        $client = DB::one('SELECT id, locale FROM clients WHERE id = ? AND is_active = 1', [$clientId]);
        $name = trim((string) ($in['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $errors = [];
        if (!$client) {
            $errors['portal_email'] = __('validation.required');
        }
        if ($name === '') {
            $errors['portal_name'] = __('validation.required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['portal_email'] = __('validation.email');
        }
        $existing = DB::one('SELECT * FROM portal_users WHERE email = ?', [$email]);
        if ($existing && (int) $existing['client_id'] !== $clientId) {
            $errors['portal_email'] = __('portal.email_other_client');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $token = bin2hex(random_bytes(32));
        $data = [
            'name'              => $name,
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::INVITE_DAYS . ' days')),
            'is_active'         => 1,
            'invited_by'        => Auth::id(),
        ];
        if ($existing) {
            DB::update('portal_users', $data, 'id = ?', [$existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = DB::insert('portal_users', $data + [
                'client_id'  => $clientId,
                'contact_id' => (int) ($in['contact_id'] ?? 0) ?: null,
                'email'      => $email,
                'locale'     => $client['locale'],
            ]);
        }
        Audit::log('portal.invited', 'portal_user', $id, ['email' => $email, 'client_id' => $clientId], $channel);
        return $token;
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return DB::one(
            'SELECT pu.*, c.name AS client_name FROM portal_users pu JOIN clients c ON c.id = pu.client_id
             WHERE pu.invite_token_hash = ? AND pu.invite_expires_at > NOW() AND pu.is_active = 1',
            [hash('sha256', $token)]
        );
    }

    public static function acceptInvite(string $token, string $password, string $confirm): array
    {
        $user = self::findByToken($token) ?? throw new ValidationException(['token' => __('portal.invite_invalid')]);
        if (mb_strlen($password) < 8) {
            throw new ValidationException(['password' => __('validation.password_min')]);
        }
        if ($password !== $confirm) {
            throw new ValidationException(['password_confirm' => __('portal.password_mismatch')]);
        }
        DB::update('portal_users', [
            'password'          => password_hash($password, PASSWORD_DEFAULT),
            'invite_token_hash' => null,
            'invite_expires_at' => null,
        ], 'id = ?', [$user['id']]);
        Audit::log('portal.invite_accepted', 'portal_user', (int) $user['id'], [], 'portal');
        return $user;
    }

    public static function setActive(int $clientId, int $userId, bool $active, string $channel = 'web'): void
    {
        DB::update('portal_users', ['is_active' => (int) $active, 'invite_token_hash' => null], 'id = ? AND client_id = ?', [$userId, $clientId]);
        Audit::log($active ? 'portal.activated' : 'portal.deactivated', 'portal_user', $userId, [], $channel);
    }

    public static function inviteUrl(string $token): string
    {
        return rtrim((string) config('app.url'), '/') . '/portal/invite/' . $token;
    }

    // --- Müvekkilin gördüğü projeler ---

    public static function showHours(): bool
    {
        return Settings::get('portal.show_hours', '1') === '1';
    }

    /** Yalnızca portalda görünür işaretli projeler; açıklar önce. */
    public static function projects(int $clientId): array
    {
        return DB::all(
            "SELECT p.id, p.number, p.name, p.type, p.status, p.court, p.case_number, p.opened_at, p.closed_at,
                    p.budget_hours, u.name AS responsible_name,
                    (SELECT COALESCE(SUM(t.minutes), 0) FROM time_entries t WHERE t.project_id = p.id) AS minutes
             FROM projects p LEFT JOIN users u ON u.id = p.responsible_user_id
             WHERE p.client_id = ? AND p.portal_visible = 1
             ORDER BY FIELD(p.status, 'open', 'on_hold', 'closed'), p.opened_at DESC",
            [$clientId]
        );
    }

    public static function project(int $clientId, int $id): ?array
    {
        $p = DB::one(
            "SELECT p.id, p.number, p.name, p.type, p.status, p.court, p.case_number, p.opened_at, p.closed_at,
                    p.budget_hours, p.billing_type, u.name AS responsible_name, u.email AS responsible_email,
                    (SELECT COALESCE(SUM(t.minutes), 0) FROM time_entries t WHERE t.project_id = p.id) AS minutes
             FROM projects p LEFT JOIN users u ON u.id = p.responsible_user_id
             WHERE p.id = ? AND p.client_id = ? AND p.portal_visible = 1",
            [$id, $clientId]
        );
        if ($p) {
            // Ekip: yalnızca ad ve unvan (ücret bilgisi asla)
            $p['team'] = DB::all(
                'SELECT u.name, u.title FROM project_members m JOIN users u ON u.id = m.user_id
                 WHERE m.project_id = ? AND u.is_active = 1 ORDER BY u.name',
                [$id]
            );
            // Bu projeyi içeren kesinleşmiş dökümler
            $p['statements'] = DB::all(
                "SELECT DISTINCT s.* FROM statements s JOIN statement_lines l ON l.statement_id = s.id
                 WHERE l.project_id = ? AND s.client_id = ? AND s.status = 'issued'
                 ORDER BY s.issue_date DESC",
                [$id, $clientId]
            );
        }
        return $p;
    }

    // --- Müvekkilin gördüğü dökümler ---

    /** Müvekkil yalnızca kendi kesinleşmiş dökümlerini görür. */
    public static function statements(int $clientId): array
    {
        return DB::all(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM payment_notices n WHERE n.statement_id = s.id AND n.status = 'pending') AS pending_notices
             FROM statements s WHERE s.client_id = ? AND s.status = 'issued'
             ORDER BY s.issue_date DESC, s.id DESC",
            [$clientId]
        );
    }

    public static function statement(int $clientId, int $id): ?array
    {
        $s = StatementService::find($id);
        if (!$s || (int) $s['client_id'] !== $clientId || $s['status'] !== 'issued') {
            return null;
        }
        $s['notices'] = DB::all('SELECT * FROM payment_notices WHERE statement_id = ? ORDER BY created_at DESC', [$id]);
        return $s;
    }

    /** Para birimi başına açık bakiye */
    public static function balances(int $clientId): array
    {
        return DB::all(
            "SELECT currency, SUM(total - paid_amount) AS balance, COUNT(*) AS count,
                    SUM(CASE WHEN due_date < CURDATE() THEN total - paid_amount ELSE 0 END) AS overdue
             FROM statements WHERE client_id = ? AND status = 'issued' AND paid_amount < total
             GROUP BY currency",
            [$clientId]
        );
    }

    // --- Ödeme bildirimleri ---

    public static function submitNotice(int $statementId, array $in, ?array $file): int
    {
        $clientId = PortalAuth::clientId();
        $s = self::statement($clientId, $statementId) ?? throw new ValidationException(['notice' => __('error.404')]);
        $balance = StatementService::balance($s);
        $pending = (float) DB::value("SELECT COALESCE(SUM(amount), 0) FROM payment_notices WHERE statement_id = ? AND status = 'pending'", [$statementId]);

        $amount = parse_money($in['amount'] ?? '');
        $date = (string) ($in['paid_at'] ?? '');
        $errors = [];
        if (!$amount) {
            $errors['amount'] = __('validation.number');
        } elseif ($amount > $balance - $pending + 0.005) {
            $errors['amount'] = __('portal.notice_exceeds', ['balance' => format_money(max(0, $balance - $pending), $s['currency'])]);
        }
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) {
            $errors['paid_at'] = __('validation.date');
        }

        $receipt = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$file['error'] === UPLOAD_ERR_OK ? mime_content_type($file['tmp_name']) : ''] ?? null;
            if (!$ext) {
                $errors['receipt'] = __('portal.receipt_type');
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $errors['receipt'] = __('billing.invoice_size');
            } else {
                $receipt = ['tmp' => $file['tmp_name'], 'ext' => $ext];
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $fileName = null;
        if ($receipt) {
            $dir = BASE_PATH . '/storage/uploads/receipts';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $fileName = sprintf('%s-%s.%s', $s['number'], bin2hex(random_bytes(6)), $receipt['ext']);
            move_uploaded_file($receipt['tmp'], "$dir/$fileName");
        }

        $id = DB::insert('payment_notices', [
            'statement_id'   => $statementId,
            'portal_user_id' => PortalAuth::id(),
            'amount'         => $amount,
            'paid_at'        => $date,
            'reference'      => trim((string) ($in['reference'] ?? '')) ?: null,
            'notes'          => trim((string) ($in['notes'] ?? '')) ?: null,
            'receipt_file'   => $fileName,
        ]);
        Audit::log('payment_notice.submitted', 'statement', $statementId, ['notice_id' => $id, 'amount' => $amount, 'portal_user_id' => PortalAuth::id()], 'portal');
        return $id;
    }

    public static function notices(int $statementId): array
    {
        return DB::all(
            'SELECT n.*, pu.name AS portal_user_name, u.name AS reviewer_name
             FROM payment_notices n
             LEFT JOIN portal_users pu ON pu.id = n.portal_user_id
             LEFT JOIN users u ON u.id = n.reviewed_by
             WHERE n.statement_id = ? ORDER BY n.created_at DESC',
            [$statementId]
        );
    }

    public static function pendingCount(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM payment_notices WHERE status = 'pending'");
    }

    /** Onay: bildirim ödeme kaydına dönüşür (tutar ofis tarafından düzeltilebilir). */
    public static function approveNotice(int $statementId, int $noticeId, array $in): void
    {
        $n = DB::one("SELECT * FROM payment_notices WHERE id = ? AND statement_id = ? AND status = 'pending'", [$noticeId, $statementId])
            ?? throw new ValidationException(['notice' => __('portal.notice_not_pending')]);

        DB::transaction(function () use ($statementId, $n, $in) {
            StatementService::addPayment($statementId, [
                'amount'    => $in['amount'] ?? $n['amount'],
                'paid_at'   => $n['paid_at'],
                'method'    => 'transfer',
                'reference' => $n['reference'],
                'notes'     => $n['notes'],
            ], 'portal');
            $paymentId = (int) DB::value('SELECT MAX(id) FROM payments WHERE statement_id = ?', [$statementId]);
            DB::update('payment_notices', [
                'status'      => 'approved',
                'payment_id'  => $paymentId,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$n['id']]);
            Audit::log('payment_notice.approved', 'statement', $statementId, ['notice_id' => $n['id']]);
        });
    }

    public static function rejectNotice(int $statementId, int $noticeId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException(['reject_reason' => __('validation.required')]);
        }
        $updated = DB::update('payment_notices', [
            'status'        => 'rejected',
            'reject_reason' => mb_substr($reason, 0, 255),
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => date('Y-m-d H:i:s'),
        ], "id = ? AND statement_id = ? AND status = 'pending'", [$noticeId, $statementId]);
        if (!$updated) {
            throw new ValidationException(['notice' => __('portal.notice_not_pending')]);
        }
        Audit::log('payment_notice.rejected', 'statement', $statementId, ['notice_id' => $noticeId, 'reason' => $reason]);
    }

    public static function receiptPath(array $notice): ?string
    {
        $path = BASE_PATH . '/storage/uploads/receipts/' . basename((string) $notice['receipt_file']);
        return $notice['receipt_file'] && is_file($path) ? $path : null;
    }
}
