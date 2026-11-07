<?php

declare(strict_types=1);

namespace Modules\Dashboard;

use App\Core\Auth;
use App\Core\View;
use App\Services\ClientService;
use App\Services\LeaveService;
use App\Services\ProjectService;
use App\Services\StatementService;
use App\Services\TimeService;

final class DashboardController
{
    public function index(): void
    {
        // Her kutu kendi yetkisiyle; faturalama yetkisi olmayana (avukat) döküm yerine açık görev sayısı
        $billing = can('statements.manage') || can('payments.manage');
        $tasks = can('tasks.own') || can('tasks.all');
        $time = can('time.own') || can('time.all');
        $stats = array_values(array_filter([
            can('clients.view') ? ['icon' => 'users', 'label' => __('dash.active_clients'), 'hint' => __('dash.hint_clients'), 'value' => ClientService::countActive(), 'tone' => 'blue'] : null,
            can('projects.view') ? ['icon' => 'folder', 'label' => __('dash.open_projects'), 'hint' => __('dash.hint_projects'), 'value' => ProjectService::countOpen(), 'tone' => 'green'] : null,
            $time ? ['icon' => 'clock', 'label' => __('dash.hours_month'), 'hint' => __('dash.hint_hours'), 'value' => TimeService::hours(TimeService::minutesInMonth(Auth::id())), 'tone' => 'amber'] : null,
            $billing
                ? ['icon' => 'receipt', 'label' => __('dash.unpaid'), 'hint' => __('dash.hint_unpaid'), 'value' => StatementService::countOpen(), 'tone' => 'red']
                : ($tasks ? ['icon' => 'check', 'label' => __('dash.open_tasks'), 'hint' => __('dash.hint_tasks'), 'value' => \App\Services\TaskService::countOpenMine(), 'tone' => 'red'] : null),
        ]));

        // Ofis durumu yalnız raporları görebilene (yönetici, muhasebe)
        $office = null;
        if (can('reports.view')) {
            $range = in_array($_GET['range'] ?? '', \App\Services\OfficeStats::RANGES, true) ? $_GET['range'] : 'year';
            [$from, $to] = \App\Services\OfficeStats::range($range);
            $currencies = \App\Services\OfficeStats::currencies();
            $currency = in_array($_GET['cur'] ?? '', $currencies, true) ? $_GET['cur'] : ($currencies[0] ?? null);
            $office = [
                'range'       => $range,
                'kpis'        => \App\Services\OfficeStats::kpis(),
                'monthly'     => \App\Services\OfficeStats::monthlyHours(),
                'clients'     => \App\Services\OfficeStats::topClients($from, $to),
                'people'      => \App\Services\OfficeStats::byPerson($from, $to),
                'currencies'  => $currencies,
                'currency'    => $currency,
                'collections' => $currency ? \App\Services\OfficeStats::collections($currency) : [],
            ];
        }

        View::render('Dashboard::index', [
            'office'     => $office,
            'title'      => __('nav.dashboard'),
            'stats'      => $stats,
            'recentTime' => $time ? TimeService::recent(Auth::id()) : null,
            'unpaid'     => $billing ? StatementService::list(['status' => 'open']) : null,
            // Yaklaşanlar: önümüzdeki 7 gün + gecikmiş süreler (yalnızca kişinin kendi kayıtları)
            'upcoming'   => can('calendar.view')
                ? array_merge(
                    \App\Services\CalendarService::overdue(Auth::id()),
                    \App\Services\CalendarService::range(date('Y-m-d'), date('Y-m-d', strtotime('+6 days')), Auth::id())
                )
                : null,
            'tasks'      => $tasks ? \App\Services\TaskService::list('mine') : null,
            'onLeave'    => LeaveService::approvedInRange(date('Y-m-d'), date('Y-m-d')),
            'leavePending' => can('leaves.own') || can('leaves.approve') || can('leaves.manage') ? LeaveService::list('approve') : [],
            'myProjects' => can('projects.view') ? ProjectService::list(['mine' => true, 'status' => 'open']) : null,
        ]);
    }
}
