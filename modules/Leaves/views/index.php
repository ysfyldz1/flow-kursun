<?php
use App\Core\View;
use App\Services\LeaveService;

$tabs = ['mine' => 'leaves.tab_mine'];
if ($pending || can('leaves.approve') || can('leaves.manage')) {
    $tabs['approve'] = 'leaves.tab_approve';
}
if (can('leaves.manage')) {
    $tabs['all'] = 'leaves.tab_all';
}
$q = fn (array $over) => '?' . http_build_query($over + ['tab' => $tab, 'year' => $year]);
$fmtDays = fn (float $d) => rtrim(rtrim(number_format($d, 1, ',', '.'), '0'), ',');
?>
<div class="page-head">
    <div>
        <h1><?= e(__('leaves.title')) ?></h1>
        <p class="muted"><?= e(__('leaves.subtitle')) ?></p>
    </div>
    <?php if (can('leaves.manage')): ?>
        <a href="/leaves/balances" class="btn btn-outline"><?= icon('users') ?><?= e(__('leaves.balances')) ?></a>
    <?php endif; ?>
</div>

<div class="time-layout">
    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('calendar') ?></span>
                <h2><?= e(__('leaves.my_balance')) ?></h2>
            </header>
            <dl class="dl">
                <div class="dl-row"><dt><?= e(__('leaves.remaining')) ?></dt><dd class="text-strong"><?= e($fmtDays($balance['remaining'])) ?> <?= e(__('leaves.days_unit')) ?></dd></div>
                <div class="dl-row"><dt><?= e(__('leaves.entitlement')) ?></dt><dd><?= e($fmtDays($balance['entitlement'])) ?></dd></div>
                <div class="dl-row"><dt><?= e(__('leaves.used')) ?></dt><dd><?= e($fmtDays($balance['used'])) ?></dd></div>
                <div class="dl-row"><dt><?= e(__('leaves.excuse_year')) ?></dt><dd><?= e($fmtDays($balance['excuse_year'])) ?></dd></div>
            </dl>
            <?php if (!$balance['hire_date'] && !$balance['manual']): ?>
                <p class="muted small" style="padding:0 16px 12px"><?= e(__('leaves.no_hire_date')) ?></p>
            <?php endif; ?>
        </section>

        <?php if (can('leaves.own') || can('leaves.manage')): ?>
            <section class="card form-card">
                <h3 class="card-title"><?= e(__('leaves.new')) ?></h3>
                <?= View::partial('Leaves::_form', ['users' => $users, 'approvers' => $approvers]) ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="tabs">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= e($q(['tab' => $key])) ?>" class="<?= $tab === $key ? 'active' : '' ?>">
                        <?= e(__($label)) ?><?php if ($key === 'approve' && $pending): ?> <span class="badge badge-amber"><?= $pending ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($tab !== 'approve'): ?>
                <div class="month-nav">
                    <a href="<?= e($q(['year' => $year - 1])) ?>" class="btn btn-sm btn-outline" aria-label="‹">‹</a>
                    <strong><?= $year ?></strong>
                    <a href="<?= e($q(['year' => $year + 1])) ?>" class="btn btn-sm btn-outline" aria-label="›">›</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$rows): ?>
            <div class="empty empty-lg"><?= e(__($tab === 'approve' ? 'leaves.empty_approve' : 'leaves.empty')) ?></div>
        <?php else: ?>
            <table class="table table-m">
                <thead>
                    <tr>
                        <?php if ($tab !== 'mine'): ?><th><?= e(__('leaves.employee')) ?></th><?php endif; ?>
                        <th><?= e(__('leaves.type')) ?></th>
                        <th><?= e(__('leaves.dates')) ?></th>
                        <th class="num"><?= e(__('leaves.days')) ?></th>
                        <th><?= e(__('users.status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $l): ?>
                    <tr class="row-link" onclick="location.href='/leaves/<?= $l['id'] ?>'">
                        <?php if ($tab !== 'mine'): ?>
                            <td class="cell-main m-title"><strong><?= e($l['user_name']) ?></strong><span><?= e($l['user_title'] ?? '') ?></span></td>
                        <?php endif; ?>
                        <td class="<?= $tab !== 'mine' ? 'm-meta' : 'm-title' ?>"><a href="/leaves/<?= $l['id'] ?>" class="link"><?= e(__('leaves.type_' . $l['type'])) ?></a></td>
                        <td class="nowrap m-meta"><?= e(format_date($l['start_date'])) ?><?= $l['end_date'] !== $l['start_date'] ? ' – ' . e(format_date($l['end_date'])) : '' ?></td>
                        <td class="num m-meta"><?= e($fmtDays((float) $l['days'])) ?></td>
                        <td class="m-value"><?= View::partial('Leaves::_status', ['l' => $l]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
