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

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('wallet') ?></span>
                <h2><?= e(__('nav.expenses')) ?></h2>
                <?php if ($expenses): ?><span class="muted small"><?= e(format_money(array_sum(array_column($expenses, 'amount')), $p['currency'])) ?></span><?php endif; ?>
                <?php if ($p['status'] !== 'closed' && (can('expenses.own') || can('expenses.all'))): ?>
                    <a href="/expenses?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('expenses.add')) ?></a>
                <?php endif; ?>
            </header>
            <?php if (!$expenses): ?>
                <div class="empty"><?= e(__('expenses.none_for_project')) ?></div>
            <?php else: ?>
                <table class="table">
                    <?php foreach ($expenses as $x): ?>
                        <tr>
                            <td class="muted nowrap"><?= e(format_date($x['expense_date'])) ?></td>
                            <td class="cell-main"><strong><?= e(__('expenses.cat_' . $x['category'])) ?></strong><span><?= e($x['description']) ?> · <?= e($x['user_name']) ?></span></td>
                            <td class="cell-actions">
                                <strong><?= e(format_money((float) $x['amount'], $x['currency'])) ?></strong><br>
                                <span class="muted small"><?= e(__($x['statement_id'] ? 'time.billed' : ($x['is_billable'] ? 'expenses.state_unbilled' : 'expenses.state_internal'))) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <?php if (\App\Services\DocumentService::enabled()): ?>
            <section class="card widget widget-auto" id="documents">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('folder') ?></span>
                    <h2><?= e(__('docs.title')) ?></h2>
                    <?php if (can('documents.manage')): ?>
                        <label class="btn btn-sm btn-outline head-right upload-zone" data-upload="/projects/<?= $p['id'] ?>/documents/upload"
                               data-csrf="<?= e(\App\Core\Csrf::token()) ?>" data-msg-done="<?= e(__('docs.uploaded')) ?>" data-msg-empty="<?= e(__('docs.file_empty')) ?>">
                            <?= icon('plus', 'icon icon-sm') ?><?= e(__('docs.upload')) ?>
                            <input type="file" multiple hidden>
                            <span class="upload-status"></span>
                        </label>
                    <?php endif; ?>
                </header>
                <div class="docs-body" data-src="/projects/<?= $p['id'] ?>/documents"><div class="empty"><?= e(__('docs.loading')) ?></div></div>
            </section>
            <script src="<?= asset('upload.js') ?>" defer></script>
            <script>
            (() => {
                const section = document.getElementById('documents');
                const body = section.querySelector('.docs-body');
                const load = () => fetch(body.dataset.src, { credentials: 'same-origin' }).then(r => r.text()).then(html => { body.innerHTML = html; });
                load();
                section.addEventListener('uploaded', load);
                body.addEventListener('click', async e => {
                    const btn = e.target.closest('[data-share]');
                    if (!btn) return;
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.append('_csrf', <?= json_encode(\App\Core\Csrf::token()) ?>);
                    await fetch(btn.dataset.share, { method: 'POST', body: fd, credentials: 'same-origin' });
                    load();
                });
            })();
            </script>
        <?php endif; ?>

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
