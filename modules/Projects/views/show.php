<?php
use App\Core\View;
use App\Services\TimeService;

$p = $project;
$row = fn (string $label, ?string $value) => $value
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . nl2br(e($value)) . '</dd></div>'
    : '';
$billingValue = match ($p['billing_type']) {
    'fixed'    => $p['fixed_fee'] !== null ? format_money((float) $p['fixed_fee'], $p['currency']) : null,
    'retainer' => $p['retainer_amount'] !== null ? format_money((float) $p['retainer_amount'], $p['currency']) . ' / ' . __('projects.month') : null,
    default    => $p['hourly_rate'] !== null ? format_money((float) $p['hourly_rate'], $p['currency']) . ' / ' . __('projects.hour') : null,
};
?>
<div class="page-head">
    <div>
        <a href="/clients/<?= $p['client_id'] ?>" class="back-link">← <?= e($p['client_name']) ?></a>
        <div class="title-line">
            <h1><?= e($p['name']) ?></h1>
            <span class="code code-lg"><?= e($p['number']) ?></span>
            <?= View::partial('Projects::_status', ['status' => $p['status']]) ?>
        </div>
        <p class="muted">
            <?= e(__('projects.type_' . $p['type'])) ?>
            <?= $p['court'] ? ' · ' . e($p['court']) : '' ?>
            <?= $p['case_number'] ? ' · ' . e($p['case_number']) : '' ?>
        </p>
    </div>
    <?php if (can('projects.edit')): ?>
        <a href="/projects/<?= $p['id'] ?>/edit" class="btn btn-primary"><?= e(__('clients.edit')) ?></a>
    <?php endif; ?>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('clock') ?></span>
                <h2><?= e(__('dash.recent_time')) ?></h2>
                <?php if ($p['status'] === 'open' && (can('time.own') || can('time.all'))): ?>
                    <a href="/time" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('time.add')) ?></a>
                <?php endif; ?>
            </header>
            <div class="totals-row">
                <div><span><?= e(__('time.total')) ?></span><strong><?= TimeService::hours((int) $totals['minutes']) ?> <?= e(__('projects.hours')) ?></strong></div>
                <div><span><?= e(__('time.billable')) ?></span><strong><?= TimeService::hours((int) $totals['billable_minutes']) ?> <?= e(__('projects.hours')) ?></strong></div>
                <?php if ($p['budget_hours'] !== null): $pct = min(100, round($totals['minutes'] / 60 / max((float) $p['budget_hours'], 0.1) * 100)); ?>
                    <div><span><?= e(__('projects.budget_hours')) ?></span><strong>%<?= $pct ?></strong><span class="meter"><span style="width: <?= $pct ?>%" class="<?= $pct >= 90 ? 'meter-warn' : '' ?>"></span></span></div>
                <?php endif; ?>
                <?php if (can('rates.view') && $p['billing_type'] === 'hourly'): ?>
                    <div><span><?= e(__('time.amount')) ?></span><strong><?= e(format_money((float) $totals['amount'], $p['currency'])) ?></strong></div>
                <?php endif; ?>
            </div>
            <?php if (!$entries): ?>
                <div class="empty"><?= e(__('projects.no_time')) ?></div>
            <?php else: ?>
                <table class="table">
                    <?php foreach ($entries as $t): ?>
                        <tr>
                            <td class="muted nowrap"><?= e(format_date($t['work_date'])) ?></td>
                            <td class="cell-main"><strong><?= e($t['user_name']) ?></strong><span><?= e(mb_strimwidth($t['description'], 0, 120, '…')) ?></span></td>
                            <td class="cell-actions">
                                <strong><?= TimeService::formatMinutes((int) $t['minutes']) ?></strong>
                                <?php if (!$t['is_billable']): ?><br><span class="muted small"><?= e(__('time.non_billable')) ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($p['description']): ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('projects.description')) ?></h2></header>
                <div class="card-body"><?= nl2br(e($p['description'])) ?></div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('clients.section_general')) ?></h2></header>
            <dl class="dl">
                <div class="dl-row"><dt><?= e(__('clients.name')) ?></dt><dd><a href="/clients/<?= $p['client_id'] ?>" class="link"><?= e($p['client_name']) ?></a></dd></div>
                <?= $row('projects.responsible', $p['responsible_name']) ?>
                <?= $row('projects.opened_at', format_date($p['opened_at'])) ?>
                <?= $row('projects.closed_at', format_date($p['closed_at'])) ?>
                <?= $row('portal.title', __($p['portal_visible'] ? 'projects.portal_shown' : 'projects.portal_hidden')) ?>
                <?= $row('projects.budget_hours', $p['budget_hours'] !== null ? rtrim(rtrim((string) $p['budget_hours'], '0'), '.') . ' ' . __('projects.hours') : null) ?>
                <?php if (can('rates.view')): ?>
                    <?= $row('projects.billing_type', __('projects.billing_' . $p['billing_type'])) ?>
                    <?= $row('clients.currency', $p['currency']) ?>
                    <?= $row('projects.fee', $billingValue) ?>
                <?php endif; ?>
            </dl>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('users') ?></span>
                <h2><?= e(__('projects.team')) ?></h2>
            </header>
            <?php if (!$p['members']): ?>
                <div class="empty"><?= e(__('dash.empty')) ?></div>
            <?php else: ?>
                <dl class="dl">
                    <?php foreach ($p['members'] as $m): ?>
                        <div class="dl-row">
                            <dt class="cell-main">
                                <strong class="text-strong"><?= e($m['name']) ?></strong>
                                <?php if ((int) $m['user_id'] === (int) $p['responsible_user_id']): ?><span><?= e(__('projects.responsible')) ?></span><?php endif; ?>
                            </dt>
                            <?php if (isset($rates[$m['user_id']])): $r = $rates[$m['user_id']]; ?>
                                <dd class="cell-main align-right">
                                    <?= $r['rate'] !== null ? e(format_money($r['rate'], $r['currency'])) : '<span class="text-warn">' . e(__('projects.no_rate')) . '</span>' ?>
                                    <?php if ($r['source']): ?><span><?= e(__('projects.rate_source_' . $r['source'])) ?></span><?php endif; ?>
                                </dd>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>
</div>
