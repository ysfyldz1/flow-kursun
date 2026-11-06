<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ValidationException;

final class RoleService
{
    public static function list(): array
    {
        return DB::all(
            'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.is_active = 1) AS user_count
             FROM roles r ORDER BY r.id'
        );
    }

    public static function find(int $id): ?array
    {
        $role = DB::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if ($role) {
            $role['permissions'] = array_column(DB::all('SELECT permission FROM role_permissions WHERE role_id = ?', [$id]), 'permission');
        }
        return $role;
    }

    public static function allPermissions(): array
    {
        return require BASE_PATH . '/app/permissions.php';
    }

    public static function save(?int $id, array $input, string $channel = 'web'): int
    {
        $role = $id ? self::find($id) : null;
        if ($role && $role['slug'] === 'admin') {
            throw new ValidationException(['permissions' => __('roles.admin_locked')]);
        }

        $nameTr = trim((string) ($input['name_tr'] ?? ''));
        $nameEn = trim((string) ($input['name_en'] ?? '')) ?: $nameTr;
        if ($nameTr === '') {
            throw new ValidationException(['name_tr' => __('validation.required')]);
        }

        // Yalnızca tanımlı izinler kabul edilir
        $valid = array_merge(...array_values(self::allPermissions()));
        $perms = array_values(array_intersect((array) ($input['permissions'] ?? []), $valid));

        return DB::transaction(function () use ($id, $nameTr, $nameEn, $perms, $channel) {
            if ($id) {
                DB::update('roles', ['name_tr' => $nameTr, 'name_en' => $nameEn], 'id = ?', [$id]);
            } else {
                $id = DB::insert('roles', [
                    'slug'    => 'role_' . bin2hex(random_bytes(4)),
                    'name_tr' => $nameTr,
                    'name_en' => $nameEn,
                ]);
            }
            DB::query('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
            foreach ($perms as $p) {
                DB::insert('role_permissions', ['role_id' => $id, 'permission' => $p]);
            }
            Audit::log('role.saved', 'role', $id, ['permissions' => $perms], $channel);
            return $id;
        });
    }

    public static function delete(int $id, string $channel = 'web'): void
    {
        $role = self::find($id);
        if (!$role || $role['is_system']) {
            throw new ValidationException(['role' => __('roles.system_locked')]);
        }
        if (DB::value('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id])) {
            throw new ValidationException(['role' => __('roles.in_use')]);
        }
        DB::query('DELETE FROM roles WHERE id = ?', [$id]);
        Audit::log('role.deleted', 'role', $id, ['name' => $role['name_tr']], $channel);
    }
}
