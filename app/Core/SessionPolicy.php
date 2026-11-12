<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Settings;

/**
 * Oturum süreleri: hareketsizlik (session.idle_minutes) ve girişten itibaren en uzun süre (session.max_hours).
 * Ofis ve portal oturumu için aynı kural geçerlidir. Oturum dosyaları storage/sessions'ta tutulur,
 * böylece sunucunun kendi temizliği (gc_maxlifetime) oturumu erken düşürmez.
 */
final class SessionPolicy
{
    public const IDLE_DEFAULT = 120;  // dakika
    public const MAX_DEFAULT  = 12;   // saat
    public const MAX_LIMIT    = 24;   // saat; dosya ve çerez ömrü bunu karşılar

    public static function start(): void
    {
        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        ini_set('session.gc_maxlifetime', (string) (self::MAX_LIMIT * 3600));

        session_name(config('session.name', 'flow_session'));
        session_set_cookie_params([
            'lifetime' => self::MAX_LIMIT * 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Girişte çağrılır: en uzun süre bu andan sayılır. */
    public static function begin(): void
    {
        $_SESSION['_auth_at'] = $_SESSION['_seen_at'] = time();
    }

    /** Her istekte: süre dolduysa oturumu kapatır, dolmadıysa son hareketi günceller. */
    public static function enforce(): void
    {
        if (empty($_SESSION['user_id']) && empty($_SESSION['portal_user_id'])) {
            return;
        }
        $now = time();
        $authAt = (int) ($_SESSION['_auth_at'] ?? $now);
        $seenAt = (int) ($_SESSION['_seen_at'] ?? $now);

        if ($now - $seenAt > self::idleMinutes() * 60 || $now - $authAt > self::maxHours() * 3600) {
            $locale = $_SESSION['locale'] ?? null;
            $_SESSION = [];
            session_regenerate_id(true);
            if ($locale) {
                $_SESSION['locale'] = $locale;
            }
            flash('error', __('auth.session_expired'));
            return;
        }
        $_SESSION['_auth_at'] = $authAt;
        $_SESSION['_seen_at'] = $now;
    }

    public static function idleMinutes(): int
    {
        return max(5, min(self::MAX_LIMIT * 60, (int) Settings::get('session.idle_minutes', self::IDLE_DEFAULT)));
    }

    public static function maxHours(): int
    {
        return max(1, min(self::MAX_LIMIT, (int) Settings::get('session.max_hours', self::MAX_DEFAULT)));
    }
}
