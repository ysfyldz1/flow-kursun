<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use RuntimeException;

/**
 * E-postayı projeye kaydetme. Kişi kendi posta kutusunda (kullanıcı adına bağlantı, Mail.Read) arar ve seçer;
 * e-posta ekleriyle birlikte MIME (.eml) olarak projenin "Yazışmalar" klasörüne yüklenir (uygulama izniyle).
 * Böylece e-posta kişinin posta kutusundan silinse bile projede kalır ve ekipteki herkes açabilir.
 */
final class ProjectEmailService
{
    public const FOLDER = 'Yazışmalar';
    private const SELECT = 'id,subject,from,toRecipients,ccRecipients,receivedDateTime,hasAttachments,bodyPreview,internetMessageId';

    public static function forProject(int $projectId): array
    {
        return DB::all(
            'SELECT e.*, u.name AS saved_by_name FROM project_emails e LEFT JOIN users u ON u.id = e.saved_by
             WHERE e.project_id = ? ORDER BY e.received_at DESC, e.id DESC',
            [$projectId]
        );
    }

    public static function find(int $projectId, int $id): ?array
    {
        return DB::one('SELECT * FROM project_emails WHERE id = ? AND project_id = ?', [$id, $projectId]);
    }

    /**
     * Kişinin posta kutusunda arama (tüm klasörler). Boş aramada son e-postalar.
     * Graph'ta $search ile sıralama birlikte kullanılamaz; arama sonuçları alaka sırasıyla gelir.
     */
    public static function search(int $userId, string $q, array $project): array
    {
        $q = trim(str_replace('"', '', $q));
        $path = '/me/messages?$top=30&$select=' . self::SELECT
            . ($q !== '' ? '&$search=' . rawurlencode('"' . $q . '"') : '&$orderby=' . rawurlencode('receivedDateTime desc'));
        $saved = array_flip(array_column(DB::all('SELECT internet_message_id FROM project_emails WHERE project_id = ?', [$project['id']]), 'internet_message_id'));
        return array_map(fn ($m) => [
            'id'          => $m['id'],
            'subject'     => (string) ($m['subject'] ?? ''),
            'from'        => $m['from']['emailAddress']['name'] ?? $m['from']['emailAddress']['address'] ?? '',
            'fromEmail'   => $m['from']['emailAddress']['address'] ?? '',
            'date'        => isset($m['receivedDateTime']) ? date('d.m.Y H:i', strtotime($m['receivedDateTime'])) : '',
            'attachments' => (bool) ($m['hasAttachments'] ?? false),
            'preview'     => mb_strimwidth(trim((string) ($m['bodyPreview'] ?? '')), 0, 140, '…'),
            'saved'       => isset($saved[$m['internetMessageId'] ?? '']),
        ], Microsoft::graphAs($userId, 'GET', $path)['value'] ?? []);
    }

    /** Seçilen e-postayı projeye kaydeder. Zaten kayıtlıysa false döner. */
    public static function save(array $project, int $userId, string $messageId): bool
    {
        if (!preg_match('/^[A-Za-z0-9\-_=+\/]+$/', $messageId)) {
            throw new RuntimeException('Invalid message', 404);
        }
        $id = rawurlencode($messageId);
        $m = Microsoft::graphAs($userId, 'GET', "/me/messages/$id?\$select=" . self::SELECT);
        $imid = (string) ($m['internetMessageId'] ?? $messageId);
        if (DB::value('SELECT 1 FROM project_emails WHERE project_id = ? AND internet_message_id = ?', [$project['id'], $imid])) {
            return false;
        }

        $mime = Microsoft::rawAs($userId, "/me/messages/$id/\$value");
        $received = isset($m['receivedDateTime']) ? date('Y-m-d H:i:s', strtotime($m['receivedDateTime'])) : date('Y-m-d H:i:s');
        $from = $m['from']['emailAddress'] ?? [];
        $name = mb_substr(substr($received, 0, 10) . ' ' . trim((string) ($m['subject'] ?? '')) ?: '—', 0, 150)
            . ' (' . mb_substr((string) ($from['name'] ?? $from['address'] ?? ''), 0, 40) . ').eml';
        $item = DocumentService::storeFile(DocumentService::projectSubfolder($project, self::FOLDER), $name, $mime);

        $to = array_map(fn ($r) => $r['emailAddress']['address'] ?? '', array_merge($m['toRecipients'] ?? [], $m['ccRecipients'] ?? []));
        DB::insert('project_emails', [
            'project_id'          => $project['id'],
            'internet_message_id' => mb_substr($imid, 0, 400),
            'subject'             => mb_substr((string) ($m['subject'] ?? ''), 0, 500),
            'from_name'           => mb_substr((string) ($from['name'] ?? ''), 0, 255) ?: null,
            'from_email'          => mb_substr((string) ($from['address'] ?? ''), 0, 255) ?: null,
            'to_text'             => mb_substr(implode(', ', array_filter($to)), 0, 1000) ?: null,
            'received_at'         => $received,
            'has_attachments'     => (int) !empty($m['hasAttachments']),
            'preview'             => mb_substr(trim((string) ($m['bodyPreview'] ?? '')), 0, 500) ?: null,
            'drive_item_id'       => $item['id'] ?? null,
            'file_name'           => $item['name'] ?? $name,
            'size'                => strlen($mime),
            'saved_by'            => Auth::id(),
        ]);
        Audit::log('email.saved', 'project', (int) $project['id'], ['subject' => $m['subject'] ?? '', 'file' => $item['name'] ?? $name]);
        return true;
    }

    /** Listeden ve SharePoint'ten kaldırır. */
    public static function delete(array $project, int $id): void
    {
        $e = self::find((int) $project['id'], $id) ?? throw new RuntimeException('Not found', 404);
        if ($e['drive_item_id']) {
            DocumentService::deleteItem($e['drive_item_id']);
        }
        DB::query('DELETE FROM project_emails WHERE id = ?', [$id]);
        Audit::log('email.removed', 'project', (int) $project['id'], ['subject' => $e['subject']]);
    }

    /** Müvekkilin iletişim kişilerinin e-postaları (seçicide hızlı filtre). */
    public static function clientAddresses(int $clientId): array
    {
        $rows = DB::all("SELECT email FROM client_contacts WHERE client_id = ? AND email IS NOT NULL AND email <> '' ORDER BY is_primary DESC", [$clientId]);
        $client = DB::value('SELECT email FROM clients WHERE id = ?', [$clientId]);
        return array_values(array_unique(array_filter(array_merge($client ? [$client] : [], array_column($rows, 'email')))));
    }
}
