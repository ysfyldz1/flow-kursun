<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use Throwable;

/**
 * Takvim: duruşma, kesin süre, toplantı. Kayıt Flow'da tutulur; atanan her kişinin kendi Outlook takvimine
 * tek yönlü yansıtılır (Calendars.ReadWrite uygulama izni). Outlook hatası kaydı engellemez, katılımcıya not düşülür.
 */
final class CalendarService
{
    public const TYPES = ['hearing', 'deadline', 'meeting'];

    /** Outlook hatırlatması (dakika): duruşmaya bir gün, süreye iki gün kala. */
    private const REMINDERS = ['hearing' => 1440, 'deadline' => 2880, 'meeting' => 15];

    private const SELECT = "SELECT e.*, p.number AS project_number, p.name AS project_name, p.court, p.case_number,
                                   c.id AS client_id, c.name AS client_name,
                                   (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ')
                                      FROM event_attendees a JOIN users u ON u.id = a.user_id WHERE a.event_id = e.id) AS attendee_names,
                                   (SELECT COUNT(*) FROM event_attendees a WHERE a.event_id = e.id AND a.sync_error IS NOT NULL) AS sync_errors
                            FROM events e
                            LEFT JOIN projects p ON p.id = e.project_id
                            LEFT JOIN clients c ON c.id = p.client_id";

    public static function find(int $id): ?array
    {
        $e = DB::one(self::SELECT . ' WHERE e.id = ?', [$id]);
        if ($e) {
            $e['attendees'] = DB::all(
                'SELECT a.*, u.name FROM event_attendees a JOIN users u ON u.id = a.user_id WHERE a.event_id = ? ORDER BY u.name',
                [$id]
            );
        }
        return $e;
    }

    /** Tarih aralığındaki kayıtlar; $userId verilirse yalnızca o kişinin katıldıkları. */
    public static function range(string $from, string $to, ?int $userId = null): array
    {
        [$where, $params] = self::mine($userId);
        return DB::all(
            self::SELECT . " WHERE e.starts_at >= ? AND e.starts_at < ? $where ORDER BY e.starts_at, e.all_day DESC, e.id",
            array_merge([$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'], $params)
        );
    }

    /** Tarihi geçmiş ama tamamlanmamış süreler: kaçırılmasın diye ayrıca gösterilir. */
    public static function overdue(?int $userId = null): array
    {
        [$where, $params] = self::mine($userId);
        return DB::all(
            self::SELECT . " WHERE e.type = 'deadline' AND e.done_at IS NULL AND e.starts_at < ? $where ORDER BY e.starts_at",
            array_merge([date('Y-m-d') . ' 00:00:00'], $params)
        );
    }

    public static function forProject(int $projectId): array
    {
        return DB::all(
            self::SELECT . ' WHERE e.project_id = ? AND (e.starts_at >= ? OR (e.type = \'deadline\' AND e.done_at IS NULL)) ORDER BY e.starts_at LIMIT 20',
            [$projectId, date('Y-m-d', strtotime('-7 days'))]
        );
    }

    public static function create(array $input): int
    {
        [$data, $attendees] = self::validate($input);
        $id = DB::transaction(function () use ($data, $attendees) {
            $id = DB::insert('events', $data + ['created_by' => Auth::id()]);
            foreach ($attendees as $uid) {
                DB::insert('event_attendees', ['event_id' => $id, 'user_id' => $uid]);
            }
            Audit::log('event.created', 'event', $id, ['type' => $data['type'], 'title' => $data['title'], 'starts_at' => $data['starts_at']]);
            return $id;
        });
        self::sync($id);
        return $id;
    }

    public static function update(int $id, array $input): void
    {
        [$data, $attendees] = self::validate($input);
        $removed = DB::transaction(function () use ($id, $data, $attendees) {
            DB::update('events', $data, 'id = ?', [$id]);
            $current = DB::all('SELECT * FROM event_attendees WHERE event_id = ?', [$id]);
            $removed = array_filter($current, fn ($a) => !in_array((int) $a['user_id'], $attendees, true));
            foreach ($removed as $a) {
                DB::query('DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?', [$id, $a['user_id']]);
            }
            foreach (array_diff($attendees, array_map('intval', array_column($current, 'user_id'))) as $uid) {
                DB::insert('event_attendees', ['event_id' => $id, 'user_id' => $uid]);
            }
            Audit::log('event.updated', 'event', $id, ['title' => $data['title'], 'starts_at' => $data['starts_at']]);
            return $removed;
        });
        foreach ($removed as $a) {
            self::removeFromOutlook($a);
        }
        self::sync($id);
    }

    public static function delete(int $id): void
    {
        $attendees = DB::all('SELECT * FROM event_attendees WHERE event_id = ?', [$id]);
        $event = DB::one('SELECT type, title, starts_at FROM events WHERE id = ?', [$id]);
        DB::query('DELETE FROM events WHERE id = ?', [$id]);
        Audit::log('event.deleted', 'event', $id, $event ?? []);
        foreach ($attendees as $a) {
            self::removeFromOutlook($a);
        }
    }

    /** Süreyi tamamlandı/tamamlanmadı yapar; Outlook'ta başlık işaretlenir ve hatırlatma kapanır. */
    public static function toggleDone(int $id): void
    {
        $e = DB::one('SELECT done_at FROM events WHERE id = ?', [$id]);
        $done = $e['done_at'] === null;
        DB::update('events', ['done_at' => $done ? date('Y-m-d H:i:s') : null, 'done_by' => $done ? Auth::id() : null], 'id = ?', [$id]);
        Audit::log($done ? 'event.done' : 'event.reopened', 'event', $id);
        self::sync($id);
    }

    // --- Outlook ---

    public static function outlookEnabled(): bool
    {
        return Microsoft::isConfigured() && Settings::get('calendar.outlook') === '1';
    }

    /** Her katılımcının Outlook'undaki kopyayı oluşturur ya da günceller. */
    public static function sync(int $id): void
    {
        if (!self::outlookEnabled()) {
            return;
        }
        $e = self::find($id);
        $payload = self::payload($e);
        foreach ($e['attendees'] as $a) {
            $mailbox = self::mailbox((int) $a['user_id']);
            try {
                if ($a['ms_event_id']) {
                    try {
                        Microsoft::graph('PATCH', "/users/$mailbox/events/" . rawurlencode($a['ms_event_id']), $payload);
                        $msId = $a['ms_event_id'];
                    } catch (Throwable $ex) {
                        if ($ex->getCode() !== 404) {
                            throw $ex;
                        }
                        // Kişi Outlook'ta silmiş; yeniden oluşturulur
                        $msId = Microsoft::graph('POST', "/users/$mailbox/events", $payload)['id'];
                    }
                } else {
                    $msId = Microsoft::graph('POST', "/users/$mailbox/events", $payload)['id'];
                }
                DB::update('event_attendees', ['ms_event_id' => $msId, 'sync_error' => null], 'event_id = ? AND user_id = ?', [$id, $a['user_id']]);
            } catch (Throwable $ex) {
                DB::update('event_attendees', ['sync_error' => mb_substr($ex->getMessage(), 0, 255)], 'event_id = ? AND user_id = ?', [$id, $a['user_id']]);
            }
        }
    }

    private static function removeFromOutlook(array $attendee): void
    {
        if (!$attendee['ms_event_id'] || !Microsoft::isConfigured()) {
            return;
        }
        try {
            Microsoft::graph('DELETE', '/users/' . self::mailbox((int) $attendee['user_id']) . '/events/' . rawurlencode($attendee['ms_event_id']));
        } catch (Throwable) {
            // Zaten silinmiş olabilir; Flow kaydını etkilemez
        }
    }

    private static function mailbox(int $userId): string
    {
        $u = DB::one('SELECT email, ms_upn FROM users WHERE id = ?', [$userId]);
        return rawurlencode((string) ($u['ms_upn'] ?: $u['email']));
    }

    private static function payload(array $e): array
    {
        $tz = (string) config('app.timezone', 'Europe/Istanbul');
        $start = strtotime($e['starts_at']);
        if ($e['all_day']) {
            $end = strtotime('+1 day', strtotime(date('Y-m-d', $start)));
        } else {
            $end = $e['ends_at'] ? strtotime($e['ends_at']) : $start + 3600;
        }
        $done = $e['type'] === 'deadline' && $e['done_at'];

        $lines = [];
        if ($e['project_id']) {
            $lines[] = '<b>' . e($e['project_number'] . ' · ' . $e['project_name']) . '</b> — ' . e($e['client_name']);
        }
        if ($e['type'] === 'hearing' && ($e['court'] || $e['case_number'])) {
            $lines[] = e(trim($e['court'] . ' · ' . $e['case_number'], ' ·'));
        }
        if ($e['notes']) {
            $lines[] = nl2br(e($e['notes']));
        }
        $lines[] = '<a href="' . e(rtrim((string) config('app.url'), '/') . '/calendar/' . $e['id']) . '">Flow</a>';

        return [
            'subject'  => ($done ? '✓ ' : '') . __('cal.prefix_' . $e['type']) . ': ' . $e['title'],
            'body'     => ['contentType' => 'HTML', 'content' => implode('<br>', $lines)],
            'start'    => ['dateTime' => date('Y-m-d\TH:i:s', $start), 'timeZone' => $tz],
            'end'      => ['dateTime' => date('Y-m-d\TH:i:s', $end), 'timeZone' => $tz],
            'isAllDay' => (bool) $e['all_day'],
            'location' => ['displayName' => (string) $e['location']],
            'showAs'   => $e['type'] === 'deadline' ? 'free' : 'busy',
            'isReminderOn' => !$done,
            'reminderMinutesBeforeStart' => self::REMINDERS[$e['type']],
        ];
    }

    // --- Doğrulama ---

    /** @return array{0: array, 1: int[]} */
    private static function validate(array $in): array
    {
        $type = (string) ($in['type'] ?? '');
        $title = trim((string) ($in['title'] ?? ''));
        $projectId = (int) ($in['project_id'] ?? 0) ?: null;
        $date = (string) ($in['date'] ?? '');
        $startTime = (string) ($in['start_time'] ?? '');
        $endTime = (string) ($in['end_time'] ?? '');
        $attendees = array_values(array_unique(array_map('intval', (array) ($in['attendees'] ?? []))));

        $errors = [];
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'] = __('validation.required');
        }
        $project = $projectId ? DB::one('SELECT id, number, name FROM projects WHERE id = ?', [$projectId]) : null;
        if ($projectId && !$project) {
            $errors['project_id'] = __('validation.required');
        }
        if ($type === 'hearing' && !$project) {
            $errors['project_id'] = __('cal.hearing_needs_project');
        }
        // Duruşmada başlık boşsa projeden üretilir
        if ($title === '' && $type === 'hearing' && $project) {
            $title = $project['name'];
        }
        if ($title === '') {
            $errors['title'] = __('validation.required');
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors['date'] = __('validation.date');
        }
        $timeOk = fn (string $t) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
        // Süre saatsiz olabilir (gün sonu); duruşma ve toplantı saatli
        $allDay = $type === 'deadline' && $startTime === '';
        if (!$allDay && !$timeOk($startTime)) {
            $errors['start_time'] = __('validation.required');
        }
        if ($endTime !== '' && (!$timeOk($endTime) || ($timeOk($startTime) && $endTime <= $startTime))) {
            $errors['end_time'] = __('cal.end_after_start');
        }
        if (!$attendees) {
            $errors['attendees'] = __('cal.attendees_required');
        } else {
            $valid = array_map('intval', array_column(DB::all(
                'SELECT id FROM users WHERE is_active = 1 AND id IN (' . implode(',', array_fill(0, count($attendees), '?')) . ')',
                $attendees
            ), 'id'));
            $attendees = array_values(array_intersect($attendees, $valid));
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return [[
            'type'       => $type,
            'title'      => mb_substr($title, 0, 255),
            'project_id' => $projectId,
            'starts_at'  => $date . ' ' . ($allDay ? '00:00' : $startTime) . ':00',
            'ends_at'    => !$allDay && $endTime !== '' ? "$date $endTime:00" : null,
            'all_day'    => (int) $allDay,
            'location'   => trim((string) ($in['location'] ?? '')) ?: null,
            'notes'      => trim((string) ($in['notes'] ?? '')) ?: null,
        ], $attendees];
    }

    private static function mine(?int $userId): array
    {
        return $userId
            ? ['AND EXISTS (SELECT 1 FROM event_attendees a WHERE a.event_id = e.id AND a.user_id = ?)', [$userId]]
            : ['', []];
    }
}
