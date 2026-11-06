<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$cache ??= array_column(DB::all('SELECT `key`, `value` FROM settings'), 'value', 'key');
        return self::$cache[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::query(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [$key, $value]
            );
        }
        self::$cache = null;
    }
}
