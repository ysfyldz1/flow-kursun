<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\View;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function __(string $key, array $replace = []): string
{
    return Lang::get($key, $replace);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 0;
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $ref);
    exit;
}

function abort(int $code): never
{
    http_response_code($code);
    $messages = [403 => 'error.403', 404 => 'error.404', 419 => 'error.419', 500 => 'error.500'];
    View::render('error', ['code' => $code, 'message' => __($messages[$code] ?? 'error.500')], null);
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function auth(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return (string) ($GLOBALS['__old'][$key] ?? $default);
}

function request_is(string $prefix): bool
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
}

function format_money(float $amount, string $currency = 'TRY'): string
{
    $symbols = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
    $symbol = $symbols[$currency] ?? $currency;
    if (Lang::locale() === 'tr') {
        return number_format($amount, 2, ',', '.') . ' ' . $symbol;
    }
    return $symbol . number_format($amount, 2, '.', ',');
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    $format = Lang::locale() === 'tr' ? 'd.m.Y' : 'M j, Y';
    return date($withTime ? $format . ' H:i' : $format, $ts);
}

/** Süre her zaman 6 dakikanın katına yukarı yuvarlanır (0,1 saat). */
function round_minutes(int $minutes): int
{
    return (int) (ceil(max($minutes, 0) / 6) * 6);
}

function icon(string $name, string $class = 'icon'): string
{
    $file = BASE_PATH . "/views/icons/{$name}.svg";
    if (!is_file($file)) {
        return '';
    }
    return str_replace('<svg', '<svg class="' . e($class) . '"', file_get_contents($file));
}
