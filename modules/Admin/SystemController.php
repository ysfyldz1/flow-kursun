<?php

declare(strict_types=1);

namespace Modules\Admin;

use App\Core\View;
use App\Services\Audit;
use App\Services\Migrator;
use App\Services\SetupGuide;
use Throwable;

final class SystemController
{
    public function index(): void
    {
        View::render('Admin::system', [
            'title'   => __('system.title'),
            'version' => trim((string) @file_get_contents(BASE_PATH . '/VERSION')),
            'pending' => Migrator::pending(),
        ]);
    }

    public function update(): void
    {
        try {
            $ran = Migrator::run();
        } catch (Throwable $e) {
            error_log((string) $e);
            flash('error', __('system.update_failed', ['error' => $e->getMessage()]));
            redirect('/settings/system');
        }
        Audit::log('system.updated', 'settings', null, ['migrations' => $ran]);
        flash('success', __('system.updated', ['count' => count($ran)]));
        redirect('/settings/system');
    }

    public function guide(): void
    {
        View::render('Admin::guide', ['title' => __('guide.title'), 'html' => SetupGuide::html()]);
    }
}
