<?php

declare(strict_types=1);

namespace Modules\Auth;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Microsoft;

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

    public function microsoft(): void
    {
        if (!Microsoft::loginEnabled()) {
            abort(404);
        }
        $_SESSION['_ms_state'] = bin2hex(random_bytes(16));
        $_SESSION['_ms_nonce'] = bin2hex(random_bytes(16));
        header('Location: ' . Microsoft::authorizeUrl($_SESSION['_ms_state'], $_SESSION['_ms_nonce']));
        exit;
    }

    /**
     * Kullanıcı Flow'da önceden tanımlı olmalı; otomatik hesap açılmaz.
     * İlk girişte e-posta ile eşleşir ve Microsoft nesne kimliği (oid) kaydedilir; sonraki girişler oid ile.
     */
    public function microsoftCallback(): void
    {
        $state = $_SESSION['_ms_state'] ?? '';
        $nonce = $_SESSION['_ms_nonce'] ?? '';
        unset($_SESSION['_ms_state'], $_SESSION['_ms_nonce']);

        if (!Microsoft::loginEnabled() || $state === '' || !hash_equals($state, (string) ($_GET['state'] ?? ''))) {
            flash('error', __('auth.ms_failed'));
            redirect('/login');
        }
        if (isset($_GET['error'])) {
            // Kullanıcı vazgeçtiyse sessizce dön; diğer hatalar (yönetici onayı vb.) gösterilir
            if ($_GET['error'] !== 'access_denied') {
                flash('error', __('auth.ms_error', ['error' => strtok((string) ($_GET['error_description'] ?? $_GET['error']), "\r\n")]));
            }
            redirect('/login');
        }

        try {
            $ms = Microsoft::exchangeCode((string) ($_GET['code'] ?? ''), $nonce);
        } catch (\Throwable $e) {
            flash('error', __('auth.ms_error', ['error' => $e->getMessage()]));
            redirect('/login');
        }

        $user = DB::one('SELECT * FROM users WHERE ms_object_id = ?', [$ms['oid']])
            ?? ($ms['email'] !== '' ? DB::one('SELECT * FROM users WHERE email = ? AND ms_object_id IS NULL', [$ms['email']]) : null);
        if (!$user || !$user['is_active']) {
            flash('error', __('auth.ms_no_user', ['email' => $ms['email']]));
            redirect('/login');
        }

        DB::update('users', ['ms_object_id' => $ms['oid'], 'ms_upn' => $ms['email'] ?: null], 'id = ?', [$user['id']]);
        Auth::login($user);
        $_SESSION['locale'] = $user['locale'];
        Audit::log('auth.login', null, null, ['via' => 'microsoft']);
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
            if (\App\Core\PortalAuth::check()) {
                DB::update('portal_users', ['locale' => $locale], 'id = ?', [\App\Core\PortalAuth::id()]);
            }
        }
        back();
    }
}
