<?php

declare(strict_types=1);

namespace App\Core;

/** Müvekkil portalı oturumu; ofis oturumundan (Auth) bağımsızdır. */
final class PortalAuth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = DB::one(
            'SELECT pu.* FROM portal_users pu JOIN clients c ON c.id = pu.client_id
             WHERE pu.email = ? AND pu.is_active = 1 AND c.is_active = 1',
            [mb_strtolower(trim($email))]
        );
        if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
            return false;
        }
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['portal_user_id'] = (int) $user['id'];
        $_SESSION['locale'] = $user['locale'];
        DB::update('portal_users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        self::$user = null;
    }

    public static function logout(): void
    {
        unset($_SESSION['portal_user_id']);
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

    public static function clientId(): ?int
    {
        return isset(self::user()['client_id']) ? (int) self::user()['client_id'] : null;
    }

    public static function user(): ?array
    {
        if (self::$user === null && !empty($_SESSION['portal_user_id'])) {
            self::$user = DB::one(
                'SELECT pu.*, c.name AS client_name, c.code AS client_code
                 FROM portal_users pu JOIN clients c ON c.id = pu.client_id
                 WHERE pu.id = ? AND pu.is_active = 1 AND c.is_active = 1',
                [$_SESSION['portal_user_id']]
            );
            if (self::$user === null) {
                unset($_SESSION['portal_user_id']);
            }
        }
        return self::$user;
    }
}
