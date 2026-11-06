<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ValidationException;
use RuntimeException;

/**
 * Proje belgeleri. Dosyalar Flow'da değil, kurumun M365'inde durur; Graph'ta ikisi de bir "drive":
 *  - sharepoint: bir SharePoint sitesinin belge kitaplığı (ekip Teams/Explorer'dan da erişir)
 *  - onedrive:   merkezi bir hesabın OneDrive'ı
 * Yapı: <kök>/<M-0001 Müvekkil>/<M-0001-001 Proje>. Klasör kimliği projede saklanır; adlar değişse de bağ kopmaz.
 * Yükleme tarayıcıdan doğrudan Graph'a gider (upload session), sunucudan geçmez.
 */
final class DocumentService
{
    public const PROVIDERS = ['sharepoint', 'onedrive'];
    private const SELECT = 'id,name,size,file,folder,webUrl,createdDateTime,lastModifiedDateTime,lastModifiedBy,parentReference';

    public static function enabled(): bool
    {
        return Microsoft::isConfigured() && Settings::get('docs.drive_id');
    }

    /** Ayarlardaki SharePoint site adresinden ya da OneDrive kullanıcısından drive kimliğini bulur. */
    public static function resolveDrive(string $provider, string $target): string
    {
        if ($provider === 'sharepoint') {
            $u = parse_url($target);
            if (empty($u['host']) || empty($u['path'])) {
                throw new RuntimeException(__('docs.site_invalid'));
            }
            $site = Microsoft::graph('GET', '/sites/' . $u['host'] . ':' . rtrim($u['path'], '/') . '?$select=id');
            $drive = Microsoft::graph('GET', '/sites/' . $site['id'] . '/drive?$select=id');
        } else {
            $drive = Microsoft::graph('GET', '/users/' . rawurlencode($target) . '/drive?$select=id');
        }
        return (string) $drive['id'];
    }

    /** Projenin klasörü; yoksa (ilk kullanımda) oluşturulur. */
    public static function projectFolder(array $project): string
    {
        if (!empty($project['drive_folder_id'])) {
            return $project['drive_folder_id'];
        }
        $client = DB::one('SELECT code, name FROM clients WHERE id = ?', [$project['client_id']]);
        $folder = self::ensurePath([
            (string) Settings::get('docs.root', 'Flow'),
            $client['code'] . ' ' . $client['name'],
            $project['number'] . ' ' . $project['name'],
        ]);
        DB::update('projects', ['drive_folder_id' => $folder], 'id = ?', [$project['id']]);
        return $folder;
    }

    public static function list(string $folderId): array
    {
        $items = self::graph('GET', "/items/$folderId/children?\$select=" . self::SELECT . '&$top=500')['value'] ?? [];
        // Önce klasörler, sonra dosyalar; ikisi de ada göre
        usort($items, fn ($a, $b) => [isset($b['folder']), mb_strtolower($a['name'])] <=> [isset($a['folder']), mb_strtolower($b['name'])]);
        return $items;
    }

    /** Öğe projenin klasöründe mi? Başka klasörden kimlik uydurup indirme yapılamasın. */
    public static function item(string $folderId, string $itemId): array
    {
        if (!preg_match('/^[A-Za-z0-9!_\-]+$/', $itemId)) {
            throw new RuntimeException('Invalid item', 404);
        }
        $item = self::graph('GET', "/items/$itemId?\$select=" . self::SELECT . ',@microsoft.graph.downloadUrl');
        if (($item['parentReference']['id'] ?? null) !== $folderId) {
            throw new RuntimeException('Item not in project folder', 404);
        }
        return $item;
    }

    /** Tarayıcının parça parça yükleyeceği, önceden yetkilendirilmiş adres. Aynı ad varsa yeni ad verilir. */
    public static function uploadSession(string $folderId, string $name, int $size): string
    {
        $name = self::safeName($name);
        if ($name === '' || $size <= 0) {
            throw new ValidationException(['file' => __('docs.file_empty')]);
        }
        $s = self::graph('POST', "/items/$folderId:/" . rawurlencode($name) . ':/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'rename'],
        ]);
        return (string) $s['uploadUrl'];
    }

    /**
     * Office Online'da açma adresi. SharePoint'te ekip siteye zaten erişir (webUrl);
     * merkezi OneDrive'da dosya başkasınındır, kurum içi düzenleme bağlantısı oluşturulur.
     */
    public static function openUrl(array $item): string
    {
        if (Settings::get('docs.provider') === 'onedrive') {
            try {
                $link = self::graph('POST', "/items/{$item['id']}/createLink", ['type' => 'edit', 'scope' => 'organization']);
                return (string) $link['link']['webUrl'];
            } catch (RuntimeException) {
                // Kurum içi bağlantı kapatılmış olabilir; webUrl'e düş
            }
        }
        return (string) $item['webUrl'];
    }

    // --- Portal paylaşımı ---

    public static function shares(int $projectId): array
    {
        return array_column(DB::all('SELECT * FROM document_shares WHERE project_id = ?', [$projectId]), null, 'item_id');
    }

    public static function toggleShare(array $project, array $item, int $userId): bool
    {
        $existing = DB::one('SELECT id FROM document_shares WHERE project_id = ? AND item_id = ?', [$project['id'], $item['id']]);
        if ($existing) {
            DB::query('DELETE FROM document_shares WHERE id = ?', [$existing['id']]);
            Audit::log('document.unshared', 'project', (int) $project['id'], ['name' => $item['name']]);
            return false;
        }
        DB::insert('document_shares', [
            'project_id' => $project['id'],
            'item_id'    => $item['id'],
            'name'       => $item['name'],
            'size'       => $item['size'] ?? null,
            'source'     => 'office',
            'shared_by'  => $userId,
        ]);
        Audit::log('document.shared', 'project', (int) $project['id'], ['name' => $item['name']]);
        return true;
    }

    /** Müvekkilin yüklediği dosya projenin klasörüne düşer ve kendisiyle paylaşılmış sayılır. */
    public static function recordClientUpload(array $project, array $item, int $portalUserId): void
    {
        DB::query(
            "INSERT IGNORE INTO document_shares (project_id, item_id, name, size, source, portal_user_id)
             VALUES (?, ?, ?, ?, 'client', ?)",
            [$project['id'], $item['id'], $item['name'], $item['size'] ?? null, $portalUserId]
        );
        Audit::log('document.client_uploaded', 'project', (int) $project['id'], ['name' => $item['name']], 'portal');
    }

    private static function ensurePath(array $segments): string
    {
        $parent = 'root';
        foreach ($segments as $segment) {
            $name = self::safeName($segment);
            try {
                $parent = self::graph('POST', "/items/$parent/children", [
                    'name'   => $name,
                    'folder' => new \stdClass(),
                    '@microsoft.graph.conflictBehavior' => 'fail',
                ])['id'];
            } catch (RuntimeException $e) {
                if ($e->getCode() !== 409) {
                    throw $e;
                }
                $parent = self::graph('GET', "/items/$parent:/" . rawurlencode($name) . '?$select=id')['id'];
            }
        }
        return $parent;
    }

    /** OneDrive/SharePoint'in kabul etmediği karakterler ve baştaki/sondaki boşluk-nokta temizlenir. */
    private static function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1f"*:<>?\/\\\\|#%]+/u', '-', $name);
        return mb_substr(trim((string) $name, " .\t"), 0, 200);
    }

    private static function graph(string $method, string $path, ?array $body = null): array
    {
        return Microsoft::graph($method, '/drives/' . Settings::get('docs.drive_id') . $path, $body);
    }
}
