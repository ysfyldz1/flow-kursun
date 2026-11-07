<?php $fmt = rtrim(rtrim(number_format((float) $days, 1, ',', '.'), '0'), ','); ?>
<table style="margin:16px 0 0;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:3px 16px 3px 0;color:#64748b"><?= e(__('leaves.type')) ?></td><td style="font-weight:600"><?= e(__('leaves.type_' . $type)) ?></td></tr>
    <tr><td style="padding:3px 16px 3px 0;color:#64748b"><?= e(__('leaves.dates')) ?></td><td style="font-weight:600"><?= e(format_date($start)) ?><?= $end !== $start ? ' – ' . e(format_date($end)) : '' ?></td></tr>
    <tr><td style="padding:3px 16px 3px 0;color:#64748b"><?= e(__('leaves.days')) ?></td><td style="font-weight:600"><?= e($fmt) ?> <?= e(__('leaves.workdays')) ?></td></tr>
    <?php if (!empty($reason)): ?><tr><td style="padding:3px 16px 3px 0;color:#64748b;vertical-align:top"><?= e(__('leaves.reason')) ?></td><td><?= nl2br(e($reason)) ?></td></tr><?php endif; ?>
</table>
