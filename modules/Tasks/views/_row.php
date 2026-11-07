<?php
// Tek görev satırı: liste, proje sayfası ve panoda ortak. Soldaki daire tek tıkla tamamlar/geri açar.
use App\Services\TaskService;

$overdue = TaskService::isOverdue($t);
$today = $t['due_date'] === date('Y-m-d');
?>
<div class="widget-row task-row<?= $t['done_at'] ? ' is-done' : '' ?><?= $overdue ? ' is-overdue' : '' ?>">
    <?php if (TaskService::canComplete($t)): ?>
        <form method="post" action="/tasks/<?= $t['id'] ?>/done">
            <?= csrf_field() ?>
            <button class="task-check" title="<?= e(__($t['done_at'] ? 'tasks.reopen' : 'tasks.complete')) ?>"><?= icon('check') ?></button>
        </form>
    <?php endif; ?>
    <a href="/tasks/<?= $t['id'] ?>" class="cell-main">
        <strong>
            <?php if ($t['priority'] === 'urgent' && !$t['done_at']): ?><span class="badge badge-red"><?= e(__('tasks.priority_urgent')) ?></span><?php endif; ?>
            <?= e($t['title']) ?>
        </strong>
        <span>
            <?php if ($t['project_id'] && empty($hideProject)): ?><?= e($t['project_number']) ?> · <?= e($t['client_name']) ?> · <?php endif; ?>
            <?= e((string) $t['assignee_names']) ?>
        </span>
    </a>
    <div class="cell-main align-right nowrap">
        <?php if ($t['done_at']): ?>
            <strong class="muted"><?= e(format_date(substr($t['done_at'], 0, 10))) ?></strong>
            <span><?= e((string) $t['done_by_name']) ?></span>
        <?php elseif ($t['due_date']): ?>
            <strong class="<?= $overdue ? 'text-danger' : ($today ? 'text-warn' : '') ?>"><?= e($today ? __('tasks.today') : format_date($t['due_date'])) ?></strong>
            <?php if ($overdue): ?><span class="text-danger"><?= e(__('tasks.overdue')) ?></span><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
