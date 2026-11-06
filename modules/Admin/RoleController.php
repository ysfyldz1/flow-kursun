<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\ValidationException;
use App\Core\View;
use App\Services\RoleService;

final class RoleController
{
    public function index(): void
    {
        View::render('Admin::roles/index', [
            'title' => __('roles.title'),
            'roles' => RoleService::list(),
        ]);
    }

    public function edit(?string $id = null): void
    {
        $role = $id ? (RoleService::find((int) $id) ?? abort(404)) : null;
        View::render('Admin::roles/form', [
            'title'       => $role ? localized($role) : __('roles.new'),
            'role'        => $role,
            'permissions' => RoleService::allPermissions(),
        ]);
    }

    public function save(?string $id = null): void
    {
        try {
            $id = RoleService::save($id ? (int) $id : null, $_POST);
        } catch (ValidationException $e) {
            fail($e, $id ? "/settings/roles/$id" : '/settings/roles/new');
        }
        flash('success', __('app.saved'));
        redirect("/settings/roles/$id");
    }

    public function delete(string $id): void
    {
        try {
            RoleService::delete((int) $id);
            flash('success', __('app.deleted'));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        redirect('/settings/roles');
    }
}
