<?php
use App\Core\View;
use App\Services\ExpenseService;

$states = ['' => 'app.all', 'unbilled' => 'expenses.state_unbilled', 'billed' => 'expenses.state_billed', 'internal' => 'expenses.state_internal', 'advance' => 'adv.state_advance'];
$query = fn (array $over) => '?' . http_build_query(array_filter($over + $filters));
?>
<div class="page-head">
    <div>
        <h1><?= e(__('expenses.title')) ?></h1>
        <p class="muted"><?= e(__('expenses.subtitle')) ?></p>
    </div>
</div>

<div class="time-layout">
    <div class="stack">
        <section class="card form-card">
            <h3 class="card-title"><?= e(__('expenses.new')) ?></h3>
            <?= View::partial('Expenses::_form', ['entry' => null, 'projects' => $projects]) ?>
        </section>
    </div>

    <div class="stack">
        <?php if ($totals): ?>
            <div class="grid grid-<?= min(3, count($totals)) ?>" style="margin-bottom:0">
                <?php foreach ($totals as $cur => $t): ?>
                    <div class="card stat">
                        <span class="stat-icon tone-amber"><?= icon('wallet') ?></span>
                        <div class="stat-text"><strong><?= e(__('expenses.total_in', ['currency' => $cur])) ?></strong><span><?= e(format_date($filters['from'])) ?> – <?= e(format_date($filters['to'])) ?></span></div>
                        <div class="stat-value stat-money"><?= e(format_money($t['amount'], $cur)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="get" class="toolbar">
                <div class="tabs">
                    <?php foreach ($states as $key => $label): ?>
                        <a href="<?= e($query(['state' => $key])) ?>" class="<?= $filters['state'] === $key ? 'active' : '' ?>"><?= e(__($label)) ?></a>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="state" value="<?= e($filters['state']) ?>">
                <?= View::partial('Reports::_period', ['filters' => $filters]) ?>
                <?php if ($users): ?>
                    <select name="user_id" class="toolbar-select" onchange="this.form.submit()">
                        <option value=""><?= e(__('reports.all_people')) ?></option>
                        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $filters['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>

            <?php if (!$rows): ?>
                <div class="empty empty-lg"><?= e(__('expenses.empty')) ?></div>
            <?php else: ?>
                <table class="table table-m">
                    <thead>
                        <tr>
                            <th><?= e(__('time.date')) ?></th>
                            <th><?= e(__('time.description')) ?></th>
                            <th><?= e(__('time.project')) ?></th>
                            <th class="num"><?= e(__('time.amount')) ?></th>
                            <th><?= e(__('users.status')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $x): ?>
                        <tr>
                            <td class="muted nowrap m-meta"><?= e(format_date($x['expense_date'])) ?></td>
                            <td class="cell-main m-title">
                                <strong><?= e(__('expenses.cat_' . $x['category'])) ?></strong>
                                <span><?= e($x['description']) ?><?= $users ? ' · ' . e($x['user_name']) : '' ?></span>
                            </td>
                            <td class="m-meta m-sub"><a href="/projects/<?= $x['project_id'] ?>" class="cell-main"><strong><?= e($x['project_name']) ?></strong><span><?= e($x['project_number']) ?> · <?= e($x['client_name']) ?></span></a></td>
                            <td class="num m-value">
                                <strong><?= e(format_money((float) $x['amount'], $x['currency'])) ?></strong>
                                <?php if ($x['receipt_file']): ?><br><a href="/expenses/<?= $x['id'] ?>/receipt" target="_blank" class="link small"><?= e(__('expenses.receipt')) ?> ↗</a><?php endif; ?>
                            </td>
                            <td class="m-meta">
                                <?php if ($x['statement_id']): ?>
                                    <span class="badge badge-blue"><?= e($x['statement_number'] ?: __('billing.draft')) ?></span>
                                <?php elseif ($x['from_advance']): ?>
                                    <span class="badge badge-green"><?= e(__('adv.state_advance')) ?></span>
                                <?php elseif (!$x['is_billable']): ?>
                                    <span class="badge badge-gray"><?= e(__('expenses.state_internal')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-amber"><?= e(__('expenses.state_unbilled')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-actions m-actions">
                                <?php if (ExpenseService::canModify($x)): ?>
                                    <a href="/expenses/<?= $x['id'] ?>/edit" class="btn btn-sm btn-outline"><?= e(__('clients.edit')) ?></a>
                                    <form method="post" action="/expenses/<?= $x['id'] ?>/delete" onsubmit="return confirm('<?= e(__('app.confirm_delete')) ?>')">
                                        <?= csrf_field() ?><button class="btn btn-sm btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
