<?php
use App\Services\TaskService;

$val = fn (string $k, string $default = '') => old($k, (string) ($t[$k] ?? $default));
$projectId = (int) $val('project_id', (string) ($_GET['project_id'] ?? ''));
$assignees = has_errors()
    ? array_map('intval', (array) ($GLOBALS['__old']['assignees'] ?? []))
    : ($t['assignees'] ?? [$me]);
?>
<form method="post" action="<?= $t ? '/tasks/' . $t['id'] : '/tasks' ?>" class="form">
    <?= csrf_field() ?>
    <?= error_for('task') ?>
    <label class="field">
        <span><?= e(__('tasks.task')) ?> *</span>
        <input name="title" value="<?= e($val('title')) ?>" required maxlength="255" placeholder="<?= e(__('tasks.title_hint')) ?>">
        <?= error_for('title') ?>
    </label>
    <label class="field">
        <span><?= e(__('time.project')) ?></span>
        <select name="project_id">
            <option value=""><?= e(__('tasks.no_project')) ?></option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['number'] . ' · ' . $p['name'] . ' — ' . $p['client_name']) ?></option>
            <?php endforeach; ?>
            <?php if ($t && $t['project_id'] && !in_array((int) $t['project_id'], array_map('intval', array_column($projects, 'id')), true)): ?>
                <option value="<?= $t['project_id'] ?>" selected><?= e($t['project_number'] . ' · ' . $t['project_name']) ?></option>
            <?php endif; ?>
        </select>
        <?= error_for('project_id') ?>
    </label>
    <div class="form-grid">
        <label class="field">
            <span><?= e(__('tasks.due')) ?></span>
            <input type="date" name="due_date" value="<?= e($val('due_date')) ?>">
            <?= error_for('due_date') ?>
        </label>
        <label class="field">
            <span><?= e(__('tasks.priority')) ?></span>
            <select name="priority">
                <?php foreach (TaskService::PRIORITIES as $pr): ?>
                    <option value="<?= $pr ?>" <?= $val('priority', 'normal') === $pr ? 'selected' : '' ?>><?= e(__('tasks.priority_' . $pr)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="field">
        <span><?= e(__('tasks.assignees')) ?> *</span>
        <div class="check-grid">
            <?php foreach ($users as $u): ?>
                <label class="check"><input type="checkbox" name="assignees[]" value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $assignees, true) ? 'checked' : '' ?>><span><?= e($u['name']) ?><?= (int) $u['id'] === $me ? ' (' . e(__('tasks.me')) . ')' : '' ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= error_for('assignees') ?>
    </div>
    <label class="field">
        <span><?= e(__('tasks.description')) ?></span>
        <textarea name="description" rows="3"><?= e($val('description')) ?></textarea>
    </label>
    <div class="form-actions form-actions-tight">
        <button class="btn btn-primary"><?= e(__($t ? 'app.save' : 'tasks.add')) ?></button>
    </div>
</form>
