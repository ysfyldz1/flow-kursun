<?php
$fmtDays = fn (float $d) => rtrim(rtrim(number_format($d, 1, ',', '.'), '0'), ',');
?>
<div class="page-head">
    <div>
        <a href="/leaves" class="back-link">← <?= e(__('leaves.title')) ?></a>
        <h1><?= e(__('leaves.balances')) ?></h1>
        <p class="muted"><?= e(__('leaves.balances_hint')) ?></p>
    </div>
</div>

<div class="card">
    <table class="table table-m">
        <thead>
            <tr>
                <th><?= e(__('leaves.employee')) ?></th>
                <th><?= e(__('users.hire_date')) ?></th>
                <th class="num"><?= e(__('leaves.entitlement')) ?></th>
                <th class="num"><?= e(__('leaves.used')) ?></th>
                <th class="num"><?= e(__('leaves.remaining')) ?></th>
                <th class="num"><?= e(__('leaves.excuse_year')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="cell-main m-title"><strong><?= e($r['name']) ?></strong><span><?= e($r['title'] ?? '') ?></span></td>
                <td class="nowrap m-meta"><?= $r['hire_date'] ? e(format_date($r['hire_date'])) : '<span class="text-warn">' . e(__('leaves.hire_missing')) . '</span>' ?></td>
                <td class="num m-meta"><span class="m-label"><?= e(__('leaves.entitlement_short')) ?> </span><?= e($fmtDays($r['entitlement'])) ?><?= $r['manual'] ? ' <span class="muted small" title="' . e(__('users.leave_entitlement_hint')) . '">✎</span>' : '' ?></td>
                <td class="num m-meta"><span class="m-label"><?= e(__('leaves.used_short')) ?> </span><?= e($fmtDays($r['used'])) ?></td>
                <td class="num m-value"><span class="m-label"><?= e(__('leaves.remaining_short')) ?> </span><strong class="<?= $r['remaining'] < 0 ? 'text-warn' : '' ?>"><?= e($fmtDays($r['remaining'])) ?></strong></td>
                <td class="num m-hide"><?= e($fmtDays($r['excuse_year'])) ?></td>
                <td class="cell-actions m-hide">
                    <?php if (can('users.manage')): ?><a href="/settings/users/<?= $r['id'] ?>" class="btn btn-sm btn-outline"><?= e(__('clients.edit')) ?></a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
