<?php

declare(strict_types=1);

namespace Modules\Auth;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;

final class AuthController
{
    public function showLogin(): void
    {
        View::render('Auth::login', ['title' => __('auth.login')], 'auth');
    }

    public function login(): void
    {
        $email = (string) ($_POST['email'] ?? '');

        // Basit kaba kuvvet koruması: oturum başına 5 hatalı denemede 5 dakika bekletilir
        $lock = $_SESSION['_login_lock'] ?? ['count' => 0, 'until' => 0];
        if ($lock['until'] > time()) {
            flash('error', __('auth.throttled'));
            $_SESSION['_old'] = ['email' => $email];
            redirect('/login');
        }

        if (!Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            $lock['count']++;
            if ($lock['count'] >= 5) {
                $lock = ['count' => 0, 'until' => time() + 300];
            }
            $_SESSION['_login_lock'] = $lock;
            flash('error', __('auth.failed'));
            $_SESSION['_old'] = ['email' => $email];
            redirect('/login');
        }

        unset($_SESSION['_login_lock']);
        $_SESSION['locale'] = Auth::user()['locale'];
        Audit::log('auth.login');
        redirect('/');
    }

    public function logout(): void
    {
        Audit::log('auth.logout');
        Auth::logout();
        redirect('/login');
    }

    public function locale(): void
    {
        $locale = (string) ($_POST['locale'] ?? '');
        if (in_array($locale, config('app.locales', ['tr']), true)) {
            $_SESSION['locale'] = $locale;
            if (Auth::check()) {
                DB::update('users', ['locale' => $locale], 'id = ?', [Auth::id()]);
            }
        }
        back();
    }
}
