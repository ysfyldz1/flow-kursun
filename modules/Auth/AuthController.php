<?php

declare(strict_types=1);

namespace Modules\Auth;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Audit;
use App\Services\Mailer;
use App\Services\Microsoft;
use App\Services\PasswordService;

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
        redirect(self::intended('/'));
    }

    public function microsoft(): void
    {
        if (!Microsoft::loginEnabled()) {
            abort(404);
        }
        $_SESSION['_ms_state'] = bin2hex(random_bytes(16));
        $_SESSION['_ms_nonce'] = bin2hex(random_bytes(16));
        $_SESSION['_ms_login_connect'] = Microsoft::loginConnects();
        header('Location: ' . Microsoft::authorizeUrl($_SESSION['_ms_state'], $_SESSION['_ms_nonce'], $_SESSION['_ms_login_connect']));
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
        $connect = !empty($_SESSION['_ms_connect']);
        $loginConnect = !empty($_SESSION['_ms_login_connect']);
        unset($_SESSION['_ms_state'], $_SESSION['_ms_nonce'], $_SESSION['_ms_connect'], $_SESSION['_ms_login_connect']);

        // Aynı yönlendirme adresi, giriş yapmış kullanıcının hesabını bağlaması için de kullanılır
        if ($connect && Auth::check()) {
            $this->connectCallback($state, $nonce);
        }
        if (Auth::check()) {
            redirect('/');
        }

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
            $ms = Microsoft::exchangeCode((string) ($_GET['code'] ?? ''), $nonce, $loginConnect);
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
        // Girişle birlikte hesap da bağlanır; olmazsa giriş yine geçerli, kişi profilinden bağlayabilir
        if (!empty($ms['token'])) {
            try {
                Microsoft::connectUser(DB::one('SELECT * FROM users WHERE id = ?', [$user['id']]), $ms);
            } catch (\Throwable $e) {
                error_log('Microsoft connect at login: ' . $e->getMessage());
            }
        }
        Audit::log('auth.login', null, null, ['via' => 'microsoft']);
        redirect(self::intended('/'));
    }

    /** Profilde "Microsoft 365 hesabımı bağla": kişinin kendi posta kutusu ve OneDrive'ı için izin istenir. */
    public function microsoftConnect(): void
    {
        if (!Microsoft::isConfigured()) {
            abort(404);
        }
        $_SESSION['_ms_state'] = bin2hex(random_bytes(16));
        $_SESSION['_ms_nonce'] = bin2hex(random_bytes(16));
        $_SESSION['_ms_connect'] = true;
        header('Location: ' . Microsoft::authorizeUrl($_SESSION['_ms_state'], $_SESSION['_ms_nonce'], true, auth()['ms_upn'] ?: auth()['email']));
        exit;
    }

    public function microsoftDisconnect(): void
    {
        Microsoft::disconnectUser((int) Auth::id());
        flash('success', __('ms.disconnected'));
        redirect('/account');
    }

    private function connectCallback(string $state, string $nonce): never
    {
        if ($state === '' || !hash_equals($state, (string) ($_GET['state'] ?? ''))) {
            flash('error', __('auth.ms_failed'));
            redirect('/account');
        }
        if (isset($_GET['error'])) {
            if ($_GET['error'] !== 'access_denied') {
                flash('error', __('ms.connect_error', ['error' => strtok((string) ($_GET['error_description'] ?? $_GET['error']), "\r\n")]));
            }
            redirect('/account');
        }
        try {
            Microsoft::connectUser(auth(), Microsoft::exchangeCode((string) ($_GET['code'] ?? ''), $nonce, true));
            flash('success', __('ms.connected'));
        } catch (\Throwable $e) {
            flash('error', __('ms.connect_error', ['error' => $e->getMessage()]));
        }
        redirect(self::intended('/account'));
    }

    /** Girişten önce istenen sayfa (Router yazar); yalnız site içi yol kabul edilir, bir kez kullanılır. */
    private static function intended(string $default): string
    {
        $to = (string) ($_SESSION['_intended'] ?? '');
        unset($_SESSION['_intended']);
        return preg_match('~^/(?![/\\])~', $to) ? $to : $default;
    }

    /** Profil: ad, dil ve Microsoft 365 bağlantısı. */
    public function account(): void
    {
        View::render('Auth::account', [
            'title'      => __('app.profile'),
            'user'       => auth(),
            'connection' => Microsoft::isConfigured() ? Microsoft::connection((int) Auth::id()) : false,
        ]);
    }

    public function changePassword(): void
    {
        if (!auth()['password']) {
            abort(404);
        }
        try {
            PasswordService::change(auth(), (string) ($_POST['current_password'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
        } catch (ValidationException $e) {
            fail($e, '/account');
        }
        flash('success', __('auth.password_changed'));
        redirect('/account');
    }

    public function showForgot(): void
    {
        if (!Mailer::delivers()) {
            abort(404);
        }
        View::render('Auth::forgot', ['title' => __('auth.forgot_title')], 'auth');
    }

    public function forgot(): void
    {
        if (!Mailer::delivers()) {
            abort(404);
        }
        PasswordService::sendReset((string) ($_POST['email'] ?? ''));
        flash('success', __('auth.forgot_sent'));
        redirect('/login');
    }

    public function showReset(string $token): void
    {
        $user = PasswordService::findByToken($token);
        if ($user) {
            \App\Core\Lang::set($user['locale']);
        }
        View::render('Auth::reset', ['title' => __('auth.reset_title'), 'user' => $user, 'token' => $token], 'auth');
    }

    public function reset(string $token): void
    {
        try {
            PasswordService::reset($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
        } catch (ValidationException $e) {
            fail($e, "/reset-password/$token");
        }
        flash('success', __('auth.reset_done'));
        redirect('/login');
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
