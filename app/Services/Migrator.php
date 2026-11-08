<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** database/migrations içindeki henüz çalışmamış .sql dosyalarını sırayla uygular (CLI, kurulum, Güncellemeler). */
final class Migrator
{
    /** @return string[] bekleyen dosya adları */
    public static function pending(): array
    {
        self::ensureTable();
        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $files = array_map('basename', glob(BASE_PATH . '/database/migrations/*.sql') ?: []);
        sort($files);
        return array_values(array_diff($files, $done));
    }

    /**
     * Hata olursa durur ve istisna atar; hatalı dosya kaydedilmez. Dosya tek tek ifadelere bölünerek
     * çalıştırılır, böylece çok ifadeli dosyanın ortasındaki hata gözden kaçmaz.
     * @return string[] uygulanan dosya adları
     */
    public static function run(): array
    {
        $ran = [];
        foreach (self::pending() as $name) {
            foreach (self::statements((string) file_get_contents(BASE_PATH . "/database/migrations/$name")) as $sql) {
                DB::pdo()->exec($sql);
            }
            DB::insert('migrations', ['name' => $name]);
            $ran[] = $name;
        }
        return $ran;
    }

    private static function ensureTable(): void
    {
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(190) PRIMARY KEY,
            ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** Yorum satırları atılır; ifadeler satır sonundaki ";" ile ayrılır. */
    private static function statements(string $sql): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        return array_values(array_filter(array_map('trim', preg_split('/;[ \t]*(?:--[^\n]*)?\r?\n/', $sql . "\n") ?: [])));
    }
}
