<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Proje ve müvekkil görünürlüğü. projects.all yetkisi olan her şeyi görür; olmayan yalnız sorumlusu ya da
 * ekip üyesi olduğu projeleri ve bu projelerin müvekkillerini (ya da kendi açtığı müvekkilleri) görür.
 * Kural yalnızca burada tanımlıdır; listeler SQL koşulunu, sayfalar can* kontrollerini kullanır.
 */
final class Access
{
    public static function allProjects(): bool
    {
        return Auth::can('projects.all');
    }

    /** Proje görünürlüğü için SQL koşulu ve parametreleri; $alias projects tablosunun takma adı. */
    public static function projectWhere(string $alias = 'p'): array
    {
        if (self::allProjects()) {
            return ['1 = 1', []];
        }
        return [
            "($alias.responsible_user_id = ? OR EXISTS (SELECT 1 FROM project_members pm_ WHERE pm_.project_id = $alias.id AND pm_.user_id = ?))",
            [Auth::id(), Auth::id()],
        ];
    }

    /** Müvekkil görünürlüğü: görünen bir projesi olan ya da kişinin kendi açtığı müvekkil. */
    public static function clientWhere(string $alias = 'c'): array
    {
        if (self::allProjects()) {
            return ['1 = 1', []];
        }
        [$p, $params] = self::projectWhere('vp_');
        return [
            "($alias.created_by = ? OR EXISTS (SELECT 1 FROM projects vp_ WHERE vp_.client_id = $alias.id AND $p))",
            array_merge([Auth::id()], $params),
        ];
    }

    public static function canSeeProject(?int $projectId): bool
    {
        if (!$projectId) {
            return false;
        }
        [$where, $params] = self::projectWhere();
        return (bool) DB::value("SELECT 1 FROM projects p WHERE p.id = ? AND $where", array_merge([$projectId], $params));
    }

    public static function canSeeClient(?int $clientId): bool
    {
        if (!$clientId) {
            return false;
        }
        [$where, $params] = self::clientWhere();
        return (bool) DB::value("SELECT 1 FROM clients c WHERE c.id = ? AND $where", array_merge([$clientId], $params));
    }

    /** Takvim kaydı: projesiz, projesi görünen ya da kişinin katıldığı kayıt. */
    public static function eventWhere(string $alias = 'e'): array
    {
        if (self::allProjects()) {
            return ['1 = 1', []];
        }
        [$p, $params] = self::projectWhere('ep_');
        return [
            "($alias.project_id IS NULL OR EXISTS (SELECT 1 FROM projects ep_ WHERE ep_.id = $alias.project_id AND $p)
              OR EXISTS (SELECT 1 FROM event_attendees ea_ WHERE ea_.event_id = $alias.id AND ea_.user_id = ?))",
            array_merge($params, [Auth::id()]),
        ];
    }
}
