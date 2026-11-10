<?php /** Para birimi başına avans bakiyesi kartları; $link verilirse kart o para biriminin ekstresine gider. */ ?>
<div class="grid grid-<?= max(1, min(3, count($balances))) ?>" style="margin-bottom:16px">
    <?php foreach ($balances as $b): ?>
        <?php $bal = (float) $b['balance']; ?>
        <a href="?currency=<?= e($b['currency']) ?>" class="card stat">
            <span class="stat-icon <?= $bal < 0 ? 'tone-red' : ($bal == 0 ? 'tone-amber' : 'tone-green') ?>"><?= icon('wallet') ?></span>
            <div class="stat-text">
                <strong><?= e(__('adv.balance_in', ['currency' => $b['currency']])) ?></strong>
                <span>
                    <?= e(__('adv.received_spent', ['in' => format_money((float) $b['received'], $b['currency']), 'out' => format_money((float) $b['spent'], $b['currency'])])) ?>
                    <?php if ($bal < 0): ?> · <span class="text-danger"><?= e(__('adv.deficit')) ?></span><?php endif; ?>
                </span>
            </div>
            <div class="stat-value stat-money <?= $bal < 0 ? 'text-danger' : '' ?>"><?= e(format_money($bal, $b['currency'])) ?></div>
        </a>
    <?php endforeach; ?>
</div>
