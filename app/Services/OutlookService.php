<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Outlook eklentisi. Panel e-postanın kendisini göndermez, yalnız kimliğini; Flow e-postayı kişinin kendi
 * yetkisiyle (bağlı Microsoft hesabı) Graph'tan çeker ve ProjectEmailService ile projeye kaydeder.
 * Yazarken "gönderince kaydet": e-postaya x-flow-marker başlığı konur, saatlik iş Gönderilmiş Öğeler'de bulur.
 */
final class OutlookService
{
    public const MARKER_HEADER = 'x-flow-marker';

    // --- Panel anahtarı -------------------------------------------------------------------------------

    public static function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        DB::insert('addin_tokens', ['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
        Audit::log('outlook.connected', 'user', $userId, [], 'web');
        return $token;
    }

    /** Authorization: Bearer … → etkin kullanıcı; yoksa null. */
    public static function userFromToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = DB::one(
            'SELECT t.id AS token_id, t.last_used_at, u.* FROM addin_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND u.is_active = 1',
            [hash('sha256', $token)]
        );
        if (!$row) {
            return null;
        }
        if (!$row['last_used_at'] || strtotime($row['last_used_at']) < time() - 3600) {
            DB::update('addin_tokens', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['token_id']]);
        }
        unset($row['token_id'], $row['last_used_at']);
        return $row;
    }

    public static function revokeToken(string $token): void
    {
        DB::query('DELETE FROM addin_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    // --- Proje önerisi --------------------------------------------------------------------------------

    /**
     * Panel açılınca: bu e-posta (ya da aynı yazışma) daha önce kaydedildiyse o projeler; gönderen/alıcılar
     * bir müvekkilin iletişim kişisiyse o müvekkilin açık projeleri; ayrıca kişinin son çalıştığı projeler.
     */
    public static function context(array $in): array
    {
        $imid = trim((string) ($in['internetMessageId'] ?? ''));
        $conversation = trim((string) ($in['conversationId'] ?? ''));
        $addresses = array_values(array_unique(array_filter(array_map(
            fn ($a) => mb_strtolower(trim((string) $a)),
            (array) ($in['addresses'] ?? [])
        ), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
        [$visible, $vp] = Access::projectWhere();

        $saved = $imid === '' ? [] : DB::all(
            "SELECT DISTINCT p.id FROM project_emails e JOIN projects p ON p.id = e.project_id
             WHERE e.internet_message_id = ? AND $visible",
            array_merge([$imid], $vp)
        );
        $thread = $conversation === '' ? [] : DB::all(
            "SELECT p.id, MAX(e.created_at) AS last FROM project_emails e JOIN projects p ON p.id = e.project_id
             WHERE e.conversation_id = ? AND p.status = 'open' AND $visible GROUP BY p.id ORDER BY last DESC",
            array_merge([$conversation], $vp)
        );
        $byContact = [];
        if ($addresses) {
            $in = implode(',', array_fill(0, count($addresses), '?'));
            // Önce bu adreslerle daha önce kaydedilmiş yazışmaların projeleri (en güçlü işaret),
            // sonra adresi müvekkilin kendisi, iletişim kişisi ya da portal kullanıcısı olan müvekkillerin projeleri
            $byContact = array_merge(
                DB::all(
                    "SELECT p.id, MAX(e.created_at) AS last FROM project_emails e JOIN projects p ON p.id = e.project_id
                     WHERE p.status = 'open' AND $visible AND LOWER(e.from_email) IN ($in)
                     GROUP BY p.id ORDER BY last DESC LIMIT 6",
                    array_merge($vp, $addresses)
                ),
                DB::all(
                    "SELECT DISTINCT p.id FROM projects p JOIN clients c ON c.id = p.client_id
                     WHERE p.status = 'open' AND $visible
                       AND (LOWER(c.email) IN ($in)
                            OR EXISTS (SELECT 1 FROM client_contacts cc WHERE cc.client_id = c.id AND LOWER(cc.email) IN ($in))
                            OR EXISTS (SELECT 1 FROM portal_users pu WHERE pu.client_id = c.id AND LOWER(pu.email) IN ($in)))
                     ORDER BY p.id DESC LIMIT 12",
                    array_merge($vp, $addresses, $addresses, $addresses)
                )
            );
        }
        $recent = DB::all(
            "SELECT t.project_id AS id, MAX(t.work_date) AS last FROM time_entries t JOIN projects p ON p.id = t.project_id
             WHERE t.user_id = ? AND p.status = 'open' AND $visible GROUP BY t.project_id ORDER BY last DESC LIMIT 6",
            array_merge([Auth::id()], $vp)
        );

        $ids = fn (array $rows) => array_map(fn ($r) => (int) $r['id'], $rows);
        $savedIds = $ids($saved);
        $suggested = array_values(array_unique(array_merge($ids($thread), $ids($byContact))));
        $recentIds = array_values(array_diff($ids($recent), $suggested));
        return [
            'saved'     => self::projects($savedIds),
            'suggested' => self::projects(array_values(array_diff($suggested, $savedIds))),
            'recent'    => self::projects(array_values(array_diff($recentIds, $savedIds))),
            'thread'    => (bool) $thread,
        ];
    }

    /** Arama kutusu: proje adı/no, müvekkil adı/kodu, esas no. */
    public static function search(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        [$visible, $vp] = Access::projectWhere();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $rows = DB::all(
            "SELECT p.id FROM projects p JOIN clients c ON c.id = p.client_id
             WHERE p.status = 'open' AND $visible
               AND (p.name LIKE ? OR p.number LIKE ? OR c.name LIKE ? OR c.code LIKE ? OR p.case_number LIKE ?)
             ORDER BY c.name, p.number LIMIT 20",
            array_merge($vp, [$like, $like, $like, $like, $like])
        );
        return self::projects(array_map(fn ($r) => (int) $r['id'], $rows));
    }

    /** Panelde gösterilecek proje kartları (verilen sırada). */
    private static function projects(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $rows = DB::all(
            'SELECT p.id, p.number, p.name, p.billing_type, c.name AS client_name FROM projects p JOIN clients c ON c.id = p.client_id
             WHERE p.id IN (' . implode(',', array_map('intval', $ids)) . ')'
        );
        $byId = array_column($rows, null, 'id');
        return array_values(array_filter(array_map(fn ($id) => isset($byId[$id]) ? [
            'id'       => (int) $byId[$id]['id'],
            'number'   => $byId[$id]['number'],
            'name'     => $byId[$id]['name'],
            'client'   => $byId[$id]['client_name'],
            'billable' => $byId[$id]['billing_type'] !== 'non_billable',
        ] : null, $ids)));
    }

    // --- Kaydetme -------------------------------------------------------------------------------------

    /**
     * Açık e-postayı projeye kaydeder; istenirse seçili ekleri belge klasörüne koyar ve zaman kaydı girer.
     * Döner: ['saved' => bool (false: zaten kayıtlıydı), 'attachments' => [...], 'time_entry_id' => ?int]
     */
    public static function save(array $in): array
    {
        $project = self::project((int) ($in['projectId'] ?? 0));
        $userId = (int) Auth::id();
        $messageId = (string) ($in['itemId'] ?? '');
        $note = isset($in['note']) ? (string) $in['note'] : null;

        // Zaman kaydı önce doğrulanır; e-posta kaydedildikten sonra hata çıkmasın
        $time = self::timeInput($in, $project);
        $saved = ProjectEmailService::save($project, $userId, $messageId, $note);
        $attachments = ProjectEmailService::saveAttachments($project, $userId, $messageId, array_map('strval', (array) ($in['attachments'] ?? [])));
        $timeId = $time ? TimeService::create($time, 'outlook') : null;
        return ['saved' => $saved, 'attachments' => $attachments, 'time_entry_id' => $timeId, 'project' => self::projects([(int) $project['id']])[0]];
    }

    /** Yazarken: "gönderince kaydet" kaydı. İşaret (marker) panelde üretilip e-postanın başlığına konur. */
    public static function queue(array $in): void
    {
        $project = self::project((int) ($in['projectId'] ?? 0));
        $marker = (string) ($in['marker'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $marker)) {
            throw new ValidationException(['marker' => 'invalid']);
        }
        $time = self::timeInput($in, $project);
        DB::query(
            'INSERT INTO outlook_pending (user_id, marker, project_id, subject, recipients, note, duration, is_billable)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE project_id = VALUES(project_id), subject = VALUES(subject), recipients = VALUES(recipients),
                                     note = VALUES(note), duration = VALUES(duration), is_billable = VALUES(is_billable)',
            [
                Auth::id(), $marker, $project['id'],
                mb_substr(trim((string) ($in['subject'] ?? '')), 0, 500) ?: null,
                mb_substr(implode(',', array_map('strval', (array) ($in['addresses'] ?? []))), 0, 1000) ?: null,
                mb_substr(trim((string) ($in['note'] ?? '')), 0, 500) ?: null,
                $time ? $time['duration'] : null,
                $time ? (int) !empty($time['is_billable']) : 1,
            ]
        );
    }

    /** Yazarken vazgeçilirse (proje seçimi kaldırıldı) kuyruktan düşer. */
    public static function unqueue(string $marker): void
    {
        DB::query("DELETE FROM outlook_pending WHERE marker = ? AND user_id = ? AND status = 'pending'", [$marker, Auth::id()]);
    }

    /**
     * Saatlik iş: bekleyen "gönderince kaydet" kayıtları kişinin Gönderilmiş Öğeler'inde aranır.
     * Önce x-flow-marker başlığı, bulunamazsa konu + alıcı + zaman. 3 gün içinde bulunamayan "failed" olur.
     */
    public static function processPending(): array
    {
        $result = ['saved' => 0, 'waiting' => 0, 'failed' => 0];
        $rows = DB::all("SELECT * FROM outlook_pending WHERE status = 'pending' ORDER BY user_id, id");
        $byUser = [];
        foreach ($rows as $r) {
            $byUser[$r['user_id']][] = $r;
        }
        foreach ($byUser as $userId => $items) {
            $user = DB::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$userId]);
            if (!$user) {
                continue;
            }
            Auth::actAs($user);
            $previous = \App\Core\Lang::locale();
            \App\Core\Lang::set($user['locale'] ?: config('app.locale'));   // zaman kaydı açıklaması kişinin dilinde
            try {
                $since = gmdate('Y-m-d\TH:i:s\Z', strtotime(min(array_column($items, 'created_at'))) - 600);
                $sent = Microsoft::graphAs((int) $userId, 'GET', '/me/mailFolders/sentitems/messages?$top=50'
                    . '&$filter=' . rawurlencode("sentDateTime ge $since")
                    . '&$orderby=' . rawurlencode('sentDateTime desc')
                    . '&$select=id,subject,sentDateTime,toRecipients,ccRecipients,internetMessageHeaders')['value'] ?? [];
                foreach ($items as $p) {
                    $result[self::matchAndSave($p, $sent)]++;
                }
            } catch (Throwable $e) {
                foreach ($items as $p) {
                    $result[self::expireIfOld($p, $e->getMessage())]++;
                }
            } finally {
                Auth::actAs(null);
                \App\Core\Lang::set($previous);
            }
        }
        return $result;
    }

    private static function matchAndSave(array $p, array $sent): string
    {
        $match = null;
        foreach ($sent as $m) {
            foreach ($m['internetMessageHeaders'] ?? [] as $h) {
                if (strtolower($h['name'] ?? '') === self::MARKER_HEADER && trim((string) $h['value']) === $p['marker']) {
                    $match = $m;
                    break 2;
                }
            }
        }
        if (!$match && $p['subject']) {
            // Başlık taşınmadıysa: aynı konu, gönderim kayıttan sonra, alıcılardan biri ortak
            $want = array_filter(explode(',', mb_strtolower((string) $p['recipients'])));
            foreach ($sent as $m) {
                $to = array_map(fn ($r) => mb_strtolower($r['emailAddress']['address'] ?? ''), array_merge($m['toRecipients'] ?? [], $m['ccRecipients'] ?? []));
                if (trim((string) ($m['subject'] ?? '')) === trim($p['subject'])
                    && strtotime($m['sentDateTime']) >= strtotime($p['created_at']) - 60
                    && (!$want || array_intersect($want, $to))) {
                    $match = $m;
                    break;
                }
            }
        }
        if (!$match) {
            return self::expireIfOld($p, null);
        }

        $project = DB::one('SELECT * FROM projects WHERE id = ?', [$p['project_id']]);
        ProjectEmailService::save($project, (int) $p['user_id'], $match['id'], $p['note']);
        if ($p['duration'] && $project['status'] === 'open') {
            try {
                TimeService::create([
                    'project_id'  => $project['id'],
                    'work_date'   => date('Y-m-d', strtotime($match['sentDateTime'])),
                    'duration'    => $p['duration'],
                    'description' => self::timeDescription((string) $match['subject']),
                    'is_billable' => $p['is_billable'],
                ], 'outlook');
            } catch (ValidationException) {
                // e-posta kaydedildi; zaman kaydı girilemediyse (ör. proje kapandı) yalnız o atlanır
            }
        }
        DB::update('outlook_pending', ['status' => 'saved', 'done_at' => date('Y-m-d H:i:s'), 'error' => null], 'id = ?', [$p['id']]);
        return 'saved';
    }

    private static function expireIfOld(array $p, ?string $error): string
    {
        if (strtotime($p['created_at']) < time() - 3 * 86400) {
            DB::update('outlook_pending', ['status' => 'failed', 'done_at' => date('Y-m-d H:i:s'), 'error' => mb_substr($error ?? 'not found in Sent Items', 0, 255)], 'id = ?', [$p['id']]);
            return 'failed';
        }
        if ($error) {
            DB::update('outlook_pending', ['error' => mb_substr($error, 0, 255)], 'id = ?', [$p['id']]);
        }
        return 'waiting';
    }

    // --- Yardımcılar ----------------------------------------------------------------------------------

    private static function project(int $id): array
    {
        $project = DB::one('SELECT * FROM projects WHERE id = ?', [$id]);
        if (!$project || !Access::canSeeProject($id)) {
            throw new RuntimeException('Project not found', 404);
        }
        return $project;
    }

    /** Panelde zaman da girildiyse TimeService girdisi; girilmediyse null. Hata varsa ValidationException. */
    private static function timeInput(array $in, array $project): ?array
    {
        $duration = trim((string) ($in['time']['duration'] ?? ''));
        if ($duration === '') {
            return null;
        }
        if (!can('time.own') && !can('time.all')) {
            throw new RuntimeException('Forbidden', 403);
        }
        $input = [
            'project_id'  => $project['id'],
            'work_date'   => date('Y-m-d'),
            'duration'    => $duration,
            'description' => trim((string) ($in['time']['description'] ?? '')) ?: self::timeDescription((string) ($in['subject'] ?? '')),
            'is_billable' => !empty($in['time']['billable']),
        ];
        // Yalnız doğrulama: geçersizse burada hata verir, kayıt sonra girilir
        if (TimeService::parseDuration($duration) === null) {
            throw new ValidationException(['duration' => __('time.invalid_duration')]);
        }
        if ($project['status'] !== 'open') {
            throw new ValidationException(['duration' => __('time.project_required')]);
        }
        return $input;
    }

    private static function timeDescription(string $subject): string
    {
        $subject = trim(preg_replace('/^(re|fw|fwd|ynt|ilt)\s*:\s*/iu', '', trim($subject)));
        return __('outlook.time_description', ['subject' => $subject !== '' ? $subject : '—']);
    }
}
