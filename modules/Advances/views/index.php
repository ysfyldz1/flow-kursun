<div class="page-head">
    <div>
        <h1><?= e(__('adv.title')) ?></h1>
        <p class="muted"><?= e(__('adv.subtitle')) ?></p>
    </div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('adv.none')) ?></div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= e(__('nav.clients')) ?></th>
                        <th class="num"><?= e(__('adv.in')) ?></th>
                        <th class="num"><?= e(__('adv.out')) ?></th>
                        <th class="num"><?= e(__('adv.balance')) ?></th>
                        <th><?= e(__('adv.last_move')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $cur = $r['currency'] ?? ''; $bal = (float) $r['balance']; ?>
                    <tr class="row-link" onclick="location='/clients/<?= $r['client_id'] ?>/advance<?= $cur ? '?currency=' . e($cur) : '' ?>'">
                        <td class="cell-main">
                            <strong><?= e($r['name']) ?></strong>
                            <span><?= e($r['code']) ?><?php if (!$r['uses_advance']): ?> · <?= e(__('adv.off')) ?><?php endif; ?></span>
                        </td>
                        <?php if ($cur): ?>
                            <td class="num"><?= e(format_money((float) $r['received'], $cur)) ?></td>
                            <td class="num"><?= e(format_money((float) $r['spent'], $cur)) ?></td>
                            <td class="num">
                                <strong class="<?= $bal < 0 ? 'text-danger' : '' ?>"><?= e(format_money($bal, $cur)) ?></strong>
                                <?php if ($bal < 0): ?><br><span class="badge badge-red"><?= e(__('adv.deficit')) ?></span><?php elseif ($bal == 0): ?><br><span class="badge badge-amber"><?= e(__('adv.empty_balance')) ?></span><?php endif; ?>
                            </td>
                            <td class="muted"><?= e(format_date($r['last_date'])) ?></td>
                        <?php else: ?>
                            <td colspan="4" class="muted"><?= e(__('adv.no_moves')) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
