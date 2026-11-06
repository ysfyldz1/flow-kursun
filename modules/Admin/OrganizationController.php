<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\ValidationException;
use App\Core\View;
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
            'billing.vat_rate'         => (string) ($vat ?? 0),
            'billing.withholding_rate' => (string) ($wh ?? 0),
            'billing.due_days'         => $dueDays,
            'billing.bank_accounts'    => $text('bank_accounts'),
            'portal.show_hours'        => !empty($_POST['portal_show_hours']) ? '1' : '0',
        ];

        if ($ext) {
            foreach (glob(BASE_PATH . '/storage/uploads/logo.*') as $old) {
                unlink($old);
            }
            move_uploaded_file($logo['tmp_name'], BASE_PATH . "/storage/uploads/logo.$ext");
            $values['org.logo'] = "logo.$ext";
        } elseif (!empty($_POST['remove_logo'])) {
            foreach (glob(BASE_PATH . '/storage/uploads/logo.*') as $old) {
                unlink($old);
            }
            $values['org.logo'] = null;
        }

        Settings::set($values);
        Audit::log('settings.updated', 'settings', null, $values);
        flash('success', __('app.saved'));
        redirect('/settings/organization');
    }

    /** Logo web kökü dışında durur; buradan sunulur. */
    public function logo(): void
    {
        $file = Settings::get('org.logo');
        $path = $file ? BASE_PATH . '/storage/uploads/' . basename($file) : null;
        if (!$path || !is_file($path)) {
            abort(404);
        }
        header('Content-Type: ' . mime_content_type($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
    }
}
