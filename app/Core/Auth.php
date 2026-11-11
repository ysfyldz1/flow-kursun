<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = DB::one('SELECT * FROM users WHERE email = ? AND is_active = 1', [mb_strtolower(trim($email))]);
        if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
            return false;
        }
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['session_epoch'] = (int) ($user['session_epoch'] ?? 0);
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        self::$user = null;
        self::$permissions = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function user(): ?array
    {
        if (self::$user === null && !empty($_SESSION['user_id'])) {
            self::$user = DB::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
            // Şifre başka yerden değiştiyse bu oturum kapanır
            if (self::$user !== null && (int) (self::$user['session_epoch'] ?? 0) !== (int) ($_SESSION['session_epoch'] ?? 0)) {
                self::$user = null;
            }
            if (self::$user === null) {
                unset($_SESSION['user_id']);
            } elseif (array_key_exists('last_seen_at', self::$user)   // migration 021 uygulanmadan kod gelirse site çökmesin
                && (!self::$user['last_seen_at'] ||strtotime(self::$user['last_seen_at']) < time() - 600)) {
                // Özet e-postası "kaç gündür girmedi"yi buradan anlar; her istekte değil, 10 dakikada bir yazılır
                DB::update('users', ['last_seen_at' => date('Y-m-d H:i:s')], 'id = ?', [self::$user['id']]);
            }
        }
        return self::$user;
    }

    /** Yalnız komut satırı (cron): yetki kontrolleri ve "benim" sorguları bu kişi için çalışır. */
    public static function actAs(?array $user): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new \LogicException('actAs yalnız komut satırında');
        }
        self::$user = $user;
        self::$permissions = null;
    }

    /** Kişi kendi şifresini değiştirdiğinde o anki oturumu açık tutar. */
    public static function keepSession(int $userId, int $epoch): void
    {
        if ((int) ($_SESSION['user_id'] ?? 0) === $userId) {
            session_regenerate_id(true);
            $_SESSION['session_epoch'] = $epoch;
            self::$user = null;
        }
    }

    /** Rol koda gömülü değil; rolün yetki listesine bakılır. */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if (self::$permissions === null) {
            self::$permissions = array_column(
                DB::all('SELECT permission FROM role_permissions WHERE role_id = ?', [$user['role_id']]),
                'permission'
            );
        }
        return in_array('*', self::$permissions, true) || in_array($permission, self::$permissions, true);
    }
}
