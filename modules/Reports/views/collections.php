<?php use App\Core\View; ?>
<?= View::partial('Reports::_head', ['tab' => $tab]) ?>

<div class="grid grid-4">
    <?php foreach ($totals ?: ['TRY' => ['invoiced' => 0, 'collected' => 0]] as $cur => $t): ?>
        <div class="card stat">
            <span class="stat-icon tone-blue"><?= icon('receipt') ?></span>
            <div class="stat-text"><strong><?= e(__('reports.invoiced')) ?> (<?= e($cur) ?>)</strong><span><?= e(__('reports.invoiced_hint')) ?></span></div>
            <div class="stat-value stat-money"><?= e(format_money($t['invoiced'], $cur)) ?></div>
        </div>
        <div class="card stat">
            <span class="stat-icon tone-green"><?= icon('check') ?></span>
            <div class="stat-text"><strong><?= e(__('reports.collected')) ?> (<?= e($cur) ?>)</strong><span><?= e(__('reports.collected_hint')) ?></span></div>
            <div class="stat-value stat-money"><?= e(format_money($t['collected'], $cur)) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <form method="get" class="toolbar">
        <?= View::partial('Reports::_period', ['filters' => $filters]) ?>
    </form>
    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('reports.empty')) ?></div>
    <?php else: ?>
        <table class="table table-m">
            <thead>
                <tr>
                    <th><?= e(__('clients.name')) ?></th>
                    <th class="num"><?= e(__('reports.invoiced')) ?></th>
                    <th class="num"><?= e(__('reports.collected')) ?></th>
                    <th class="num"><?= e(__('reports.payment_count')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="m-title"><a href="/clients/<?= $r['client_id'] ?>" class="cell-main"><strong><?= e($r['client_name']) ?></strong><span><?= e($r['client_code']) ?></span></a></td>
                    <td class="num m-meta"><span class="m-label"><?= e(__('reports.invoiced')) ?> </span><?= (float) $r['invoiced'] > 0 ? e(format_money((float) $r['invoiced'], $r['currency'])) : '<span class="muted">—</span>' ?></td>
                    <td class="num m-value"><strong><?= (float) $r['collected'] > 0 ? e(format_money((float) $r['collected'], $r['currency'])) : '<span class="muted">—</span>' ?></strong></td>
                    <td class="num m-hide"><?= (int) $r['payments'] ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
