<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use DateTime;

/**
 * İzin talepleri. Gün sayımı iş günüyle (hafta sonu ve resmî tatil düşülür, arife yarım gün).
 * Yıllık izin hakkı işe girişten itibaren kıdeme göre birikir ve devreder (İş K. md. 53-54);
 * mazeret izni ayrı sayılır, haktan düşmez. Onay: kişiye atanmış onaycılardan biri,
 * atanmamışsa leaves.approve yetkisi olanlar; leaves.manage herkesinkini yönetir.
 */
final class LeaveService
{
    public const TYPES = ['annual', 'excuse', 'sick', 'unpaid'];
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    private const SELECT = 'SELECT l.*, u.name AS user_name, u.title AS user_title, r.name AS reviewer_name
                            FROM leave_requests l
                            JOIN users u ON u.id = l.user_id
                            LEFT JOIN users r ON r.id = l.reviewed_by';

    public static function find(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE l.id = ?', [$id]);
    }

    /**
     * Liste. scope: mine (kendi talepleri), approve (onaylayabileceği bekleyenler), all (leaves.manage).
     */
    public static function list(string $scope, ?int $year = null): array
    {
        $where = [];
        $params = [];
        if ($scope === 'approve') {
            return array_values(array_filter(
                DB::all(self::SELECT . " WHERE l.status = 'pending' ORDER BY l.start_date"),
                fn ($l) => self::canReview($l)
            ));
        }
        if ($scope === 'all' && Auth::can('leaves.manage')) {
            // hepsi
        } else {
            $where[] = 'l.user_id = ?';
            $params[] = Auth::id();
        }
        if ($year) {
            $where[] = 'YEAR(l.start_date) = ?';
            $params[] = $year;
        }
        return DB::all(
            self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.start_date DESC, l.id DESC',
            $params
        );
    }

    /** Onaylanmış izinler; takvim ve "kim izinde" için. $userId verilirse yalnız o kişi. */
    public static function approvedInRange(string $from, string $to, ?int $userId = null): array
    {
        $sql = self::SELECT . " WHERE l.status = 'approved' AND l.start_date <= ? AND l.end_date >= ?";
        $params = [$to, $from];
        if ($userId) {
            $sql .= ' AND l.user_id = ?';
            $params[] = $userId;
        }
        return DB::all($sql . ' ORDER BY u.name', $params);
    }

    public static function isOnLeave(int $userId, string $date): bool
    {
        return (bool) DB::value(
            "SELECT 1 FROM leave_requests WHERE user_id = ? AND status = 'approved' AND ? BETWEEN start_date AND end_date",
            [$userId, $date]
        );
    }

    public static function pendingCount(): int
    {
        return count(self::list('approve'));
    }

    // --- Gün sayımı -------------------------------------------------------------------------

    /** Tarih aralığındaki tatiller: [Y-m-d => yarım gün mü]. */
    public static function holidayMap(string $from, string $to): array
    {
        $map = [];
        foreach (DB::all('SELECT date, half_day FROM holidays WHERE date BETWEEN ? AND ?', [$from, $to]) as $h) {
            $map[$h['date']] = (bool) $h['half_day'];
        }
        return $map;
    }

    /** İzinde düşülecek iş günü sayısı. Yarım gün işaretleri yalnız iş gününe denk geliyorsa etkiler. */
    public static function countDays(string $start, string $end, bool $halfStart = false, bool $halfEnd = false): float
    {
        $holidays = self::holidayMap($start, $end);
        $total = 0.0;
        for ($d = new DateTime($start); $d->format('Y-m-d') <= $end; $d->modify('+1 day')) {
            $day = $d->format('Y-m-d');
            if ((int) $d->format('N') >= 6 || ($holidays[$day] ?? null) === false) {
                continue;
            }
            $value = isset($holidays[$day]) ? 0.5 : 1.0;   // arife: yalnız sabahı iş günü
            if ($start === $end) {
                $value = ($halfStart || $halfEnd) ? min($value, 0.5) : $value;
            } elseif (($day === $start && $halfStart) || ($day === $end && $halfEnd)) {
                $value = min($value, 0.5);
            }
            $total += $value;
        }
        return $total;
    }

    // --- Hak hesabı -------------------------------------------------------------------------

    /** Kıdem yılına göre yıllık hak: 1-5 yıl 14, 5-15 yıl 20, 15+ yıl 26 gün (İş K. md. 53). */
    public static function annualRate(int $completedYears): int
    {
        return match (true) {
            $completedYears < 1  => 0,
            $completedYears < 5  => 14,
            $completedYears < 15 => 20,
            default              => 26,
        };
    }

    /** İşe girişten bugüne biriken toplam hak; tamamlanan her kıdem yılı için o yılın oranı eklenir. */
    public static function accrued(?string $hireDate, ?string $asOf = null): float
    {
        if (!$hireDate) {
            return 0;
        }
        $years = (new DateTime($hireDate))->diff(new DateTime($asOf ?? date('Y-m-d')));
        if ($years->invert) {
            return 0;
        }
        $total = 0;
        for ($y = 1; $y <= $years->y; $y++) {
            $total += self::annualRate($y);
        }
        return $total;
    }

    /** Hak durumu: toplam hak, kullanılan, kalan; bu yıl kullanılan mazeret ayrıca. */
    public static function balance(int $userId): array
    {
        $user = DB::one('SELECT hire_date, leave_entitlement FROM users WHERE id = ?', [$userId]);
        $entitlement = $user['leave_entitlement'] !== null ? (float) $user['leave_entitlement'] : self::accrued($user['hire_date']);
        // Yıllık izin + raporsuz hastalık izni haktan düşer; mazeret ve ücretsiz düşmez
        $used = (float) DB::value(
            "SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE user_id = ? AND status = 'approved'
               AND (type = 'annual' OR (type = 'sick' AND report_file IS NULL))",
            [$userId]
        );
        $excuse = (float) DB::value(
            "SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE user_id = ? AND status = 'approved'
               AND type = 'excuse' AND YEAR(start_date) = YEAR(CURDATE())",
            [$userId]
        );
        return [
            'hire_date'   => $user['hire_date'],
            'manual'      => $user['leave_entitlement'] !== null,
            'entitlement' => $entitlement,
            'used'        => $used,
            'remaining'   => $entitlement - $used,
            'excuse_year' => $excuse,
        ];
    }

    public static function balances(): array
    {
        $rows = DB::all('SELECT id, name, title, hire_date FROM users WHERE is_active = 1 ORDER BY name');
        foreach ($rows as &$r) {
            $r += self::balance((int) $r['id']);
        }
        return $rows;
    }

    // --- Onaycılar --------------------------------------------------------------------------

    /** Kişiye atanmış onaycılar (kimlikler). */
    public static function assignedApprovers(int $userId): array
    {
        return array_map('intval', array_column(DB::all('SELECT approver_id FROM leave_approvers WHERE user_id = ?', [$userId]), 'approver_id'));
    }

    public static function setApprovers(int $userId, array $approverIds): void
    {
        $ids = array_unique(array_filter(array_map('intval', $approverIds), fn ($id) => $id && $id !== $userId));
        DB::transaction(function () use ($userId, $ids) {
            DB::query('DELETE FROM leave_approvers WHERE user_id = ?', [$userId]);
            foreach ($ids as $id) {
                DB::insert('leave_approvers', ['user_id' => $userId, 'approver_id' => $id]);
            }
        });
    }

    /** Talebi kimler onaylayabilir: atanmış onaycılar, yoksa leaves.approve yetkililer (kendisi hariç). */
    public static function approversFor(int $userId): array
    {
        $assigned = self::assignedApprovers($userId);
        if ($assigned) {
            return DB::all(
                'SELECT id, name, email, locale FROM users WHERE is_active = 1 AND id IN (' . implode(',', $assigned) . ')'
            );
        }
        return DB::all(
            "SELECT DISTINCT u.id, u.name, u.email, u.locale FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id AND rp.permission IN ('leaves.approve', '*')
             WHERE u.is_active = 1 AND u.id <> ?",
            [$userId]
        );
    }

    public static function canReview(array $leave): bool
    {
        if ((int) $leave['user_id'] === Auth::id()) {
            return false;   // kendi talebini onaylayamaz
        }
        return Auth::can('leaves.manage')
            || in_array(Auth::id(), array_map('intval', array_column(self::approversFor((int) $leave['user_id']), 'id')), true);
    }

    public static function canView(array $leave): bool
    {
        return (int) $leave['user_id'] === Auth::id() || Auth::can('leaves.manage') || self::canReview($leave);
    }

    // --- Talep akışı ------------------------------------------------------------------------

    public static function create(array $in, ?array $report, string $channel = 'web'): int
    {
        $data = self::validate($in);
        $data['report_file'] = self::storeFile($report, 'report');
        if ($data['report_file'] && $data['type'] !== 'sick') {
            self::deleteFile($data['report_file']);
            $data['report_file'] = null;
        }
        $id = DB::insert('leave_requests', $data);
        Audit::log('leave.requested', 'leave', $id, ['type' => $data['type'], 'start' => $data['start_date'], 'end' => $data['end_date'], 'days' => $data['days']], $channel);

        $leave = self::find($id);
        foreach (self::approversFor((int) $leave['user_id']) as $a) {
            Mailer::sendView($a['email'], $a['locale'], 'leave_request', self::mailData($leave, $a['name']));
        }
        return $id;
    }

    public static function review(int $id, string $decision, string $note, string $channel = 'web'): void
    {
        $leave = self::find($id);
        if (!$leave || $leave['status'] !== 'pending' || !self::canReview($leave)) {
            throw new ValidationException(['leave' => __('leaves.not_reviewable')]);
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new ValidationException(['decision' => __('validation.required')]);
        }
        $note = trim($note);
        if ($decision === 'rejected' && $note === '') {
            throw new ValidationException(['review_note' => __('leaves.reason_required')]);
        }
        DB::update('leave_requests', [
            'status'      => $decision,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note ?: null,
        ], 'id = ?', [$id]);
        Audit::log('leave.' . $decision, 'leave', $id, ['note' => $note], $channel);
        self::notifyOwner($id);
    }

    /** Sahibi bekleyen talebini geri çeker; onaylanmış izni onaycı/yönetici gerekçeyle iptal eder. */
    public static function cancel(int $id, string $note, string $channel = 'web'): void
    {
        $leave = self::find($id);
        $own = $leave && (int) $leave['user_id'] === Auth::id();
        $allowed = $leave && (
            ($leave['status'] === 'pending' && ($own || self::canReview($leave)))
            || ($leave['status'] === 'approved' && self::canReview($leave))
        );
        if (!$allowed) {
            throw new ValidationException(['leave' => __('leaves.not_cancellable')]);
        }
        $note = trim($note);
        if (!$own && $note === '') {
            throw new ValidationException(['review_note' => __('leaves.reason_required')]);
        }
        DB::update('leave_requests', [
            'status'      => 'cancelled',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note ?: null,
        ], 'id = ?', [$id]);
        Audit::log('leave.cancelled', 'leave', $id, ['note' => $note, 'was' => $leave['status']], $channel);
        if (!$own) {
            self::notifyOwner($id);
        }
    }

    public static function uploadSigned(int $id, ?array $file, string $channel = 'web'): void
    {
        $leave = self::find($id);
        if (!$leave || $leave['status'] !== 'approved' || ((int) $leave['user_id'] !== Auth::id() && !self::canReview($leave))) {
            throw new ValidationException(['signed' => __('leaves.not_signable')]);
        }
        $name = self::storeFile($file, 'signed') ?? throw new ValidationException(['signed' => __('validation.required')]);
        if ($leave['signed_file']) {
            self::deleteFile($leave['signed_file']);
        }
        DB::update('leave_requests', ['signed_file' => $name, 'signed_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        Audit::log('leave.signed_uploaded', 'leave', $id, [], $channel);
    }

    public static function filePath(?string $name): ?string
    {
        $path = BASE_PATH . '/storage/uploads/leaves/' . basename((string) $name);
        return $name && is_file($path) ? $path : null;
    }

    // --- Tatiller ---------------------------------------------------------------------------

    public static function holidays(int $year): array
    {
        return DB::all('SELECT * FROM holidays WHERE YEAR(date) = ? ORDER BY date', [$year]);
    }

    public static function saveHoliday(array $in): void
    {
        $date = (string) ($in['date'] ?? '');
        $d = DateTime::createFromFormat('Y-m-d', $date);
        $name = trim((string) ($in['name'] ?? ''));
        $errors = [];
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors['date'] = __('validation.date');
        }
        if ($name === '') {
            $errors['name'] = __('validation.required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        DB::query(
            'INSERT INTO holidays (date, name, half_day) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), half_day = VALUES(half_day)',
            [$date, $name, (int) !empty($in['half_day'])]
        );
    }

    public static function deleteHoliday(string $date): void
    {
        DB::query('DELETE FROM holidays WHERE date = ?', [$date]);
    }

    /** Tarihi sabit ulusal tatiller; dinî bayramlar her yıl değiştiği için elle girilir. */
    public static function addFixedHolidays(int $year): int
    {
        $fixed = [
            '01-01' => ['holidays.new_year', 0],
            '04-23' => ['holidays.apr23', 0],
            '05-01' => ['holidays.may1', 0],
            '05-19' => ['holidays.may19', 0],
            '07-15' => ['holidays.jul15', 0],
            '08-30' => ['holidays.aug30', 0],
            '10-28' => ['holidays.oct28', 1],
            '10-29' => ['holidays.oct29', 0],
        ];
        $added = 0;
        foreach ($fixed as $md => [$key, $half]) {
            $added += DB::query(
                'INSERT IGNORE INTO holidays (date, name, half_day) VALUES (?, ?, ?)',
                ["$year-$md", __($key), $half]
            )->rowCount();
        }
        return $added;
    }

    // --- Yardımcılar ------------------------------------------------------------------------

    private static function validate(array $in): array
    {
        $errors = [];
        $userId = (int) ($in['user_id'] ?? 0) ?: Auth::id();
        if ($userId !== Auth::id() && !Auth::can('leaves.manage')) {
            $userId = Auth::id();
        }
        $type = (string) ($in['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'] = __('validation.required');
        }
        $start = (string) ($in['start_date'] ?? '');
        $end = (string) ($in['end_date'] ?? '') ?: $start;
        foreach (['start_date' => $start, 'end_date' => $end] as $k => $v) {
            $d = DateTime::createFromFormat('Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                $errors[$k] = __('validation.date');
            }
        }
        if (!$errors && $end < $start) {
            $errors['end_date'] = __('leaves.end_before_start');
        }
        $halfStart = !empty($in['half_start']);
        $halfEnd = !empty($in['half_end']) && $end !== $start;
        $days = 0.0;
        if (!$errors) {
            $days = self::countDays($start, $end, $halfStart, $halfEnd);
            if ($days <= 0) {
                $errors['end_date'] = __('leaves.no_working_days');
            } elseif (DB::value(
                "SELECT 1 FROM leave_requests WHERE user_id = ? AND status IN ('pending', 'approved') AND start_date <= ? AND end_date >= ?",
                [$userId, $end, $start]
            )) {
                $errors['start_date'] = __('leaves.overlap');
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return [
            'user_id'    => $userId,
            'type'       => $type,
            'start_date' => $start,
            'end_date'   => $end,
            'half_start' => (int) $halfStart,
            'half_end'   => (int) $halfEnd,
            'days'       => $days,
            'reason'     => trim((string) ($in['reason'] ?? '')) ?: null,
        ];
    }

    private static function storeFile(?array $file, string $prefix): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $ext = $file['error'] === UPLOAD_ERR_OK
            ? (['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][mime_content_type($file['tmp_name'])] ?? null)
            : null;
        if (!$ext) {
            throw new ValidationException([$prefix => __('portal.receipt_type')]);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new ValidationException([$prefix => __('billing.invoice_size')]);
        }
        $dir = BASE_PATH . '/storage/uploads/leaves';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = "$prefix-" . date('Ymd') . '-' . bin2hex(random_bytes(8)) . ".$ext";
        move_uploaded_file($file['tmp_name'], "$dir/$name");
        return $name;
    }

    private static function deleteFile(string $name): void
    {
        if ($path = self::filePath($name)) {
            unlink($path);
        }
    }

    private static function notifyOwner(int $id): void
    {
        $leave = self::find($id);
        $owner = DB::one('SELECT name, email, locale FROM users WHERE id = ?', [$leave['user_id']]);
        Mailer::sendView($owner['email'], $owner['locale'], 'leave_' . $leave['status'], self::mailData($leave, $owner['name']));
    }

    private static function mailData(array $leave, string $recipient): array
    {
        return [
            'name'     => $recipient,
            'employee' => $leave['user_name'],
            'type'     => $leave['type'],
            'start'    => $leave['start_date'],
            'end'      => $leave['end_date'],
            'days'     => (float) $leave['days'],
            'reason'   => $leave['reason'],
            'status'   => $leave['status'],
            'note'     => $leave['review_note'],
            'reviewer' => $leave['reviewer_name'],
            'url'      => rtrim((string) config('app.url'), '/') . '/leaves/' . $leave['id'],
        ];
    }
}
