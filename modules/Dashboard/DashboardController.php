<?php

declare(strict_types=1);

namespace Modules\Dashboard;

use App\Core\Auth;
use App\Core\View;
use App\Services\ClientService;
use App\Services\ProjectService;
use App\Services\StatementService;
use App\Services\TimeService;

final class DashboardController
{
    public function index(): void
    {
        $stats = [
            ['icon' => 'users',   'label' => __('dash.active_clients'), 'hint' => __('dash.hint_clients'),  'value' => ClientService::countActive(), 'tone' => 'blue'],
            ['icon' => 'folder',  'label' => __('dash.open_projects'),  'hint' => __('dash.hint_projects'), 'value' => ProjectService::countOpen(), 'tone' => 'green'],
            ['icon' => 'clock',   'label' => __('dash.hours_month'),    'hint' => __('dash.hint_hours'),    'value' => TimeService::hours(TimeService::minutesInMonth(Auth::id())), 'tone' => 'amber'],
            ['icon' => 'receipt', 'label' => __('dash.unpaid'),         'hint' => __('dash.hint_unpaid'),   'value' => StatementService::countOpen(), 'tone' => 'red'],
        ];

        View::render('Dashboard::index', [
            'title'      => __('nav.dashboard'),
            'stats'      => $stats,
            'recentTime' => TimeService::recent(Auth::id()),
            'unpaid'     => can('statements.manage') || can('payments.manage') ? StatementService::list(['status' => 'open']) : [],
            // Yaklaşanlar: önümüzdeki 7 gün + gecikmiş süreler (yalnızca kişinin kendi kayıtları)
            'upcoming'   => can('calendar.view')
                ? array_merge(
                    \App\Services\CalendarService::overdue(Auth::id()),
                    \App\Services\CalendarService::range(date('Y-m-d'), date('Y-m-d', strtotime('+6 days')), Auth::id())
                )
                : null,
            'myProjects' => can('projects.view') ? ProjectService::list(['mine' => true, 'status' => 'open']) : [],
        ]);
    }
}
