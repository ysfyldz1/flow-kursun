<?php
use App\Core\PortalAuth;
use App\Core\View;
use App\Services\StatementService;

$open = array_filter($statements, fn ($s) => StatementService::balance($s) > 0);
$closed = array_filter($statements, fn ($s) => StatementService::balance($s) <= 0);
?>
<div class="page-head">
    <div>
        <h1><?= e(__('dash.hello', ['name' => explode(' ', PortalAuth::user()['name'])[0]])) ?></h1>
        <p class="muted"><?= e(__('portal.subtitle')) ?></p>
    </div>
</div>

<div class="grid grid-<?= max(1, min(3, count($balances) ?: 1)) ?> portal-balances">
    <?php if (!$balances): ?>
        <div class="card stat">
            <span class="stat-icon tone-green"><?= icon('check') ?></span>
            <div class="stat-text"><strong><?= e(__('portal.no_balance')) ?></strong><span><?= e(__('portal.no_balance_hint')) ?></span></div>
        </div>
    <?php endif; ?>
    <?php foreach ($balances as $b): ?>
        <div class="card stat">
            <span class="stat-icon <?= (float) $b['overdue'] > 0 ? 'tone-red' : 'tone-amber' ?>"><?= icon('receipt') ?></span>
            <div class="stat-text">
                <strong><?= e(__('portal.balance', ['currency' => $b['currency']])) ?></strong>
                <span>
                    <?= e(__('portal.open_count', ['count' => $b['count']])) ?>
                    <?php if ((float) $b['overdue'] > 0): ?> · <span class="text-danger"><?= e(__('portal.overdue_amount', ['amount' => format_money((float) $b['overdue'], $b['currency'])])) ?></span><?php endif; ?>
                </span>
            </div>
            <div class="stat-value stat-money"><?= e(format_money((float) $b['balance'], $b['currency'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($meetings): ?>
    <section class="card widget widget-auto" style="margin-bottom:16px">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('users') ?></span>
            <h2><?= e(__('meetings.title')) ?></h2>
        </header>
        <?php foreach ($meetings as $m): ?>
            <a href="/portal/meetings/<?= $m['id'] ?>" class="widget-row">
                <div class="cell-main">
                    <strong><?= e(format_date($m['meeting_at'], true)) ?></strong>
                    <span><?= e(__('meetings.responses')) ?>: <?= (int) $m['response_count'] ?> / <?= (int) $m['shareholder_count'] ?></span>
                </div>
                <div class="cell-main align-right"><?= View::partial('Meetings::_status', ['m' => $m]) ?></div>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($projects): ?>
    <section class="card widget widget-auto" style="margin-bottom:16px">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('folder') ?></span>
            <h2><?= e(__('portal.my_projects')) ?></h2>
        </header>
        <?php foreach ($projects as $p): ?>
            <a href="/portal/projects/<?= $p['id'] ?>" class="widget-row portal-project">
                <div class="cell-main">
                    <strong><?= e($p['name']) ?></strong>
                    <span>
                        <?= e($p['number']) ?> · <?= e(__('projects.type_' . $p['type'])) ?>
                        <?= $p['case_number'] ? ' · ' . e($p['case_number']) : '' ?>
                        <?= $p['responsible_name'] ? ' · ' . e($p['responsible_name']) : '' ?>
                    </span>
                </div>
                <div class="portal-project-side">
                    <?php if ($showHours): ?><?= View::partial('Portal::_project_meter', ['p' => $p]) ?><?php endif; ?>
                    <?= View::partial('Projects::_status', ['status' => $p['status']]) ?>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php foreach (['portal.open_statements' => $open, 'portal.past_statements' => $closed] as $label => $list): ?>
    <?php if (!$list && $label === 'portal.past_statements') continue; ?>
    <section class="card widget widget-auto" style="margin-bottom:16px">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('receipt') ?></span>
            <h2><?= e(__($label)) ?></h2>
        </header>
        <?php if (!$list): ?>
            <div class="empty"><?= e(__('portal.nothing_open')) ?></div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= e(__('billing.number')) ?></th>
                        <th><?= e(__('billing.issue_date')) ?></th>
                        <th><?= e(__('billing.due_date')) ?></th>
                        <th class="num"><?= e(__('billing.total_due')) ?></th>
                        <th class="num"><?= e(__('billing.balance')) ?></th>
                        <th><?= e(__('users.status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($list as $s): ?>
                    <tr class="row-link" onclick="location='/portal/statements/<?= $s['id'] ?>'">
                        <td><a href="/portal/statements/<?= $s['id'] ?>" class="code"><?= e($s['number']) ?></a></td>
                        <td class="muted"><?= e(format_date($s['issue_date'])) ?></td>
                        <td class="muted"><?= e(format_date($s['due_date'])) ?></td>
                        <td class="num"><?= e(format_money((float) $s['total'], $s['currency'])) ?></td>
                        <td class="num"><strong><?= e(format_money(StatementService::balance($s), $s['currency'])) ?></strong></td>
                        <td>
                            <?= View::partial('Billing::_status', ['s' => $s]) ?>
                            <?php if ($s['pending_notices']): ?><span class="badge badge-blue"><?= e(__('portal.notice_pending')) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
