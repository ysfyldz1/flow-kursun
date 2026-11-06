<?php

declare(strict_types=1);

namespace Modules\Dashboard;

use App\Core\View;

final class DashboardController
{
    public function index(): void
    {
        // Müvekkil, proje ve zaman modülleri gelene kadar sayaçlar 0.
        $stats = [
            ['icon' => 'users',   'label' => __('dash.active_clients'),  'hint' => __('dash.hint_clients'),  'value' => 0, 'tone' => 'blue'],
            ['icon' => 'folder',  'label' => __('dash.open_projects'),   'hint' => __('dash.hint_projects'), 'value' => 0, 'tone' => 'green'],
            ['icon' => 'clock',   'label' => __('dash.hours_month'),     'hint' => __('dash.hint_hours'),    'value' => '0,0', 'tone' => 'amber'],
            ['icon' => 'receipt', 'label' => __('dash.unpaid'),          'hint' => __('dash.hint_unpaid'),   'value' => 0, 'tone' => 'red'],
        ];

        View::render('Dashboard::index', [
            'title' => __('nav.dashboard'),
            'stats' => $stats,
        ]);
    }
}
