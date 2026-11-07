<?php
use App\Core\View;
use App\Services\TaskService;

$row = fn (string $label, ?string $value) => $value !== null && $value !== ''
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . $value . '</dd></div>'
    : '';
?>
<div class="page-head">
    <div>
        <a href="<?= $t['project_id'] ? '/projects/' . $t['project_id'] : '/tasks' ?>" class="back-link">← <?= e($t['project_id'] ? $t['project_name'] : __('tasks.title')) ?></a>
        <div class="title-line">
            <h1><?= e($t['title']) ?></h1>
            <?php if ($t['done_at']): ?>
                <span class="badge badge-green"><?= e(__('tasks.state_done')) ?></span>
            <?php elseif (TaskService::isOverdue($t)): ?>
                <span class="badge badge-red"><?= e(__('tasks.overdue')) ?></span>
            <?php endif; ?>
        </div>
        <p class="muted"><?= e(__('tasks.given_by', ['name' => (string) $t['creator_name'], 'date' => format_date(substr($t['created_at'], 0, 10))])) ?></p>
    </div>
    <div class="form-actions" style="margin:0">
        <form method="post" action="/tasks/<?= $t['id'] ?>/done">
            <?= csrf_field() ?>
            <button class="btn <?= $t['done_at'] ? 'btn-outline' : 'btn-primary' ?>"><?= icon('check') ?><?= e(__($t['done_at'] ? 'tasks.reopen' : 'tasks.complete')) ?></button>
        </form>
        <?php if ($edit): ?>
            <form method="post" action="/tasks/<?= $t['id'] ?>/delete" onsubmit="return confirm('<?= e(__('app.confirm_delete')) ?>')">
                <?= csrf_field() ?><button class="btn btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($edit): ?>
    <div class="grid grid-3-1">
        <section class="card form-card">
            <?= View::partial('Tasks::_form', ['t' => $t, 'projects' => $projects, 'users' => $users, 'me' => $me]) ?>
        </section>
    </div>
<?php else: ?>
    <div class="grid grid-3-1">
        <section class="card widget widget-auto">
            <dl class="dl">
                <?= $row('time.project', $t['project_id'] ? '<a href="/projects/' . $t['project_id'] . '" class="link">' . e($t['project_number'] . ' · ' . $t['project_name']) . '</a> — ' . e($t['client_name']) : null) ?>
                <?= $row('tasks.due', $t['due_date'] ? e(format_date($t['due_date'])) : null) ?>
                <?= $row('tasks.priority', e(__('tasks.priority_' . $t['priority']))) ?>
                <?= $row('tasks.assignees', e((string) $t['assignee_names'])) ?>
                <?= $row('tasks.description', $t['description'] ? nl2br(e($t['description'])) : null) ?>
                <?= $row('tasks.completed_by', $t['done_at'] ? e((string) $t['done_by_name']) . ' · ' . e(format_date($t['done_at'], true)) : null) ?>
            </dl>
        </section>
    </div>
<?php endif; ?>
