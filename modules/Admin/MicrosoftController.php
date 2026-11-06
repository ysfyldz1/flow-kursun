<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Audit;
use App\Services\DocumentService;
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

    /** Belge deposu: drive kimliği kaydederken çözülür, böylece yanlış adres hemen fark edilir. */
    public function documents(): void
    {
        $provider = (string) ($_POST['docs_provider'] ?? '');
        $target = trim((string) ($_POST['docs_target'] ?? ''));
        $root = trim((string) ($_POST['docs_root'] ?? '')) ?: 'Flow';

        if (!in_array($provider, DocumentService::PROVIDERS, true)) {
            Settings::set(['docs.provider' => null, 'docs.drive_id' => null]);
            flash('success', __('app.saved'));
            redirect('/settings/microsoft');
        }
        $error = match (true) {
            $target === ''                                                            => __('validation.required'),
            $provider === 'onedrive' && !filter_var($target, FILTER_VALIDATE_EMAIL)  => __('validation.email'),
            default                                                                  => null,
        };
        if (!$error) {
            try {
                $driveId = DocumentService::resolveDrive($provider, $target);
            } catch (Throwable $e) {
                $error = __('docs.drive_not_found', ['error' => $e->getMessage()]);
            }
        }
        if ($error) {
            fail(new ValidationException(['docs_target' => $error]), '/settings/microsoft');
        }

        // Depo değişirse eski klasör bağları ve paylaşımlar yeni depoda geçersizdir
        if ($driveId !== Settings::get('docs.drive_id') || $root !== Settings::get('docs.root', 'Flow')) {
            DB::query('UPDATE projects SET drive_folder_id = NULL');
            DB::query('DELETE FROM document_shares');
        }
        Settings::set(['docs.provider' => $provider, 'docs.target' => $target, 'docs.root' => $root, 'docs.drive_id' => $driveId]);
        Audit::log('settings.documents', 'settings', null, ['provider' => $provider, 'target' => $target, 'root' => $root]);
        flash('success', __('docs.connected'));
        redirect('/settings/microsoft');
    }

    public function calendar(): void
    {
        $on = !empty($_POST['calendar_outlook']);
        Settings::set(['calendar.outlook' => $on ? '1' : '0']);
        Audit::log('settings.calendar', 'settings', null, ['outlook' => $on]);
        flash('success', __('app.saved'));
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
