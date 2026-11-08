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
    $messages = [400 => 'error.500', 403 => 'error.403', 404 => 'error.404', 419 => 'error.419', 500 => 'error.500', 503 => 'error.503'];
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

/** Doğrulama hatasını ve girilen değerleri (şifreler hariç) bir sonraki isteğe taşır. */
function fail(\App\Core\ValidationException $e, string $to): never
{
    $_SESSION['_errors'] = $e->errors;
    $_SESSION['_old'] = array_diff_key($_POST, array_flip(['password', 'password_confirm', 'current_password', '_csrf']));
    redirect($to);
}

function error_for(string $key): string
{
    $msg = $GLOBALS['__errors'][$key] ?? null;
    return $msg ? '<small class="field-error">' . e($msg) . '</small>' : '';
}

function has_errors(): bool
{
    return !empty($GLOBALS['__errors']);
}

/** Kayıtta name_tr / name_en gibi iki dilli alanın geçerli dildeki değeri */
function localized(array $row, string $field = 'name'): string
{
    return (string) ($row[$field . '_' . Lang::locale()] ?? $row[$field . '_tr'] ?? '');
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

/** "2.500,50", "2.500" veya "2500.50" → sayı; boş → null; geçersiz → false */
function parse_money(mixed $raw): float|null|false
{
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);   // "2.500" → binlik ayraç
    }
    return is_numeric($s) && (float) $s >= 0 ? round((float) $s, 2) : false;
}

/** Form alanında gösterim: 2500.5 → "2500,50" (TR) / "2500.50" (EN) */
function money_input(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_numeric($value)) {
        return (string) $value;   // hatalı girişte kullanıcının yazdığını geri göster
    }
    return number_format((float) $value, 2, Lang::locale() === 'tr' ? ',' : '.', '');
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

/** Bugünün gün adı ve uzun tarihi, arayüz dilinde: ['Pazar', '8 Kasım 2026'] */
function today_label(): array
{
    $month = __('cal.month_' . date('n'));
    $date = Lang::locale() === 'tr' ? date('j') . " $month " . date('Y') : "$month " . date('j, Y');
    return [__('dash.day_' . date('N')), $date];
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

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function format_bytes(?int $bytes): string
{
    if ($bytes === null) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < count($units) - 1) {
        $n /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) $bytes : number_format($n, $n < 10 ? 1 : 0, ',', '.')) . ' ' . $units[$i];
}
