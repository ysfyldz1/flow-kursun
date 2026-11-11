<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use DateTime;

/**
 * Kişinin ilgilenmesi gereken işler; panodaki "Bekleyenler" kartı ve özet e-postası aynı listeyi kullanır.
 * Her kalem yetkiye bağlıdır. Oturumdaki kullanıcı için çalışır; cron'da Auth::actAs ile kişi seçilir.
 *
 * Kalem: ['key', 'tone' (red|amber|blue), 'count', 'url', 'items' => [['title', 'meta', 'url']]]
 */
final class PendingService
{
    /** Kesin süre bu kadar iş günü kala bekleyenlere girer */
    private const DEADLINE_DAYS = 3;

    public static function forCurrentUser(): array
    {
        $uid = (int) Auth::id();
        $today = date('Y-m-d');
        $out = [];

        if (can('calendar.view')) {
            // Gecikmiş + yaklaşan kesin süreler (tamamlanmamış), bugün ve yarınki duruşmalar
            $limit = self::addWorkdays($today, self::DEADLINE_DAYS);
            $deadlines = array_merge(
                CalendarService::overdue($uid),
                array_filter(CalendarService::range($today, $limit, $uid), fn ($e) => $e['type'] === 'deadline' && !$e['done_at'])
            );
            $out[] = self::group('deadlines', 'red', $deadlines, fn ($e) => [
                'title' => $e['title'],
                'meta'  => self::eventMeta($e),
                'url'   => '/calendar/' . $e['id'],
            ]);
            $hearings = array_filter(
                CalendarService::range($today, self::addWorkdays($today, 1), $uid),
                fn ($e) => $e['type'] === 'hearing'
            );
            $out[] = self::group('hearings', 'amber', $hearings, fn ($e) => [
                'title' => $e['title'],
                'meta'  => self::eventMeta($e, true),
                'url'   => '/calendar/' . $e['id'],
            ]);
        }

        if (can('tasks.own') || can('tasks.all')) {
            $tasks = array_filter(TaskService::list('mine'), fn ($t) => $t['due_date'] && $t['due_date'] <= $today);
            $out[] = self::group('tasks', 'red', $tasks, fn ($t) => [
                'title' => $t['title'],
                'meta'  => ($t['due_date'] < $today ? __('pending.overdue_since', ['date' => format_date($t['due_date'])]) : __('pending.today'))
                           . ($t['project_name'] ? ' · ' . $t['project_name'] : ''),
                'url'   => '/tasks/' . $t['id'],
            ]);
        }

        if (can('leaves.approve') || can('leaves.manage') || can('leaves.own')) {
            $out[] = self::group('leaves', 'amber', LeaveService::list('approve'), fn ($l) => [
                'title' => $l['user_name'],
                'meta'  => __('leaves.type_' . $l['type']) . ' · ' . format_date($l['start_date'])
                           . ($l['end_date'] !== $l['start_date'] ? ' – ' . format_date($l['end_date']) : ''),
                'url'   => '/leaves/' . $l['id'],
            ]);
        }

        if (can('portal.manage')) {
            $notices = DB::all(
                "SELECT n.id, n.statement_id, n.amount, s.currency, s.number, c.name AS client_name
                 FROM payment_notices n JOIN statements s ON s.id = n.statement_id JOIN clients c ON c.id = s.client_id
                 WHERE n.status = 'pending' ORDER BY n.id"
            );
            $out[] = self::group('notices', 'blue', $notices, fn ($n) => [
                'title' => $n['client_name'],
                'meta'  => $n['number'] . ' · ' . format_money((float) $n['amount'], $n['currency']),
                'url'   => '/billing/' . $n['statement_id'],
            ]);
        }

        if (can('payments.manage')) {
            $overdue = DB::all(
                "SELECT s.id, s.number, s.currency, s.total, s.paid_amount, s.due_date, c.name AS client_name
                 FROM statements s JOIN clients c ON c.id = s.client_id
                 WHERE s.status = 'issued' AND s.paid_amount < s.total AND s.due_date < ? ORDER BY s.due_date",
                [$today]
            );
            $out[] = self::group('overdue_statements', 'amber', $overdue, fn ($s) => [
                'title' => $s['client_name'],
                'meta'  => $s['number'] . ' · ' . format_money((float) $s['total'] - (float) $s['paid_amount'], $s['currency'])
                           . ' · ' . __('pending.due', ['date' => format_date($s['due_date'])]),
                'url'   => '/billing/' . $s['id'],
            ]);
        }

        if (can('time.own') && ($day = self::missingTimeDay($uid))) {
            $url = '/time?week=' . $day . '&date=' . $day;
            $out[] = ['key' => 'time', 'tone' => 'blue', 'count' => 1, 'url' => $url, 'items' => [[
                'title' => __('pending.time_missing', ['date' => format_date($day)]),
                'meta'  => '',
                'url'   => $url,
            ]]];
        }

        return array_values(array_filter($out, fn ($g) => $g['count'] > 0));
    }

    /** Kesin süre uyarısı alacaklar: son güne 1 iş günü kala ve son gün. [event, user, kind] */
    public static function deadlineAlerts(string $today): array
    {
        $tomorrow = self::addWorkdays($today, 1);
        return DB::all(
            "SELECT e.id AS event_id, e.title, e.starts_at, e.notes, p.number AS project_number, p.name AS project_name,
                    p.court, p.case_number, u.id AS user_id, u.name, u.email, u.locale,
                    IF(DATE(e.starts_at) = ?, 'today', 'tomorrow') AS kind
             FROM events e
             JOIN event_attendees a ON a.event_id = e.id
             JOIN users u ON u.id = a.user_id AND u.is_active = 1
             LEFT JOIN projects p ON p.id = e.project_id
             WHERE e.type = 'deadline' AND e.done_at IS NULL AND DATE(e.starts_at) IN (?, ?)
             ORDER BY u.id, e.starts_at",
            [$today, $today, $tomorrow]
        );
    }

    /** Son iş gününe zaman girilmemişse o gün (izinliyse, hiç kaydı yoksa ya da hafta başıysa sorulmaz). */
    public static function missingTimeDay(int $userId): ?string
    {
        $day = self::previousWorkday(date('Y-m-d'));
        $user = DB::one('SELECT created_at FROM users WHERE id = ?', [$userId]);
        if (!$user || substr((string) $user['created_at'], 0, 10) >= $day || LeaveService::isOnLeave($userId, $day)) {
            return null;
        }
        // Hiç zaman girmeyen (ör. sekreter, muhasebe) her gün uyarılmasın
        if (!DB::value('SELECT 1 FROM time_entries WHERE user_id = ? AND work_date >= ? - INTERVAL 30 DAY LIMIT 1', [$userId, $day])) {
            return null;
        }
        return DB::value('SELECT 1 FROM time_entries WHERE user_id = ? AND work_date = ? LIMIT 1', [$userId, $day]) ? null : $day;
    }

    /** İki tarih arasındaki iş günü sayısı (başlangıç hariç, bitiş dahil). */
    public static function workdaysBetween(string $from, string $to): float
    {
        return $to <= $from ? 0.0 : LeaveService::countDays(date('Y-m-d', strtotime($from . ' +1 day')), $to);
    }

    public static function addWorkdays(string $date, int $days): string
    {
        $d = new DateTime($date);
        $holidays = LeaveService::holidayMap($date, date('Y-m-d', strtotime($date . ' +' . ($days * 3 + 10) . ' days')));
        while ($days > 0) {
            $d->modify('+1 day');
            if ((int) $d->format('N') < 6 && ($holidays[$d->format('Y-m-d')] ?? null) !== false) {
                $days--;
            }
        }
        return $d->format('Y-m-d');
    }

    public static function previousWorkday(string $date): string
    {
        $d = new DateTime($date);
        $holidays = LeaveService::holidayMap(date('Y-m-d', strtotime($date . ' -15 days')), $date);
        do {
            $d->modify('-1 day');
        } while ((int) $d->format('N') >= 6 || ($holidays[$d->format('Y-m-d')] ?? null) === false);
        return $d->format('Y-m-d');
    }

    private static function group(string $key, string $tone, array $rows, callable $map): array
    {
        $rows = array_values($rows);
        return [
            'key'   => $key,
            'tone'  => $tone,
            'count' => count($rows),
            'url'   => self::listUrl($key),
            'items' => array_map($map, $rows),
        ];
    }

    private static function listUrl(string $key): string
    {
        return [
            'deadlines' => '/calendar', 'hearings' => '/calendar', 'tasks' => '/tasks', 'leaves' => '/leaves',
            'notices' => '/billing', 'overdue_statements' => '/billing?status=open', 'time' => '/time',
        ][$key];
    }

    private static function eventMeta(array $e, bool $withTime = false): string
    {
        $date = substr($e['starts_at'], 0, 10);
        $when = $date < date('Y-m-d')
            ? __('pending.overdue_since', ['date' => format_date($date)])
            : ($date === date('Y-m-d') ? __('pending.today')
                : ($date === date('Y-m-d', strtotime('+1 day')) ? __('pending.tomorrow') : format_date($date)));
        if ($withTime && !$e['all_day']) {
            $when .= ' ' . substr($e['starts_at'], 11, 5);
        }
        $parts = [$when];
        if ($e['project_name']) {
            $parts[] = $e['project_name'];
        }
        if (!empty($e['court'])) {
            $parts[] = trim($e['court'] . ' ' . ($e['case_number'] ?? ''));
        }
        return implode(' · ', $parts);
    }
}
