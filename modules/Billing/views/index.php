<?php
use App\Core\View;
use App\Services\StatementService;
use App\Services\TimeService;

$tabs = ['open' => 'billing.tab_open', 'paid' => 'billing.tab_paid', 'draft' => 'billing.tab_draft', 'cancelled' => 'billing.tab_cancelled', 'all' => 'app.all'];
$query = fn (array $over) => '?' . http_build_query(array_filter($over + $filters));
?>
<div class="page-head">
    <div>
        <h1><?= e(__('billing.title')) ?></h1>
        <p class="muted"><?= e(__('billing.subtitle')) ?></p>
    </div>
    <?php if (can('statements.manage')): ?>
        <a href="/billing/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('billing.new')) ?></a>
    <?php endif; ?>
</div>

<?php if ($unbilled): ?>
    <section class="card widget widget-auto" style="margin-bottom:16px">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('clock') ?></span>
            <h2><?= e(__('billing.unbilled_title')) ?></h2>
        </header>
        <table class="table">
            <?php foreach ($unbilled as $u): ?>
                <tr>
                    <td class="cell-main"><strong><?= e($u['name']) ?></strong><span><?= e($u['code']) ?> · <?= e(format_date($u['first_date'])) ?> – <?= e(format_date($u['last_date'])) ?></span></td>
                    <td><?= (int) $u['entries'] ?> <?= e(__('billing.entries')) ?> · <?= TimeService::hours((int) $u['minutes']) ?> <?= e(__('projects.hours')) ?></td>
                    <td class="num"><strong><?= e(format_money((float) $u['amount'], $u['currency'])) ?></strong></td>
                    <td class="cell-actions">
                        <form method="post" action="/billing">
                            <?= csrf_field() ?>
                            <input type="hidden" name="client_id" value="<?= $u['client_id'] ?>">
                            <input type="hidden" name="currency" value="<?= e($u['currency']) ?>">
                            <input type="hidden" name="period_end" value="<?= date('Y-m-d') ?>">
                            <button class="btn btn-sm btn-outline"><?= e(__('billing.create_draft')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
<?php endif; ?>

<div class="card">
    <form method="get" class="toolbar">
        <div class="tabs">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?= e($query(['status' => $key])) ?>" class="<?= $filters['status'] === $key ? 'active' : '' ?>"><?= e(__($label)) ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <label class="search">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('billing.search')) ?>">
        </label>
    </form>

    <?php if (!$statements): ?>
        <div class="empty empty-lg"><?= e(__('billing.empty')) ?></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('billing.number')) ?></th>
                    <th><?= e(__('clients.name')) ?></th>
                    <th><?= e(__('billing.issue_date')) ?></th>
                    <th><?= e(__('billing.due_date')) ?></th>
                    <th class="num"><?= e(__('billing.total_due')) ?></th>
                    <th class="num"><?= e(__('billing.balance')) ?></th>
                    <th><?= e(__('billing.invoice')) ?></th>
                    <th><?= e(__('users.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($statements as $s): ?>
                <tr class="row-link" onclick="location='/billing/<?= $s['id'] ?>'">
                    <td><span class="code"><?= e($s['number'] ?: __('billing.draft')) ?></span></td>
                    <td><a href="/billing/<?= $s['id'] ?>" class="cell-main"><strong><?= e($s['client_name']) ?></strong><span><?= e($s['client_code']) ?></span></a></td>
                    <td class="muted"><?= e(format_date($s['issue_date'])) ?: '—' ?></td>
                    <td class="muted"><?= e(format_date($s['due_date'])) ?: '—' ?></td>
                    <td class="num"><strong><?= e(format_money((float) $s['total'], $s['currency'])) ?></strong></td>
                    <td class="num"><?= $s['status'] === 'issued' ? e(format_money(StatementService::balance($s), $s['currency'])) : '—' ?></td>
                    <td><?= $s['invoice_file'] ? '<span class="badge badge-green">' . e($s['invoice_number'] ?: __('billing.uploaded')) . '</span>' : ($s['status'] === 'issued' ? '<span class="muted small">' . e(__('billing.awaiting_invoice')) . '</span>' : '') ?></td>
                    <td><?= View::partial('Billing::_status', ['s' => $s]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
