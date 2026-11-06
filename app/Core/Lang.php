<?php

declare(strict_types=1);

namespace App\Core;

final class Lang
{
    private static string $locale = 'tr';
    private static array $lines = [];

    public static function set(string $locale): void
    {
        if (!in_array($locale, config('app.locales', ['tr']), true)) {
            $locale = config('app.locale', 'tr');
        }
        self::$locale = $locale;
        self::$lines = require BASE_PATH . "/lang/{$locale}.php";
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function get(string $key, array $replace = []): string
    {
        $line = self::$lines[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $line = str_replace(':' . $k, (string) $v, $line);
        }
        return $line;
    }
}
