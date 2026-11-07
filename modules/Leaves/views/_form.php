<?php
use App\Services\LeaveService;

$type = old('type', 'annual');
$checked = fn (string $k) => has_errors() && !empty($GLOBALS['__old'][$k]) ? 'checked' : '';
?>
<form method="post" action="/leaves" enctype="multipart/form-data" class="form" id="leave-form">
    <?= csrf_field() ?>
    <?php if ($users): ?>
        <label class="field">
            <span><?= e(__('leaves.employee')) ?></span>
            <select name="user_id">
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) old('user_id', (string) auth()['id']) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>
    <label class="field">
        <span><?= e(__('leaves.type')) ?> *</span>
        <select name="type" required>
            <?php foreach (LeaveService::TYPES as $t): ?>
                <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(__('leaves.type_' . $t)) ?></option>
            <?php endforeach; ?>
        </select>
        <?= error_for('type') ?>
    </label>
    <div class="form-grid">
        <label class="field">
            <span><?= e(__('leaves.start')) ?> *</span>
            <input type="date" name="start_date" value="<?= e(old('start_date')) ?>" required>
            <?= error_for('start_date') ?>
        </label>
        <label class="field">
            <span><?= e(__('leaves.end')) ?> *</span>
            <input type="date" name="end_date" value="<?= e(old('end_date')) ?>" required>
            <?= error_for('end_date') ?>
        </label>
    </div>
    <label class="check"><input type="checkbox" name="half_start" value="1" <?= $checked('half_start') ?>><span><?= e(__('leaves.half_start')) ?></span></label>
    <label class="check" data-multi><input type="checkbox" name="half_end" value="1" <?= $checked('half_end') ?>><span><?= e(__('leaves.half_end')) ?></span></label>
    <p class="muted small" data-days hidden></p>
    <label class="field">
        <span><?= e(__('leaves.reason')) ?></span>
        <textarea name="reason" rows="2"><?= e(old('reason')) ?></textarea>
    </label>
    <label class="field" data-sick <?= $type === 'sick' ? '' : 'hidden' ?>>
        <span><?= e(__('leaves.report')) ?></span>
        <input type="file" name="report" accept=".pdf,.jpg,.jpeg,.png">
        <small class="muted"><?= e(__('leaves.report_hint')) ?></small>
        <?= error_for('report') ?>
    </label>
    <?php if ($approvers): ?>
        <p class="muted small"><?= e(__('leaves.goes_to', ['names' => implode(', ', array_column($approvers, 'name'))])) ?></p>
    <?php endif; ?>
    <div class="form-actions form-actions-tight">
        <button class="btn btn-primary"><?= e(__('leaves.submit')) ?></button>
    </div>
</form>
<script>
(() => {
    const f = document.getElementById('leave-form');
    const out = f.querySelector('[data-days]');
    const label = <?= json_encode(__('leaves.days_preview')) ?>;
    const refresh = async () => {
        const s = f.start_date.value, e = f.end_date.value || s;
        f.querySelector('[data-multi]').hidden = !s || s === e;
        f.querySelector('[data-sick]').hidden = f.type.value !== 'sick';
        if (!s) { out.hidden = true; return; }
        if (!f.end_date.value || f.end_date.value < s) f.end_date.value = s;
        const q = new URLSearchParams({ start: s, end: f.end_date.value, half_start: f.half_start.checked ? 1 : '', half_end: f.half_end.checked ? 1 : '' });
        const r = await fetch('/leaves/days?' + q).then(r => r.json()).catch(() => ({}));
        out.hidden = r.days == null;
        out.textContent = label.replace(':days', String(r.days).replace('.', ','));
    };
    f.addEventListener('change', refresh);
    refresh();
})();
</script>
