<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Kişinin kendi OneDrive'ı (kullanıcı adına bağlantı, Files.ReadWrite). Gezinme/arama ve seçilen dosyayı
 * projenin klasörüne kopyalama: dosya kişinin yetkisiyle okunur, uygulama yetkisiyle projeye yazılır;
 * böylece kişinin proje sitesinde ayrıca yetkisi gerekmez ve OneDrive'daki asıl dosya değişmez.
 *
 * "Dosyalarım'a kısayol ekle" ile eklenen klasörler başka bir sürücüdeki (SharePoint sitesi) öğeyi gösterir
 * (remoteItem). Böyle öğelerin kimliği "sürücü~öğe" biçiminde taşınır; içleri o sürücü üzerinden gezilir.
 */
final class OneDriveService
{
    public const MAX_BYTES = 50 * 1024 * 1024;
    private const SELECT = 'id,name,size,file,folder,remoteItem,lastModifiedDateTime,parentReference';

    /** Klasör içeriği (boşsa kök) ya da arama sonuçları; klasörler (ve klasör kısayolları) önce. */
    public static function browse(int $userId, ?string $folderId, string $q = ''): array
    {
        $q = trim(str_replace("'", "''", $q));
        if ($q !== '') {
            $items = Microsoft::graphAs($userId, 'GET', "/me/drive/root/search(q='" . rawurlencode($q) . "')?\$top=50&\$select=" . self::SELECT)['value'] ?? [];
            return ['items' => self::shape($items, null), 'search' => true];
        }
        [$drive, $base] = $folderId ? self::path($folderId) : [null, '/me/drive/root'];
        $items = Microsoft::graphAs($userId, 'GET', "$base/children?\$top=200&\$select=" . self::SELECT)['value'] ?? [];
        if (!$folderId) {
            $items = array_merge($items, self::rootShortcuts($userId, $items));
        }
        $items = self::shape($items, $drive);
        usort($items, fn ($a, $b) => [$b['folder'], mb_strtolower($a['name'])] <=> [$a['folder'], mb_strtolower($b['name'])]);
        return ['items' => $items, 'search' => false];
    }

    /** OneDrive'daki (ya da kısayolun gösterdiği sürücüdeki) dosyayı hedef klasöre kopyalar. */
    public static function copyToFolder(int $userId, string $itemId, string $targetFolderId): array
    {
        [, $base] = self::path($itemId);
        $item = Microsoft::graphAs($userId, 'GET', "$base?\$select=id,name,size,file");
        if (!isset($item['file'])) {
            throw new RuntimeException(__('onedrive.only_files'), 422);
        }
        if (($item['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException(__('onedrive.too_large', ['name' => $item['name'], 'max' => format_bytes(self::MAX_BYTES)]), 422);
        }
        return DocumentService::storeFile($targetFolderId, $item['name'], Microsoft::rawAs($userId, "$base/content"));
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

    /**
     * Graph, "Dosyalarım'a kısayol ekle" ile eklenen öğeleri children listesinde döndürmüyor; boş arama döndürüyor.
     * Kökteki kısayollar (üst klasörü kök olanlar) buradan alınıp listeye eklenir.
     */
    private static function rootShortcuts(int $userId, array $children): array
    {
        try {
            $found = Microsoft::graphAs($userId, 'GET', "/me/drive/root/search(q='')?\$top=200&\$select=" . self::SELECT)['value'] ?? [];
        } catch (RuntimeException) {
            return [];   // arama çalışmazsa kısayolsuz liste yine gösterilir
        }
        $rootId = $children[0]['parentReference']['id'] ?? Microsoft::graphAs($userId, 'GET', '/me/drive/root?$select=id')['id'];
        $have = array_column($children, 'id');
        return array_values(array_filter($found, fn ($i) => isset($i['remoteItem'])
            && ($i['parentReference']['id'] ?? null) === $rootId
            && !in_array($i['id'], $have, true)));
    }

    /** Kimlik → [sürücü ya da null, Graph yolu]. "sürücü~öğe" başka sürücüdeki (kısayol) öğedir. */
    private static function path(string $id): array
    {
        if (!preg_match('/^(?:([A-Za-z0-9!_\-]+)~)?([A-Za-z0-9!_\-]+)$/', $id, $m)) {
            throw new RuntimeException('Invalid item', 404);
        }
        return $m[1] !== ''
            ? [$m[1], '/drives/' . rawurlencode($m[1]) . '/items/' . rawurlencode($m[2])]
            : [null, '/me/drive/items/' . rawurlencode($m[2])];
    }

    /** $drive: içinde gezilen başka sürücü (kısayolun içi); oradaki öğelerin kimliği sürücüyle birlikte taşınır. */
    private static function shape(array $items, ?string $drive): array
    {
        return array_map(function ($i) use ($drive) {
            $remote = $i['remoteItem'] ?? null;   // kısayol: hedefin türü ve kimliği remoteItem'da
            $src = $remote ?? $i;
            $id = $remote
                ? ($remote['parentReference']['driveId'] ?? '') . '~' . $remote['id']
                : ($drive ? "$drive~{$i['id']}" : $i['id']);
            $isFolder = isset($src['folder']);
            $size = (int) ($src['size'] ?? 0);
            return [
                'id'       => $id,
                'name'     => $i['name'],
                'folder'   => $isFolder,
                'shortcut' => (bool) $remote,
                'count'    => $src['folder']['childCount'] ?? null,
                'size'     => $isFolder ? '' : format_bytes($size),
                'tooLarge' => !$isFolder && $size > self::MAX_BYTES,
                'modified' => isset($i['lastModifiedDateTime']) ? date('d.m.Y', strtotime($i['lastModifiedDateTime'])) : '',
            ];
        }, $items);
    }
}
