<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Lang;

/**
 * Günlük hatırlatma e-postaları (bin/cron.php çağırır):
 *  - Kesin süre: son güne 1 iş günü kala ve son gün, katılımcılara; girişten bağımsız.
 *  - Özet: N iş günüdür (reminders.digest_days, 0 = kapalı) Flow'a girmeyen ve bekleyeni olan kişiye;
 *    girmemeye devam ederse her N iş gününde bir yinelenir.
 * Gönderilen her e-posta reminder_log'a yazılır; cron aynı gün iki kez çalışsa da tekrar gitmez.
 */
final class ReminderService
{
    public static function run(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $result = ['deadline' => 0, 'digest' => 0];
        if (!Mailer::delivers()) {
            return $result;
        }

        foreach (PendingService::deadlineAlerts($today) as $r) {
            $kind = 'deadline_' . $r['kind'];
            if (self::sent($kind, (int) $r['event_id'], (int) $r['user_id'], $today)) {
                continue;
            }
            $ok = Mailer::sendView($r['email'], $r['locale'], 'deadline_alert', [
                'name'    => $r['name'],
                'title'   => $r['title'],
                'kind'    => $r['kind'],
                'date'    => substr($r['starts_at'], 0, 10),
                'project' => $r['project_name'] ? $r['project_number'] . ' · ' . $r['project_name'] : null,
                'court'   => trim(($r['court'] ?? '') . ' ' . ($r['case_number'] ?? '')) ?: null,
                'url'     => self::url('/calendar/' . $r['event_id']),
            ]);
            if ($ok) {
                self::log($kind, (int) $r['event_id'], (int) $r['user_id'], $today);
                $result['deadline']++;
            }
        }

        $days = (int) Settings::get('reminders.digest_days', 3);
        $isWorkday = PendingService::workdaysBetween(date('Y-m-d', strtotime($today . ' -1 day')), $today) > 0;
        if ($days > 0 && $isWorkday) {
            foreach (DB::all('SELECT * FROM users WHERE is_active = 1') as $u) {
                if (self::digestFor($u, $today, $days)) {
                    $result['digest']++;
                }
            }
        }
        return $result;
    }

    private static function digestFor(array $u, string $today, int $days): bool
    {
        $seen = substr((string) ($u['last_seen_at'] ?: $u['last_login_at'] ?: $u['created_at']), 0, 10);
        if (PendingService::workdaysBetween($seen, $today) < $days) {
            return false;
        }
        $last = DB::value("SELECT MAX(sent_on) FROM reminder_log WHERE kind = 'digest' AND user_id = ?", [$u['id']]);
        if ($last && $last >= $seen && PendingService::workdaysBetween($last, $today) < $days) {
            return false;
        }

        $previous = Lang::locale();
        Lang::set($u['locale'] ?: config('app.locale'));
        Auth::actAs($u);
        try {
            $groups = PendingService::forCurrentUser();
        } finally {
            Auth::actAs(null);
            Lang::set($previous);
        }
        if (!$groups) {
            return false;
        }
        foreach ($groups as &$g) {
            $g['url'] = self::url($g['url']);
            foreach ($g['items'] as &$i) {
                $i['url'] = self::url($i['url']);
            }
        }
        unset($g, $i);

        $ok = Mailer::sendView($u['email'], $u['locale'] ?: config('app.locale'), 'digest', [
            'name'   => $u['name'],
            'days'   => (int) PendingService::workdaysBetween($seen, $today),
            'groups' => $groups,
            'url'    => self::url('/'),
        ]);
        if ($ok) {
            self::log('digest', 0, (int) $u['id'], $today);
        }
        return $ok;
    }

    private static function sent(string $kind, int $refId, int $userId, string $day): bool
    {
        return (bool) DB::value(
            'SELECT 1 FROM reminder_log WHERE kind = ? AND ref_id = ? AND user_id = ? AND sent_on = ?',
            [$kind, $refId, $userId, $day]
        );
    }

    private static function log(string $kind, int $refId, int $userId, string $day): void
    {
        DB::query(
            'INSERT IGNORE INTO reminder_log (kind, ref_id, user_id, sent_on) VALUES (?, ?, ?, ?)',
            [$kind, $refId, $userId, $day]
        );
    }

    private static function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . $path;
    }
}
