<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Dosya Transferi: hesabı olmayan kişilere bağlantıyla büyük dosya gönderme.
 * Dosyalar Ayarlar→Microsoft 365'te seçilen ayrı depoda, <kök>/<tarih başlık> klasöründe durur.
 * Yükleme tarayıcıdan doğrudan Graph'a gider; indirme kısa ömürlü Graph adresine yönlendirilir, ZIP sunucudan akar.
 */
final class TransferService
{
    public const EXPIRY_DAYS = [7, 30, 0];       // 0: süresiz
    public const RETENTION_DAYS = 30;            // süre dolduktan bu kadar gün sonra dosyalar silinir
    private const ABANDONED_DAYS = 7;            // yarım kalan yükleme bu kadar gün sonra silinir
    private const MAX_FILES = 2000;

    public static function enabled(): bool
    {
        return Microsoft::isConfigured() && Settings::get('transfer.drive_id');
    }

    public static function canSee(array $t): bool
    {
        return can('transfers.all') || (int) $t['created_by'] === Auth::id();
    }

    public static function list(): array
    {
        $own = can('transfers.all') ? '' : 'WHERE t.created_by = ' . (int) Auth::id();
        return DB::all(
            "SELECT t.*, u.name AS creator_name,
                    (SELECT COUNT(*) FROM transfer_files f WHERE f.transfer_id = t.id) AS file_count,
                    (SELECT COALESCE(SUM(f.size_bytes), 0) FROM transfer_files f WHERE f.transfer_id = t.id) AS total_size
             FROM transfers t LEFT JOIN users u ON u.id = t.created_by $own
             ORDER BY t.id DESC LIMIT 500"
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT t.*, u.name AS creator_name, u.email AS creator_email, u.locale AS creator_locale
             FROM transfers t LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?',
            [$id]
        );
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $id = DB::value("SELECT id FROM transfers WHERE token = ? AND status = 'ready'", [$token]);
        return $id ? self::find((int) $id) : null;
    }

    public static function files(int $transferId): array
    {
        return DB::all('SELECT * FROM transfer_files WHERE transfer_id = ? ORDER BY relative_path', [$transferId]);
    }

    public static function file(array $t, int $fileId): ?array
    {
        return DB::one('SELECT * FROM transfer_files WHERE id = ? AND transfer_id = ?', [$fileId, $t['id']]);
    }

    public static function downloads(int $transferId): array
    {
        return DB::all(
            'SELECT d.*, f.relative_path FROM transfer_downloads d LEFT JOIN transfer_files f ON f.id = d.file_id
             WHERE d.transfer_id = ? ORDER BY d.id DESC LIMIT 100',
            [$transferId]
        );
    }

    public static function isExpired(array $t): bool
    {
        return $t['expires_at'] && strtotime($t['expires_at']) <= time();
    }

    /** Dosyaların otomatik silineceği tarih; süresiz ya da silinmiş transferde null. */
    public static function deletesAt(array $t): ?string
    {
        if (!$t['expires_at'] || $t['files_deleted_at']) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($t['expires_at'] . ' +' . self::RETENTION_DAYS . ' days'));
    }

    public static function publicUrl(array $t): string
    {
        return rtrim((string) config('app.url'), '/') . '/t/' . $t['token'];
    }

    /** Transfer kaydını ve depodaki klasörünü oluşturur; [id, dosya kimlikleri (gönderilen sırayla)] döner. */
    public static function create(array $input, array $rawFiles): array
    {
        $files = self::cleanFiles($rawFiles);
        $errors = [];
        if (!$files) {
            $errors['files'] = __('transfers.no_files');
        } elseif (count($files) > self::MAX_FILES) {
            $errors['files'] = __('transfers.too_many', ['max' => self::MAX_FILES]);
        }
        $emails = self::parseEmails((string) ($input['recipient_email'] ?? ''));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['recipient_email'] = __('transfers.email_invalid', ['email' => $email]);
            }
        }
        if (mb_strlen(implode(', ', $emails)) > 1000) {
            $errors['recipient_email'] = __('transfers.email_invalid', ['email' => '…']);
        }
        $password = (string) ($input['password'] ?? '');
        if ($password !== '' && mb_strlen($password) < 4) {
            $errors['password'] = __('transfers.password_short');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        if (!self::enabled()) {
            throw new RuntimeException(__('transfers.not_configured'));
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = self::defaultTitle($files);
        }
        $title = mb_substr($title, 0, 255);
        $days = (int) ($input['expiry'] ?? 7);
        if (!in_array($days, self::EXPIRY_DAYS, true)) {
            $days = 7;
        }
        $locale = (string) ($input['locale'] ?? '');
        if (!in_array($locale, config('app.locales'), true)) {
            $locale = (string) Settings::get('org.locale', 'tr');
        }

        $driveId = (string) Settings::get('transfer.drive_id');
        $folderId = self::createFolder($driveId, $title);

        return DB::transaction(function () use ($input, $files, $title, $emails, $password, $days, $locale, $driveId, $folderId) {
            $id = DB::insert('transfers', [
                'token'              => bin2hex(random_bytes(16)),
                'title'              => $title,
                'message'            => trim((string) ($input['message'] ?? '')) ?: null,
                'recipient_email'    => $emails ? implode(', ', $emails) : null,
                'locale'             => $locale,
                'password_hash'      => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
                'expires_at'         => $days ? date('Y-m-d H:i:s', strtotime("+$days days")) : null,
                'notify_on_download' => !empty($input['notify']) ? 1 : 0,
                'drive_id'           => $driveId,
                'folder_item_id'     => $folderId,
                'created_by'         => Auth::id(),
            ]);
            $ids = [];
            foreach ($files as $f) {
                $ids[] = DB::insert('transfer_files', ['transfer_id' => $id, 'relative_path' => $f['path'], 'size_bytes' => $f['size']]);
            }
            Audit::log('transfer.created', 'transfer', $id, ['title' => $title, 'files' => count($files), 'recipient' => $emails]);
            return [$id, $ids];
        });
    }

    /** Tarayıcının parça parça yükleyeceği, önceden yetkilendirilmiş adres. */
    public static function uploadUrl(array $t, array $file): string
    {
        $s = self::graph($t['drive_id'], 'POST', "/items/{$t['folder_item_id']}:/" . self::encodePath($file['relative_path']) . ':/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);
        return (string) $s['uploadUrl'];
    }

    /** Dosya depoda yoldan bulunur ve boyutu doğrulanır; tarayıcının verdiği kimliğe güvenilmez. */
    public static function confirmFile(array $t, array $file): void
    {
        $item = self::graph($t['drive_id'], 'GET', "/items/{$t['folder_item_id']}:/" . self::encodePath($file['relative_path']) . '?$select=id,size');
        if ((int) $item['size'] !== (int) $file['size_bytes']) {
            throw new RuntimeException(__('transfers.incomplete', ['path' => $file['relative_path']]));
        }
        DB::update('transfer_files', ['item_id' => $item['id']], 'id = ?', [$file['id']]);
    }

    /** Transferi yayına alır, varsa alıcılara e-posta gönderir. Tüm e-postalar gittiyse true. */
    public static function finalize(array $t): bool
    {
        if (DB::value('SELECT COUNT(*) FROM transfer_files WHERE transfer_id = ? AND item_id IS NULL', [$t['id']])) {
            throw new RuntimeException(__('transfers.not_all_uploaded'));
        }
        DB::update('transfers', ['status' => 'ready'], 'id = ?', [$t['id']]);
        return self::sendToRecipients(self::find((int) $t['id']));
    }

    public static function sendToRecipients(array $t): bool
    {
        $emails = self::parseEmails((string) $t['recipient_email']);
        if (!$emails) {
            return false;
        }
        $files = self::files((int) $t['id']);
        $sent = true;
        foreach ($emails as $email) {
            $sent = Mailer::sendView($email, $t['locale'], 'transfer_recipient', [
                'sender'    => (string) $t['creator_name'],
                'title'     => $t['title'],
                'note'      => (string) $t['message'],
                'count'     => count($files),
                'size'      => format_bytes((int) array_sum(array_column($files, 'size_bytes'))),
                'url'       => self::publicUrl($t),
                'until'     => $t['expires_at'] ? date('d.m.Y H:i', strtotime($t['expires_at'])) : '',
                'protected' => $t['password_hash'] ? '1' : '',
            ], $t['creator_email'] ?: null) && $sent;
        }
        return $sent;
    }

    /** $choice: 7, 30, 0 (süresiz) ya da 'now' (bağlantıyı hemen kapat). */
    public static function setExpiry(array $t, string $choice): void
    {
        if ($t['files_deleted_at']) {
            return;   // dosyalar silinmişse bağlantı açılamaz
        }
        if ($choice === 'now') {
            $expires = date('Y-m-d H:i:s');
        } elseif (in_array((int) $choice, self::EXPIRY_DAYS, true) && ctype_digit($choice)) {
            $expires = $choice === '0' ? null : date('Y-m-d H:i:s', strtotime("+$choice days"));
        } else {
            return;
        }
        DB::update('transfers', ['expires_at' => $expires], 'id = ?', [$t['id']]);
        Audit::log('transfer.expiry', 'transfer', (int) $t['id'], ['expires_at' => $expires]);
    }

    /** Depodaki klasör silinemezse kayıt da silinmez: dosyalar sahipsiz kalmasın. */
    public static function delete(array $t): void
    {
        if (!$t['files_deleted_at']) {
            self::removeFolder($t);
        }
        DB::query('DELETE FROM transfers WHERE id = ?', [$t['id']]);
        Audit::log('transfer.deleted', 'transfer', (int) $t['id'], ['title' => $t['title']]);
    }

    /** İndirmeyi kaydeder; ilk indirmede gönderene e-posta gider. $file null ise ZIP. */
    public static function recordDownload(array $t, ?array $file): void
    {
        $first = DB::transaction(function () use ($t, $file) {
            DB::insert('transfer_downloads', [
                'transfer_id' => $t['id'],
                'file_id'     => $file['id'] ?? null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            if ($file) {
                DB::query('UPDATE transfer_files SET download_count = download_count + 1 WHERE id = ?', [$file['id']]);
            } else {
                DB::query('UPDATE transfer_files SET download_count = download_count + 1 WHERE transfer_id = ?', [$t['id']]);
            }
            DB::query('UPDATE transfers SET download_count = download_count + 1 WHERE id = ?', [$t['id']]);
            // Yalnız ilk indirmeyi yakalar; eşzamanlı isteklerde tek bildirim gider
            return DB::query('UPDATE transfers SET first_downloaded_at = NOW() WHERE id = ? AND first_downloaded_at IS NULL', [$t['id']])->rowCount() > 0;
        });
        if ($first && $t['notify_on_download'] && $t['creator_email']) {
            Mailer::sendView($t['creator_email'], $t['creator_locale'], 'transfer_downloaded', [
                'name'      => (string) $t['creator_name'],
                'title'     => $t['title'],
                'file_name' => $file['relative_path'] ?? '',
                'recipient' => (string) $t['recipient_email'],
                'when'      => date('d.m.Y H:i'),
            ]);
        }
    }

    public static function downloadUrl(array $t, string $itemId): string
    {
        // $select kullanılmaz: Graph, alan seçilince bazı dosyalarda indirme adresini döndürmüyor
        $item = self::graph($t['drive_id'], 'GET', '/items/' . rawurlencode($itemId));
        $url = $item['@microsoft.graph.downloadUrl'] ?? null;
        if (!$url) {
            throw new RuntimeException('No download URL');
        }
        return (string) $url;
    }

    /** Dosyaları depodan sırayla çekip tek ZIP olarak tarayıcıya akıtır. Graph adresleri kısa ömürlü; her dosyaya sırası gelince alınır. */
    public static function streamZip(array $t, array $files, string $firstUrl): never
    {
        set_time_limit(0);
        while (ob_get_level()) {
            ob_end_clean();
        }
        $name = self::safeName($t['title']) . '.zip';
        header('Content-Type: application/zip');
        header('Content-Length: ' . ZipStream::predictSize(array_map(fn ($f) => [$f['relative_path'], (int) $f['size_bytes']], $files)));
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\x20-\x7E]|"/', '_', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');

        $zip = new ZipStream();
        foreach ($files as $i => $f) {
            $url = $i === 0 ? $firstUrl : self::downloadUrl($t, $f['item_id']);
            $zip->beginFile($f['relative_path']);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_BUFFERSIZE     => 1024 * 1024,
                CURLOPT_WRITEFUNCTION  => function ($ch, string $data) use ($zip): int {
                    if (connection_aborted()) {
                        return 0;
                    }
                    $zip->write($data);
                    return strlen($data);
                },
            ]);
            $ok = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!$ok || $code >= 400) {
                // Arşiv yarıda kesilir; Content-Length tutmadığı için tarayıcı indirmeyi başarısız gösterir
                error_log("Transfer zip: {$f['relative_path']} indirilemedi (HTTP $code)");
                exit;
            }
            $zip->endFile();
        }
        $zip->finish();
        exit;
    }

    /** Günlük temizlik (bin/cron.php): süresi çoktan dolanların dosyaları ve yarım kalan yüklemeler silinir. */
    public static function cleanup(): array
    {
        $result = ['expired' => 0, 'abandoned' => 0, 'failed' => 0];
        $expired = DB::all(
            "SELECT * FROM transfers WHERE status = 'ready' AND files_deleted_at IS NULL AND expires_at IS NOT NULL AND expires_at <= ?",
            [date('Y-m-d H:i:s', strtotime('-' . self::RETENTION_DAYS . ' days'))]
        );
        foreach ($expired as $t) {
            try {
                self::removeFolder($t);
                DB::update('transfers', ['files_deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$t['id']]);
                Audit::log('transfer.files_deleted', 'transfer', (int) $t['id'], ['title' => $t['title']], 'system');
                $result['expired']++;
            } catch (Throwable $e) {
                error_log("Transfer temizliği #{$t['id']}: " . $e->getMessage());
                $result['failed']++;
            }
        }
        $abandoned = DB::all(
            "SELECT * FROM transfers WHERE status = 'uploading' AND created_at <= ?",
            [date('Y-m-d H:i:s', strtotime('-' . self::ABANDONED_DAYS . ' days'))]
        );
        foreach ($abandoned as $t) {
            try {
                self::removeFolder($t);
                DB::query('DELETE FROM transfers WHERE id = ?', [$t['id']]);
                $result['abandoned']++;
            } catch (Throwable $e) {
                error_log("Transfer temizliği #{$t['id']}: " . $e->getMessage());
                $result['failed']++;
            }
        }
        return $result;
    }

    private static function removeFolder(array $t): void
    {
        try {
            self::graph($t['drive_id'], 'DELETE', "/items/{$t['folder_item_id']}");
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {   // zaten silinmişse sorun değil
                throw $e;
            }
        }
    }

    private static function createFolder(string $driveId, string $title): string
    {
        $root = self::safeName((string) Settings::get('transfer.root', 'Flow Transfer'));
        try {
            $rootId = self::graph($driveId, 'GET', '/items/root:/' . rawurlencode($root) . '?$select=id')['id'];
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
            $rootId = self::graph($driveId, 'POST', '/items/root/children', [
                'name'   => $root,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'replace',
            ])['id'];
        }
        return (string) self::graph($driveId, 'POST', "/items/$rootId/children", [
            'name'   => self::safeName(date('Y-m-d') . ' ' . $title),
            'folder' => new \stdClass(),
            '@microsoft.graph.conflictBehavior' => 'rename',
        ])['id'];
    }

    /** Yolları temizler ve tekilleştirir (depo adlarda büyük/küçük harf ayırmaz). */
    private static function cleanFiles(array $raw): array
    {
        $files = [];
        $seen = [];
        foreach ($raw as $f) {
            $path = self::safePath((string) ($f['path'] ?? ''));
            $size = (int) ($f['size'] ?? 0);
            if ($path === '' || $size <= 0) {
                continue;
            }
            $unique = $path;
            for ($n = 2; isset($seen[mb_strtolower($unique)]); $n++) {
                $info = pathinfo($path);
                $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'] . '/';
                $unique = $dir . $info['filename'] . " ($n)" . (isset($info['extension']) ? '.' . $info['extension'] : '');
            }
            $seen[mb_strtolower($unique)] = true;
            $files[] = ['path' => $unique, 'size' => $size];
        }
        return $files;
    }

    private static function defaultTitle(array $files): string
    {
        $first = explode('/', $files[0]['path'])[0];
        if (count($files) === 1 || str_contains($files[0]['path'], '/')) {
            return $first;
        }
        return __('transfers.title_more', ['name' => $first, 'count' => count($files) - 1]);
    }

    private static function parseEmails(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $raw) ?: [])));
    }

    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private static function safePath(string $path): string
    {
        $parts = array_filter(explode('/', str_replace('\\', '/', $path)), fn ($p) => $p !== '' && $p !== '.' && $p !== '..');
        return implode('/', array_map([self::class, 'safeName'], $parts));
    }

    /** OneDrive/SharePoint'in kabul etmediği karakterler ve baştaki/sondaki boşluk-nokta temizlenir. */
    private static function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1f"*:<>?\/\\\\|#%]+/u', '-', $name);
        return mb_substr(trim((string) $name, " .\t"), 0, 200) ?: 'file';
    }

    private static function graph(string $driveId, string $method, string $path, ?array $body = null): array
    {
        return Microsoft::graph($method, "/drives/$driveId$path", $body);
    }
}
