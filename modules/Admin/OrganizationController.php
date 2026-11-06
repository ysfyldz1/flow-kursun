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

        $values = ['org.name' => $name, 'org.locale' => $locale, 'org.primary_color' => strtolower($color)];

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
