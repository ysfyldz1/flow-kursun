<?php

declare(strict_types=1);

namespace Modules\Reports;

use App\Core\DB;
use App\Core\View;
use App\Services\ReportService;

final class ReportController
{
    public function time(): void
    {
        $f = $this->periodFilters();
        $group = in_array($_GET['group'] ?? '', ReportService::GROUPS, true) ? $_GET['group'] : 'user';
        $f['group'] = $group;
        $f['user_id'] = (int) ($_GET['user_id'] ?? 0);
        $f['client_id'] = (int) ($_GET['client_id'] ?? 0);
        $rows = ReportService::time($f['from'], $f['to'], $group, $f);
        $money = can('rates.view');

        if (isset($_GET['export'])) {
            // Tutarlar para birimi başına ayrı sütun
            $currencies = $money ? array_keys(ReportService::timeAmounts($rows)) : [];
            $headers = [__('reports.group_' . $group), '', __('time.total'), __('time.billable'), '%'];
            foreach ($currencies as $cur) {
                $headers[] = __('time.amount') . " ($cur)";
            }
            ReportService::csv("zaman-{$f['from']}-{$f['to']}.csv", $headers, array_map(function ($r) use ($currencies) {
                $line = [$r['label'], $r['sublabel'], round($r['minutes'] / 60, 2), round($r['billable_minutes'] / 60, 2), $r['minutes'] ? (int) round($r['billable_minutes'] / $r['minutes'] * 100) : 0];
                foreach ($currencies as $cur) {
                    $line[] = (float) ($r['amounts'][$cur] ?? 0);
                }
                return $line;
            }, $rows));
        }

        $this->render('time', [
            'rows'    => $rows,
            'filters' => $f,
            'users'   => DB::all('SELECT id, name FROM users ORDER BY is_active DESC, name'),
            'clients' => DB::all('SELECT id, name FROM clients ORDER BY name'),
            'totalMinutes'    => array_sum(array_column($rows, 'minutes')),
            'billableMinutes' => array_sum(array_column($rows, 'billable_minutes')),
            'amounts'         => ReportService::timeAmounts($rows),
        ]);
    }

    public function unbilled(): void
    {
        $rows = ReportService::unbilled();
        if (isset($_GET['export'])) {
            ReportService::csv('faturalanmamis-' . date('Y-m-d') . '.csv',
                [__('clients.name'), __('projects.number'), __('projects.name'), __('clients.currency'), __('billing.entries'), __('billing.hours'), __('time.amount'), __('reports.oldest')],
                array_map(fn ($r) => [$r['client_name'], $r['number'], $r['project_name'], $r['currency'], $r['entries'], round($r['minutes'] / 60, 2), can('rates.view') ? (float) $r['amount'] : '', $r['oldest']], $rows));
        }
        $this->render('unbilled', [
            'rows'    => $rows,
            'amounts' => ReportService::totalsByCurrency($rows, ['amount', 'minutes']),
        ]);
    }

    public function aging(): void
    {
        $this->requireMoney();
        $rows = ReportService::aging();
        $buckets = ['current_amount', 'd30', 'd60', 'd90', 'd90plus', 'total'];
        if (isset($_GET['export'])) {
            ReportService::csv('alacak-yaslandirma-' . date('Y-m-d') . '.csv',
                array_merge([__('clients.name'), __('clients.currency')], array_map(fn ($b) => __('reports.bucket_' . $b), $buckets)),
                array_map(fn ($r) => array_merge([$r['client_name'], $r['currency']], array_map(fn ($b) => (float) $r[$b], $buckets)), $rows));
        }
        $this->render('aging', [
            'rows'    => $rows,
            'buckets' => $buckets,
            'totals'  => ReportService::totalsByCurrency($rows, $buckets),
        ]);
    }

    public function collections(): void
    {
        $this->requireMoney();
        $f = $this->periodFilters();
        $rows = ReportService::collections($f['from'], $f['to']);
        if (isset($_GET['export'])) {
            ReportService::csv("tahsilat-{$f['from']}-{$f['to']}.csv",
                [__('clients.name'), __('clients.currency'), __('reports.invoiced'), __('reports.collected'), __('reports.payment_count')],
                array_map(fn ($r) => [$r['client_name'], $r['currency'], (float) $r['invoiced'], (float) $r['collected'], (int) $r['payments']], $rows));
        }
        $this->render('collections', [
            'rows'    => $rows,
            'filters' => $f,
            'totals'  => ReportService::totalsByCurrency($rows, ['invoiced', 'collected']),
        ]);
    }

    private function periodFilters(): array
    {
        $preset = in_array($_GET['period'] ?? '', ReportService::PERIODS, true) ? $_GET['period'] : 'this_month';
        [$from, $to] = ReportService::period($preset, $_GET['from'] ?? null, $_GET['to'] ?? null);
        return ['period' => $preset, 'from' => $from, 'to' => $to];
    }

    /** Alacak ve tahsilat raporları tutardan ibaret; ücret görme yetkisi şart. */
    private function requireMoney(): void
    {
        if (!can('rates.view')) {
            abort(403);
        }
    }

    private function render(string $view, array $data): void
    {
        View::render("Reports::$view", $data + ['title' => __('reports.' . $view), 'tab' => $view]);
    }
}
