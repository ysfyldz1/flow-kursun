<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Kişinin kendi OneDrive'ı (kullanıcı adına bağlantı, Files.ReadWrite). Gezinme/arama ve seçilen dosyayı
 * projenin klasörüne kopyalama: dosya kişinin yetkisiyle okunur, uygulama yetkisiyle projeye yazılır;
 * böylece kişinin proje sitesinde ayrıca yetkisi gerekmez ve OneDrive'daki asıl dosya değişmez.
 */
final class OneDriveService
{
    public const MAX_BYTES = 50 * 1024 * 1024;
    private const SELECT = 'id,name,size,file,folder,lastModifiedDateTime,parentReference,webUrl';

    /** Klasör içeriği (boşsa kök) ya da arama sonuçları; klasörler önce. Yol gösterici: [[id, ad], ...] */
    public static function browse(int $userId, ?string $folderId, string $q = ''): array
    {
        $q = trim(str_replace("'", "''", $q));
        if ($q !== '') {
            $items = Microsoft::graphAs($userId, 'GET', "/me/drive/root/search(q='" . rawurlencode($q) . "')?\$top=50&\$select=" . self::SELECT)['value'] ?? [];
            return ['crumbs' => [], 'items' => self::shape($items), 'search' => true];
        }
        if ($folderId !== null && $folderId !== '' && !self::validId($folderId)) {
            throw new RuntimeException('Invalid folder', 404);
        }
        $path = $folderId ? '/me/drive/items/' . rawurlencode($folderId) : '/me/drive/root';
        $items = Microsoft::graphAs($userId, 'GET', "$path/children?\$top=200&\$select=" . self::SELECT)['value'] ?? [];
        usort($items, fn ($a, $b) => [isset($b['folder']), mb_strtolower($a['name'])] <=> [isset($a['folder']), mb_strtolower($b['name'])]);

        $crumbs = [];
        $id = $folderId;
        for ($i = 0; $id && $i < 10; $i++) {
            $f = Microsoft::graphAs($userId, 'GET', '/me/drive/items/' . rawurlencode($id) . '?$select=id,name,parentReference,root');
            if (isset($f['root'])) {
                break;
            }
            array_unshift($crumbs, [$f['id'], $f['name']]);
            $id = isset($f['parentReference']['path']) && str_ends_with($f['parentReference']['path'], 'root:') ? null : ($f['parentReference']['id'] ?? null);
        }
        return ['crumbs' => $crumbs, 'items' => self::shape($items), 'search' => false];
    }

    /** OneDrive'daki dosyayı hedef klasöre (projenin SharePoint klasörü) kopyalar; yeni öğeyi döndürür. */
    public static function copyToFolder(int $userId, string $itemId, string $targetFolderId): array
    {
        if (!self::validId($itemId)) {
            throw new RuntimeException('Invalid item', 404);
        }
        $item = Microsoft::graphAs($userId, 'GET', '/me/drive/items/' . rawurlencode($itemId) . '?$select=id,name,size,file');
        if (!isset($item['file'])) {
            throw new RuntimeException(__('onedrive.only_files'), 422);
        }
        if (($item['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException(__('onedrive.too_large', ['name' => $item['name'], 'max' => format_bytes(self::MAX_BYTES)]), 422);
        }
        $content = Microsoft::rawAs($userId, '/me/drive/items/' . rawurlencode($itemId) . '/content');
        return DocumentService::storeFile($targetFolderId, $item['name'], $content);
    }

    /** Birden çok dosya; her biri ayrı denenir. [kopyalanan sayısı, hata mesajları] */
    public static function copyMany(int $userId, array $ids, string $targetFolderId, int $projectId): array
    {
        $done = 0;
        $errors = [];
        foreach (array_slice($ids, 0, 20) as $id) {
            try {
                $new = self::copyToFolder($userId, (string) $id, $targetFolderId);
                Audit::log('document.from_onedrive', 'project', $projectId, ['name' => $new['name'] ?? '']);
                $done++;
            } catch (RuntimeException $e) {
                if ($e->getCode() === 401) {
                    throw $e;
                }
                $errors[] = $e->getMessage();
            }
        }
        return [$done, $errors];
    }

    private static function shape(array $items): array
    {
        return array_map(fn ($i) => [
            'id'       => $i['id'],
            'name'     => $i['name'],
            'folder'   => isset($i['folder']),
            'count'    => $i['folder']['childCount'] ?? null,
            'size'     => isset($i['file']) ? format_bytes($i['size'] ?? 0) : '',
            'tooLarge' => isset($i['file']) && ($i['size'] ?? 0) > self::MAX_BYTES,
            'modified' => isset($i['lastModifiedDateTime']) ? date('d.m.Y', strtotime($i['lastModifiedDateTime'])) : '',
            'webUrl'   => $i['webUrl'] ?? null,
        ], $items);
    }

    private static function validId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9!_\-]+$/', $id);
    }
}
