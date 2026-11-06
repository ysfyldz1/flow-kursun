<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Audit;
use App\Services\Mailer;
use App\Services\Microsoft;
use App\Services\Secret;
use App\Services\Settings;
use Throwable;

final class MailController
{
    public function edit(): void
    {
        View::render('Admin::mail', [
            'title' => __('mail.title'),
            'log'   => DB::all('SELECT * FROM mail_log ORDER BY id DESC LIMIT 10'),
        ]);
    }

    public function update(): void
    {
        $driver = (string) ($_POST['driver'] ?? 'log');
        $from = mb_strtolower(trim((string) ($_POST['from_address'] ?? '')));
        $host = trim((string) ($_POST['smtp_host'] ?? ''));
        $port = (string) ($_POST['smtp_port'] ?? '587');
        $secure = (string) ($_POST['smtp_secure'] ?? 'tls');

        $errors = [];
        if (!in_array($driver, Mailer::DRIVERS, true)) {
            $errors['driver'] = __('validation.required');
        }
        if ($driver === 'graph' && !Microsoft::isConfigured()) {
            $errors['driver'] = __('mail.graph_not_configured');
        }
        if ($driver !== 'log' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors['from_address'] = __('validation.email');
        }
        if ($driver === 'smtp' && $host === '') {
            $errors['smtp_host'] = __('validation.required');
        }
        if ($driver === 'smtp' && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
            $errors['smtp_port'] = __('validation.number');
        }
        if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
            $secure = 'tls';
        }
        if ($errors) {
            fail(new ValidationException($errors), '/settings/mail');
        }

        $values = [
            'mail.driver'       => $driver,
            'mail.from_address' => $from ?: null,
            'mail.smtp_host'    => $host ?: null,
            'mail.smtp_port'    => $port,
            'mail.smtp_secure'  => $secure,
            'mail.smtp_user'    => trim((string) ($_POST['smtp_user'] ?? '')) ?: null,
        ];
        $pass = (string) ($_POST['smtp_pass'] ?? '');
        if ($pass !== '') {
            $values['mail.smtp_pass'] = Secret::encrypt($pass);
        }
        Settings::set($values);

        Audit::log('settings.mail', 'settings', null, $values);
        flash('success', __('app.saved'));
        redirect('/settings/mail');
    }

    /** Kayıtlı ayarlarla oturumdaki kullanıcıya deneme e-postası gönderir. */
    public function test(): void
    {
        $to = (string) Auth::user()['email'];
        try {
            Mailer::send($to, __('mail.test_subject'), '<p>' . e(__('mail.test_body')) . '</p>', true);
            flash('success', __('mail.test_ok', ['email' => $to]));
        } catch (Throwable $e) {
            flash('error', __('mail.test_failed', ['error' => $e->getMessage()]));
        }
        redirect('/settings/mail');
    }
}
