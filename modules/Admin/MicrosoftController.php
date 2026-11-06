<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Audit;
use App\Services\Microsoft;
use App\Services\Secret;
use App\Services\Settings;
use Throwable;

final class MicrosoftController
{
    private const GUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function edit(): void
    {
        View::render('Admin::microsoft', [
            'title'  => __('ms.title'),
            'linked' => (int) DB::value('SELECT COUNT(*) FROM users WHERE ms_object_id IS NOT NULL'),
        ]);
    }

    public function update(): void
    {
        $tenant = trim((string) ($_POST['tenant_id'] ?? ''));
        $client = trim((string) ($_POST['client_id'] ?? ''));
        $secret = trim((string) ($_POST['client_secret'] ?? ''));

        $errors = [];
        if (!preg_match(self::GUID, $tenant)) {
            $errors['tenant_id'] = __('ms.guid_invalid');
        }
        if (!preg_match(self::GUID, $client)) {
            $errors['client_id'] = __('ms.guid_invalid');
        }
        // Secret boş bırakılırsa kayıtlı olan korunur; hiç yoksa zorunlu
        if ($secret === '' && !Settings::get('ms.client_secret')) {
            $errors['client_secret'] = __('validation.required');
        }
        if ($errors) {
            fail(new ValidationException($errors), '/settings/microsoft');
        }

        $values = [
            'ms.tenant_id'     => strtolower($tenant),
            'ms.client_id'     => strtolower($client),
            'ms.login_enabled' => !empty($_POST['login_enabled']) ? '1' : '0',
        ];
        if ($secret !== '') {
            $values['ms.client_secret'] = Secret::encrypt($secret);
        }
        // Kimlik bilgisi değiştiyse eski uygulama belirteci geçersizdir
        DB::query("DELETE FROM integration_tokens WHERE provider = 'microsoft' AND user_id IS NULL");
        Settings::set($values);

        Audit::log('settings.microsoft', 'settings', null, array_diff_key($values, ['ms.client_secret' => 1]));
        flash('success', __('app.saved'));
        redirect('/settings/microsoft');
    }

    /** Uygulama belirteci alınabiliyorsa Tenant ID, Client ID ve Secret doğrudur. */
    public function test(): void
    {
        DB::query("DELETE FROM integration_tokens WHERE provider = 'microsoft' AND user_id IS NULL");
        try {
            Microsoft::appToken();
            flash('success', __('ms.test_ok'));
        } catch (Throwable $e) {
            flash('error', __('ms.test_failed', ['error' => $e->getMessage()]));
        }
        redirect('/settings/microsoft');
    }

    public function reset(): void
    {
        Microsoft::reset();
        Audit::log('settings.microsoft_reset', 'settings');
        flash('success', __('ms.reset_done'));
        redirect('/settings/microsoft');
    }
}
