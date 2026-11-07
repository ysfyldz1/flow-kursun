<?php
// Ofis durumu (reports.view): özet kutular + aylık saat, müvekkil/kişi sıralaması, tahsilat grafikleri.
use App\Core\View;
use App\Services\TimeService;

$hours = fn (float $h) => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',') . ' ' . __('dash.h');
$monthName = fn (string $ym) => __('cal.month_' . (int) substr($ym, 5, 2)) . ' ' . substr($ym, 0, 4);
$monthShort = fn (string $ym) => mb_substr(__('cal.month_' . (int) substr($ym, 5, 2)), 0, 3);
$q = fn (array $over) => '?' . http_build_query($over + ['range' => $o['range'], 'cur' => $o['currency']]);
$k = $o['kpis'];
$pct = fn (?float $v) => $v === null ? '—' : '%' . number_format($v * 100, 0, ',', '.');
?>
<?php // Kapalı gelir; dönem/para birimi seçilerek sayfa yenilendiyse açık kalır ?>
<details class="fold office" data-fold="office" <?= isset($_GET['range']) || isset($_GET['cur']) ? 'open' : '' ?>>
    <summary class="fold-head">
        <span class="fold-chevron"><?= icon('chevron-down') ?></span>
        <h2><?= e(__('dash.office')) ?></h2>
        <span class="muted small fold-hint"><?= e(__('dash.office_hint')) ?></span>
        <a href="/reports" class="widget-link"><?= e(__('nav.reports')) ?> →</a>
    </summary>

    <div class="grid grid-4">
        <div class="card stat">
            <div class="stat-text">
                <strong><?= e(__('dash.office_hours')) ?></strong>
                <span>
                    <?php if ($k['change'] !== null): ?>
                        <span class="<?= $k['change'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $k['change'] >= 0 ? '▲' : '▼' ?> <?= e($pct(abs($k['change']))) ?></span> <?= e(__('dash.vs_last_month')) ?>
                    <?php else: ?><?= e(__('dash.hint_this_month')) ?><?php endif; ?>
                </span>
            </div>
            <div class="stat-value"><?= e(TimeService::hours($k['minutes'])) ?></div>
        </div>
        <div class="card stat">
            <div class="stat-text"><strong><?= e(__('dash.billable_ratio')) ?></strong><span><?= e(__('dash.hint_billable_ratio')) ?></span></div>
            <div class="stat-value"><?= e($pct($k['billable_ratio'])) ?></div>
        </div>
        <a href="/reports/unbilled" class="card stat">
            <div class="stat-text"><strong><?= e(__('dash.unbilled')) ?></strong><span><?= $k['unbilled_oldest'] ? e(__('dash.oldest', ['date' => format_date($k['unbilled_oldest'])])) : e(__('dash.none')) ?></span></div>
            <div class="stat-value"><?= e(TimeService::hours($k['unbilled'])) ?></div>
        </a>
        <a href="/reports/aging" class="card stat">
            <div class="stat-text"><strong><?= e(__('dash.overdue')) ?></strong><span><?= e(__('dash.overdue_clients', ['count' => $k['overdue_clients']])) ?></span></div>
            <div class="stat-value <?= $k['overdue'] ? 'text-danger' : '' ?>"><?= $k['overdue'] ?></div>
        </a>
    </div>

    <div class="grid grid-3-1 viz-row">
        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('dash.monthly_hours')) ?></h2><span class="muted small"><?= e(__('dash.last_12')) ?></span></header>
            <div class="viz-body">
                <?= View::partial('charts/columns', [
                    'rows'    => $o['monthly'],
                    'series'  => ['billable' => __('dash.billable'), 'other' => __('dash.non_billable')],
                    'stacked' => true,
                    'fmt'     => $hours,
                    'xLabel'  => $monthName,
                    'xShort'  => $monthShort,
                    'caption' => __('dash.monthly_hours'),
                    'width'   => 880,
                ]) ?>
            </div>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <h2><?= e(__('dash.collections')) ?></h2>
                <?php if (count($o['currencies']) > 1): ?>
                    <div class="tabs tabs-sm">
                        <?php foreach ($o['currencies'] as $c): ?><a href="<?= e($q(['cur' => $c])) ?>" class="<?= $c === $o['currency'] ? 'active' : '' ?>"><?= $c ?></a><?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>
            <div class="viz-body">
                <?php if (!$o['currency']): ?>
                    <div class="empty"><?= e(__('dash.no_data')) ?></div>
                <?php else: ?>
                    <?= View::partial('charts/columns', [
                        'rows'    => $o['collections'],
                        'series'  => ['invoiced' => __('dash.invoiced'), 'collected' => __('dash.collected')],
                        'stacked' => false,
                        'fmt'     => fn (float $v) => format_money($v, $o['currency']),
                        'xLabel'  => $monthName,
                        'xShort'  => $monthShort,
                        'caption' => __('dash.collections') . ' (' . $o['currency'] . ')',
                        'width'   => 430,
                    ]) ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid grid-2">
        <?php foreach (['clients' => 'dash.top_clients', 'people' => 'dash.by_person'] as $key => $title): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <h2><?= e(__($title)) ?></h2>
                    <div class="tabs tabs-sm">
                        <?php foreach (\App\Services\OfficeStats::RANGES as $r): ?><a href="<?= e($q(['range' => $r])) ?>" class="<?= $r === $o['range'] ? 'active' : '' ?>"><?= e(__('dash.range_' . $r)) ?></a><?php endforeach; ?>
                    </div>
                </header>
                <div class="viz-body">
                    <?= View::partial('charts/bars', ['items' => $o[$key], 'fmt' => $hours]) ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</details>
