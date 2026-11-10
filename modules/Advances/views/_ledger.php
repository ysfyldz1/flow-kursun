<?php
/** Avans ekstresi: ofis ve portal ortak. $deleteBase verilirse giriş satırlarında silme düğmesi çıkar. */
$deleteBase ??= null;
$money = fn (float $v) => e(format_money($v, $currency));
$cols = $deleteBase ? 6 : 5;
?>
<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th><?= e(__('adv.date')) ?></th>
                <th><?= e(__('adv.description')) ?></th>
                <th class="num"><?= e(__('adv.in')) ?></th>
                <th class="num"><?= e(__('adv.out')) ?></th>
                <th class="num"><?= e(__('adv.balance')) ?></th>
                <?php if ($deleteBase): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <tr class="row-muted">
                <td class="muted"><?= e(format_date($filters['from'])) ?></td>
                <td><strong><?= e(__('adv.opening')) ?></strong></td>
                <td></td><td></td>
                <td class="num"><strong class="<?= $ledger['opening'] < 0 ? 'text-danger' : '' ?>"><?= $money($ledger['opening']) ?></strong></td>
                <?php if ($deleteBase): ?><td></td><?php endif; ?>
            </tr>
            <?php if (!$ledger['rows']): ?>
                <tr><td colspan="<?= $cols ?>" class="empty"><?= e(__('adv.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($ledger['rows'] as $r): ?>
                <?php $amount = (float) $r['amount']; ?>
                <tr>
                    <td class="muted"><?= e(format_date($r['d'])) ?></td>
                    <td class="cell-main">
                        <?php if ($r['kind'] === 'expense'): ?>
                            <strong><?= e(__('expenses.cat_' . $r['category'])) ?> – <?= e($r['description']) ?></strong>
                            <span><?= e($r['project_number']) ?> · <?= e($r['project_name']) ?></span>
                        <?php else: ?>
                            <strong><?= e(__('adv.type_' . $r['type'])) ?></strong>
                            <span><?= e(implode(' · ', array_filter([$r['method'] ? __('adv.method_' . $r['method']) : null, $r['reference'], $r['description']]))) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= $amount > 0 ? $money($amount) : '' ?></td>
                    <td class="num"><?= $amount < 0 ? $money(-$amount) : '' ?></td>
                    <td class="num"><strong class="<?= $r['balance'] < 0 ? 'text-danger' : '' ?>"><?= $money((float) $r['balance']) ?></strong></td>
                    <?php if ($deleteBase): ?>
                        <td class="cell-actions">
                            <?php if ($r['kind'] === 'advance'): ?>
                                <form method="post" action="<?= e($deleteBase . '/' . $r['id'] . '/delete') ?>" onsubmit="return confirm(<?= e(json_encode(__('adv.confirm_delete'), JSON_UNESCAPED_UNICODE)) ?>)"><?= csrf_field() ?><button class="btn btn-sm btn-outline btn-danger"><?= e(__('app.delete')) ?></button></form>
                            <?php else: ?>
                                <a href="/expenses/<?= $r['id'] ?>/edit" class="btn btn-sm btn-outline"><?= e(__('adv.edit')) ?></a>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td><strong><?= e(__('adv.period_totals')) ?></strong></td>
                <td class="num"><strong><?= $money($ledger['in']) ?></strong></td>
                <td class="num"><strong><?= $money($ledger['out']) ?></strong></td>
                <td class="num"></td>
                <?php if ($deleteBase): ?><td></td><?php endif; ?>
            </tr>
            <tr>
                <td class="muted"><?= e(format_date($filters['to'])) ?></td>
                <td><strong><?= e(__('adv.closing')) ?></strong><?php if ($ledger['closing'] < 0): ?> <span class="badge badge-red"><?= e(__('adv.deficit')) ?></span><?php endif; ?></td>
                <td></td><td></td>
                <td class="num"><strong class="<?= $ledger['closing'] < 0 ? 'text-danger' : '' ?>"><?= $money($ledger['closing']) ?></strong></td>
                <?php if ($deleteBase): ?><td></td><?php endif; ?>
            </tr>
        </tfoot>
    </table>
</div>
