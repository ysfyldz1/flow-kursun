<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Boşluksuz, çakışmasız sıra numarası. Çağıran transaction içinde olmalı. */
final class Counter
{
    public static function next(string $name): int
    {
        DB::query('INSERT IGNORE INTO counters (name, value) VALUES (?, 0)', [$name]);
        $value = (int) DB::value('SELECT value FROM counters WHERE name = ? FOR UPDATE', [$name]) + 1;
        DB::update('counters', ['value' => $value], 'name = ?', [$name]);
        return $value;
    }
}
