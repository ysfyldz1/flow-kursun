<p style="margin:16px 0 4px;font-weight:600;font-size:15px"><?= $urgent ? '<span style="color:#dc2626">' . e(__('tasks.priority_urgent')) . ' · </span>' : '' ?><?= e($title) ?></p>
<?php if ($project || $due): ?>
    <p style="margin:0;color:#64748b;font-size:13px"><?= e(implode(' · ', array_filter([$project, $due ? __('tasks.due') . ': ' . format_date($due) : null]))) ?></p>
<?php endif; ?>
<?php if ($description): ?><p style="margin:10px 0 0"><?= nl2br(e($description)) ?></p><?php endif; ?>
