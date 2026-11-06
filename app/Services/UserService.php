<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ValidationException;

final class UserService
{
    public const CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP'];

    public static function list(): array
    {
        return DB::all(
            'SELECT u.*, r.name_tr AS role_tr, r.name_en AS role_en
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.is_active DESC, u.name'
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function create(array $input, string $channel = 'web'): int
    {
        $data = self::validate($input, null);
        $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        $id = DB::insert('users', $data);
        Audit::log('user.created', 'user', $id, ['email' => $data['email']], $channel);
        return $id;
    }

    public static function update(int $id, array $input, string $channel = 'web'): void
    {
        $data = self::validate($input, $id);
        if (!empty($input['password'])) {
            $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        DB::update('users', $data, 'id = ?', [$id]);
        unset($data['password']);
        Audit::log('user.updated', 'user', $id, $data, $channel);
    }

    /** Kullanıcı silinmez, pasife alınır; geçmiş kayıtları (zaman, döküm) bozulmasın. */
    public static function setActive(int $id, bool $active, ?int $actorId, string $channel = 'web'): void
    {
        if (!$active && $id === $actorId) {
            throw new ValidationException(['is_active' => __('users.cannot_deactivate_self')]);
        }
        if (!$active && self::isLastAdmin($id)) {
            throw new ValidationException(['is_active' => __('users.last_admin')]);
        }
        DB::update('users', ['is_active' => (int) $active], 'id = ?', [$id]);
        Audit::log($active ? 'user.activated' : 'user.deactivated', 'user', $id, [], $channel);
    }

    private static function isLastAdmin(int $id): bool
    {
        $adminRole = (int) DB::value("SELECT id FROM roles WHERE slug = 'admin'");
        $user = self::find($id);
        if (!$user || (int) $user['role_id'] !== $adminRole) {
            return false;
        }
        return (int) DB::value('SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1', [$adminRole]) <= 1;
    }

    private static function validate(array $in, ?int $id): array
    {
        $errors = [];
        $data = [
            'name'          => trim((string) ($in['name'] ?? '')),
            'email'         => mb_strtolower(trim((string) ($in['email'] ?? ''))),
            'title'         => trim((string) ($in['title'] ?? '')) ?: null,
            'role_id'       => (int) ($in['role_id'] ?? 0),
            'locale'        => (string) ($in['locale'] ?? 'tr'),
            'hourly_rate'   => parse_money($in['hourly_rate'] ?? ''),
            'rate_currency' => ($in['rate_currency'] ?? '') ?: null,
        ];

        if ($data['name'] === '') {
            $errors['name'] = __('validation.required');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email');
        } elseif (DB::value('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $id ?? 0])) {
            $errors['email'] = __('validation.email_taken');
        }
        if (!DB::value('SELECT id FROM roles WHERE id = ?', [$data['role_id']])) {
            $errors['role_id'] = __('validation.required');
        } elseif ($id && self::isLastAdmin($id) && $data['role_id'] !== (int) DB::value("SELECT id FROM roles WHERE slug = 'admin'")) {
            $errors['role_id'] = __('users.last_admin');
        }
        if (!in_array($data['locale'], config('app.locales'), true)) {
            $errors['locale'] = __('validation.required');
        }
        if ($data['rate_currency'] !== null && !in_array($data['rate_currency'], self::CURRENCIES, true)) {
            $errors['rate_currency'] = __('validation.required');
        }
        if ($data['hourly_rate'] === false) {
            $errors['hourly_rate'] = __('validation.number');
        } elseif ($data['hourly_rate'] !== null && $data['rate_currency'] === null) {
            $errors['rate_currency'] = __('validation.required');
        }
        $password = (string) ($in['password'] ?? '');
        if ($id === null || $password !== '') {
            if (mb_strlen($password) < 8) {
                $errors['password'] = __('validation.password_min');
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
        if (!\App\Core\Auth::can('rates.view')) {
            unset($data['hourly_rate'], $data['rate_currency']);   // yetkisi olmayan mevcut ücrete dokunamaz
        }
        return $data;
    }
}
