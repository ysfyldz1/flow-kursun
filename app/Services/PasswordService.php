<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/** Kullanıcının kendi şifresi: profilden değiştirme ve e-postayla sıfırlama. */
final class PasswordService
{
    private const RESET_MINUTES = 60;
    private const RESEND_GAP_MINUTES = 5;   // aynı adrese art arda posta yağdırılmasın

    /** Şifreyi yazar, bekleyen sıfırlama bağlantısını iptal eder ve kişinin diğer oturumlarını kapatır. */
    public static function set(int $userId, string $password): void
    {
        $epoch = (int) DB::one('SELECT session_epoch FROM users WHERE id = ?', [$userId])['session_epoch'] + 1;
        DB::update('users', [
            'password'         => password_hash($password, PASSWORD_DEFAULT),
            'session_epoch'    => $epoch,
            'reset_token_hash' => null,
            'reset_expires_at' => null,
        ], 'id = ?', [$userId]);
        Auth::keepSession($userId, $epoch);
    }

    public static function change(array $user, string $current, string $password, string $confirm): void
    {
        $errors = self::validate($password, $confirm);
        if (!password_verify($current, (string) $user['password'])) {
            sleep(1);
            $errors['current_password'] = __('auth.current_wrong');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        self::set((int) $user['id'], $password);
        Audit::log('auth.password_changed', 'user', (int) $user['id']);
    }

    /** Hesap yoksa, pasifse ya da yalnızca Microsoft ile giriyorsa sessizce geçer; adresin kayıtlı olup olmadığı belli olmaz. */
    public static function sendReset(string $email): void
    {
        $user = DB::one('SELECT * FROM users WHERE email = ? AND is_active = 1', [mb_strtolower(trim($email))]);
        if (!$user || !$user['password']) {
            return;
        }
        $lifetime = self::RESET_MINUTES - self::RESEND_GAP_MINUTES;
        if ($user['reset_expires_at'] && strtotime($user['reset_expires_at']) > strtotime("+$lifetime minutes")) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        DB::update('users', [
            'reset_token_hash' => hash('sha256', $token),
            'reset_expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::RESET_MINUTES . ' minutes')),
        ], 'id = ?', [$user['id']]);

        $sent = Mailer::sendView($user['email'], $user['locale'], 'password_reset', [
            'name'    => $user['name'],
            'url'     => rtrim((string) config('app.url'), '/') . '/reset-password/' . $token,
            'minutes' => self::RESET_MINUTES,
        ]);
        if (!$sent) {
            DB::update('users', ['reset_token_hash' => null, 'reset_expires_at' => null], 'id = ?', [$user['id']]);
        }
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $user = DB::one('SELECT * FROM users WHERE reset_token_hash = ? AND is_active = 1', [hash('sha256', $token)]);
        return $user && strtotime($user['reset_expires_at']) > time() ? $user : null;
    }

    public static function reset(string $token, string $password, string $confirm): void
    {
        $user = self::findByToken($token) ?? throw new ValidationException(['token' => __('auth.reset_invalid')]);
        if ($errors = self::validate($password, $confirm)) {
            throw new ValidationException($errors);
        }
        self::set((int) $user['id'], $password);
        Audit::log('auth.password_reset', 'user', (int) $user['id']);
    }

    private static function validate(string $password, string $confirm): array
    {
        if (mb_strlen($password) < 8) {
            return ['password' => __('validation.password_min')];
        }
        return $password === $confirm ? [] : ['password_confirm' => __('portal.password_mismatch')];
    }
}
