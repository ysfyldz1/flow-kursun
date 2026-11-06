<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\Auth;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Mailer;
use App\Services\Microsoft;
use App\Services\RoleService;
use App\Services\UserService;

final class UserController
{
    public function index(): void
    {
        View::render('Admin::users/index', [
            'title' => __('users.title'),
            'users' => UserService::list(),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $user = UserService::find((int) $id) ?? abort(404);
        $this->form($user);
    }

    public function store(): void
    {
        try {
            $id = UserService::create($_POST);
        } catch (ValidationException $e) {
            fail($e, '/settings/users/new');
        }

        if (!empty($_POST['send_welcome']) && Mailer::delivers()) {
            $user = UserService::find($id);
            $sent = Mailer::sendView($user['email'], $user['locale'], 'welcome', [
                'name'      => $user['name'],
                'email'     => $user['email'],
                'url'       => rtrim((string) config('app.url'), '/') . '/login',
                'microsoft' => Microsoft::loginEnabled(),
            ]);
            flash($sent ? 'success' : 'error', __($sent ? 'users.welcome_sent' : 'users.welcome_failed', ['email' => $user['email']]));
            redirect("/settings/users/$id");
        }
        flash('success', __('app.saved'));
        redirect("/settings/users/$id");
    }

    public function update(string $id): void
    {
        UserService::find((int) $id) ?? abort(404);
        try {
            UserService::update((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/settings/users/$id");
        }
        flash('success', __('app.saved'));
        redirect("/settings/users/$id");
    }

    public function toggle(string $id): void
    {
        $user = UserService::find((int) $id) ?? abort(404);
        try {
            UserService::setActive((int) $id, !$user['is_active'], Auth::id());
            flash('success', __('app.saved'));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        redirect('/settings/users');
    }

    private function form(?array $user): void
    {
        View::render('Admin::users/form', [
            'title' => $user ? $user['name'] : __('users.new'),
            'user'  => $user,
            'roles' => RoleService::list(),
        ]);
    }
}
