<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\ValidationException;
use App\Core\View;
use App\Services\AppIcon;
use App\Services\Audit;
use App\Services\Settings;

final class OrganizationController
{
    private const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function edit(): void
    {
        View::render('Admin::organization', ['title' => __('org.title')]);
    }

    public function update(): void
    {
        $name   = trim((string) ($_POST['org_name'] ?? ''));
        $locale = (string) ($_POST['org_locale'] ?? 'tr');
        $color  = (string) ($_POST['org_primary_color'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors['org_name'] = __('validation.required');
        }
        if (!in_array($locale, config('app.locales'), true)) {
            $errors['org_locale'] = __('validation.required');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $errors['org_primary_color'] = __('validation.color');
        }

        $vat = parse_money($_POST['vat_rate'] ?? '0');
        $wh = parse_money($_POST['withholding_rate'] ?? '0');
        $dueDays = (string) ($_POST['due_days'] ?? '15');
        if ($vat === false || $vat > 100) {
            $errors['vat_rate'] = __('validation.number');
        }
        if ($wh === false || $wh > 100) {
            $errors['withholding_rate'] = __('validation.number');
        }
        if (!ctype_digit($dueDays) || (int) $dueDays > 365) {
            $errors['due_days'] = __('validation.number');
        }

        $logo = $_FILES['logo'] ?? null;
        $ext = null;
        if ($logo && $logo['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($logo['tmp_name']);
            $ext = self::LOGO_TYPES[$mime] ?? null;
            if (!$ext) {
                $errors['logo'] = __('org.logo_type');
            } elseif ($logo['size'] > 2 * 1024 * 1024) {
                $errors['logo'] = __('org.logo_size');
            }
        } elseif ($logo && $logo['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['logo'] = __('org.logo_upload_failed');
        }

        $loginImage = $_FILES['login_image'] ?? null;
        $loginExt = null;
        if ($loginImage && $loginImage['error'] === UPLOAD_ERR_OK) {
            $loginExt = self::LOGO_TYPES[mime_content_type($loginImage['tmp_name'])] ?? null;
            if (!$loginExt) {
                $errors['login_image'] = __('org.logo_type');
            } elseif ($loginImage['size'] > 5 * 1024 * 1024) {
                $errors['login_image'] = __('org.login_image_size');
            }
        } elseif ($loginImage && $loginImage['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['login_image'] = __('org.logo_upload_failed');
        }

        if ($errors) {
            fail(new ValidationException($errors), '/settings/organization');
        }

        $text = fn (string $k) => trim((string) ($_POST[$k] ?? '')) ?: null;
        $values = [
            'org.name'                 => $name,
            'org.locale'               => $locale,
            'org.primary_color'        => strtolower($color),
            'org.address'              => $text('org_address'),
            'org.tax_info'             => $text('org_tax_info'),
            'org.login_tagline'        => $text('org_login_tagline'),
            'billing.vat_rate'         => (string) ($vat ?? 0),
            'billing.withholding_rate' => (string) ($wh ?? 0),
            'billing.due_days'         => $dueDays,
            'billing.bank_accounts'    => $text('bank_accounts'),
            'portal.show_hours'        => !empty($_POST['portal_show_hours']) ? '1' : '0',
            'time.format'              => ($_POST['time_format'] ?? '') === 'hm' ? 'hm' : 'decimal',
        ];

        if ($ext) {
            $target = BASE_PATH . "/storage/uploads/logo.$ext";
            if (!move_uploaded_file($logo['tmp_name'], $target)) {
                fail(new ValidationException(['logo' => __('org.logo_upload_failed')]), '/settings/organization');
            }
            foreach (glob(BASE_PATH . '/storage/uploads/logo.*') as $old) {
                if (basename($old) !== "logo.$ext") {
                    unlink($old);
                }
            }
            $values['org.logo'] = "logo.$ext";
        } elseif (!empty($_POST['remove_logo'])) {
            foreach (glob(BASE_PATH . '/storage/uploads/logo.*') as $old) {
                unlink($old);
            }
            $values['org.logo'] = null;
        }

        if ($loginExt) {
            if (!move_uploaded_file($loginImage['tmp_name'], BASE_PATH . "/storage/uploads/login-image.$loginExt")) {
                fail(new ValidationException(['login_image' => __('org.logo_upload_failed')]), '/settings/organization');
            }
            foreach (glob(BASE_PATH . '/storage/uploads/login-image.*') as $old) {
                if (basename($old) !== "login-image.$loginExt") {
                    unlink($old);
                }
            }
            $values['org.login_image'] = "login-image.$loginExt";
        } elseif (!empty($_POST['remove_login_image'])) {
            foreach (glob(BASE_PATH . '/storage/uploads/login-image.*') as $old) {
                unlink($old);
            }
            $values['org.login_image'] = null;
        }

        // Uygulama ikonu: yüklenirse tüm boyutlar üretilir; kaldırılırsa ya da varsayılan ikon kullanılıyorken
        // ad/renk değişirse varsayılan yeniden üretilir
        $icon = $_FILES['app_icon'] ?? null;
        $nameOrColorChanged = $name !== Settings::get('org.name') || strtolower($color) !== Settings::get('org.primary_color');
        Settings::set($values);
        if ($icon && $icon['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($err = AppIcon::fromUpload($icon)) {
                fail(new ValidationException(['app_icon' => $err]), '/settings/organization');
            }
            Settings::set(['org.icon_custom' => '1']);
        } elseif (!empty($_POST['remove_icon']) || ($nameOrColorChanged && Settings::get('org.icon_custom') !== '1')) {
            AppIcon::reset();
        }
        Audit::log('settings.updated', 'settings', null, $values);
        flash('success', __('app.saved'));
        redirect('/settings/organization');
    }

    /** Logo web kökü dışında durur; buradan sunulur. */
    public function logo(): void
    {
        $this->serve('org.logo');
    }

    public function loginImage(): void
    {
        $this->serve('org.login_image');
    }

    private function serve(string $setting): void
    {
        $file = Settings::get($setting);
        $path = $file ? BASE_PATH . '/storage/uploads/' . basename($file) : null;
        if (!$path || !is_file($path)) {
            abort(404);
        }
        // Adres sabit; görsel değişince eskisi görünmesin diye tarayıcı her seferinde doğrular
        $etag = '"' . filemtime($path) . '-' . filesize($path) . '"';
        header('Cache-Control: no-cache');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }
        header('Content-Type: ' . mime_content_type($path));
        readfile($path);
    }
}
