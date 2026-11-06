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
            if (self::$user === null) {
                unset($_SESSION['user_id']);
            }
        }
        return self::$user;
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
