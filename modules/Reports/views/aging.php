<?php use App\Core\View; ?>
<?= View::partial('Reports::_head', ['tab' => $tab]) ?>

<?php foreach ($totals as $cur => $t): ?>
    <div class="card aging-summary">
        <div class="aging-title"><strong><?= e(__('reports.receivables_in', ['currency' => $cur])) ?></strong><span class="stat-money"><?= e(format_money($t['total'], $cur)) ?></span></div>
        <div class="aging-bar">
            <?php foreach (['current_amount', 'd30', 'd60', 'd90', 'd90plus'] as $b): if ($t[$b] <= 0) continue; ?>
                <span class="ab-<?= $b ?>" style="width: <?= round($t[$b] / $t['total'] * 100, 2) ?>%" title="<?= e(__('reports.bucket_' . $b) . ': ' . format_money($t[$b], $cur)) ?>"></span>
            <?php endforeach; ?>
        </div>
        <div class="aging-legend">
            <?php foreach (['current_amount', 'd30', 'd60', 'd90', 'd90plus'] as $b): ?>
                <span><i class="ab-<?= $b ?>"></i><?= e(__('reports.bucket_' . $b)) ?> <strong><?= e(format_money($t[$b], $cur)) ?></strong></span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('reports.no_receivables')) ?></div>
    <?php else: ?>
        <table class="table table-m">
            <thead>
                <tr>
                    <th><?= e(__('clients.name')) ?></th>
                    <?php foreach ($buckets as $b): ?><th class="num"><?= e(__('reports.bucket_' . $b)) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="row-link" onclick="location='/billing?client_id=<?= $r['client_id'] ?>&status=open'">
                    <td class="cell-main m-title"><strong><?= e($r['client_name']) ?></strong><span><?= e($r['client_code']) ?> · <?= e(__('portal.open_count', ['count' => $r['statements']])) ?></span></td>
                    <?php foreach ($buckets as $b): $v = (float) $r[$b]; ?>
                        <td class="num <?= $b === 'total' ? 'strong m-value' : ($v > 0 ? 'm-meta' : 'm-empty') ?> <?= in_array($b, ['d60', 'd90', 'd90plus'], true) && $v > 0 ? 'text-danger' : '' ?>">
                            <?php if ($b !== 'total'): ?><span class="m-label"><?= e(__('reports.bucket_' . $b)) ?> </span><?php endif; ?>
                            <?= $v > 0 ? e(format_money($v, $r['currency'])) : '<span class="muted">—</span>' ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
