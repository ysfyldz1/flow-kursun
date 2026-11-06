<?php
use App\Services\CalendarService;

$val = fn (string $k, string $default = '') => old($k, $default);
$type = $val('type', (string) ($e['type'] ?? ($_GET['type'] ?? 'hearing')));
$selected = (int) $val('project_id', (string) $projectId);
$checked = has_errors() ? array_map('intval', (array) ($GLOBALS['__old']['attendees'] ?? [])) : $attendees;
$start = $e['starts_at'] ?? null;
// Kapalı projedeki kaydı düzenlerken proje listede yoksa eklenir
if ($e && $e['project_id'] && !in_array((int) $e['project_id'], array_map('intval', array_column($projects, 'id')), true)) {
    $projects[] = ['id' => $e['project_id'], 'number' => $e['project_number'], 'name' => $e['project_name'], 'client_name' => $e['client_name']];
}
?>
<div class="page-head">
    <div>
        <a href="<?= $e ? '/calendar/' . $e['id'] : '/calendar' ?>" class="back-link">← <?= e(__($e ? 'app.back' : 'cal.title')) ?></a>
        <h1><?= e(__($e ? 'cal.edit' : 'cal.new')) ?></h1>
    </div>
</div>

<form method="post" action="<?= $e ? '/calendar/' . $e['id'] : '/calendar' ?>" class="card form-card">
    <?= csrf_field() ?>
    <div class="tabs type-tabs">
        <?php foreach (CalendarService::TYPES as $t): ?>
            <label class="<?= $type === $t ? 'active' : '' ?>">
                <input type="radio" name="type" value="<?= $t ?>" <?= $type === $t ? 'checked' : '' ?> hidden><?= e(__('cal.type_' . $t)) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <?= error_for('type') ?>

    <div class="form-grid">
        <label class="field field-wide">
            <span><?= e(__('time.project')) ?><span data-type="hearing"> *</span></span>
            <select name="project_id">
                <option value=""><?= e(__('cal.no_project')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $p['id'] === $selected ? 'selected' : '' ?>><?= e($p['number'] . ' · ' . $p['name'] . ' — ' . $p['client_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error_for('project_id') ?>
        </label>
        <label class="field field-wide">
            <span><?= e(__('cal.event_title')) ?><span data-type="deadline meeting"> *</span></span>
            <input name="title" value="<?= e($val('title', (string) ($e['title'] ?? ''))) ?>" maxlength="255"
                   data-ph-hearing="<?= e(__('cal.title_ph_hearing')) ?>" data-ph-deadline="<?= e(__('cal.title_ph_deadline')) ?>" data-ph-meeting="<?= e(__('cal.title_ph_meeting')) ?>">
            <?= error_for('title') ?>
        </label>
        <label class="field">
            <span><?= e(__('time.date')) ?> *</span>
            <input type="date" name="date" value="<?= e($val('date', $start ? substr($start, 0, 10) : (string) ($_GET['date'] ?? date('Y-m-d')))) ?>" required>
            <?= error_for('date') ?>
        </label>
        <div class="form-grid form-grid-2 field">
            <label class="field">
                <span><?= e(__('cal.start')) ?><span data-type="hearing meeting"> *</span></span>
                <input type="time" name="start_time" value="<?= e($val('start_time', $start && !$e['all_day'] ? substr($start, 11, 5) : '')) ?>">
                <small class="muted" data-type="deadline"><?= e(__('cal.deadline_time_hint')) ?></small>
                <?= error_for('start_time') ?>
            </label>
            <label class="field" data-type="hearing meeting">
                <span><?= e(__('cal.end')) ?></span>
                <input type="time" name="end_time" value="<?= e($val('end_time', !empty($e['ends_at']) ? substr($e['ends_at'], 11, 5) : '')) ?>">
                <?= error_for('end_time') ?>
            </label>
        </div>
        <label class="field field-wide" data-type="hearing meeting">
            <span><?= e(__('cal.location')) ?></span>
            <input name="location" value="<?= e($val('location', (string) ($e['location'] ?? ''))) ?>" placeholder="<?= e(__('cal.location_ph')) ?>">
        </label>
        <label class="field field-wide">
            <span><?= e(__('cal.notes')) ?></span>
            <textarea name="notes" rows="3"><?= e($val('notes', (string) ($e['notes'] ?? ''))) ?></textarea>
        </label>
    </div>

    <h3 class="section-title"><?= e(__('cal.attendees')) ?> *</h3>
    <p class="muted small"><?= e(__(CalendarService::outlookEnabled() ? 'cal.attendees_hint_outlook' : 'cal.attendees_hint')) ?></p>
    <div class="check-grid">
        <?php foreach ($users as $u): ?>
            <label class="check">
                <input type="checkbox" name="attendees[]" value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $checked, true) ? 'checked' : '' ?>>
                <span><?= e($u['name']) ?><?php if ($u['title']): ?> <span class="muted small"><?= e($u['title']) ?></span><?php endif; ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?= error_for('attendees') ?>

    <div class="form-actions">
        <a href="<?= $e ? '/calendar/' . $e['id'] : '/calendar' ?>" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
        <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
    </div>
</form>
<script>
(() => {
    const form = document.currentScript.previousElementSibling;
    const title = form.querySelector('[name=title]');
    const sync = () => {
        const type = form.querySelector('[name=type]:checked').value;
        form.querySelectorAll('.type-tabs label').forEach(l => l.classList.toggle('active', l.querySelector('input').checked));
        form.querySelectorAll('[data-type]').forEach(el => { el.hidden = !el.dataset.type.split(' ').includes(type); });
        title.placeholder = title.dataset['ph' + type.charAt(0).toUpperCase() + type.slice(1)];
    };
    form.querySelectorAll('[name=type]').forEach(r => r.addEventListener('change', sync));
    sync();
})();
</script>
