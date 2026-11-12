<?php
use App\Services\TimeService;

// $entry: düzenlenen kayıt ya da null; $projects; $userId (kimin adına)
$last = $entry ? [] : ($_SESSION['_time_last'] ?? []);
// Bekleyenlerdeki "zaman girilmemiş" bağlantısı o günü seçili açar
if (!$entry && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) && $_GET['date'] <= date('Y-m-d')) {
    $last['work_date'] = $_GET['date'];
}
$val = fn (string $k, string $default = '') => old($k, (string) ($entry[$k] ?? $last[$k] ?? $default));
$duration = old('duration', $entry ? TimeService::formatMinutes((int) $entry['raw_minutes']) : '');
$billable = has_errors() ? !empty($GLOBALS['__old']['is_billable']) : (bool) ($entry['is_billable'] ?? true);
$selected = (int) $val('project_id');
$mine = array_filter($projects, fn ($p) => $p['is_member']);
$others = array_filter($projects, fn ($p) => !$p['is_member']);
$option = fn ($p) => sprintf(
    '<option value="%d" data-billing="%s" %s>%s · %s — %s</option>',
    $p['id'], e($p['billing_type']), (int) $p['id'] === $selected ? 'selected' : '',
    e($p['number']), e($p['name']), e($p['client_name'])
);
?>
<form method="post" action="<?= $entry ? '/time/' . $entry['id'] : '/time' ?>" class="form time-form">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">

    <label class="field">
        <span><?= e(__('time.project')) ?> *</span>
        <select name="project_id" required data-project>
            <option value=""><?= e(__('time.choose_project')) ?></option>
            <?php if ($mine): ?>
                <optgroup label="<?= e(__('dash.my_projects')) ?>"><?= implode('', array_map($option, $mine)) ?></optgroup>
            <?php endif; ?>
            <?php if ($others): ?>
                <optgroup label="<?= e(__('time.other_projects')) ?>"><?= implode('', array_map($option, $others)) ?></optgroup>
            <?php endif; ?>
        </select>
        <?= error_for('project_id') ?>
    </label>

    <?php // Kurşun: aşama seçimi; yalnız aşaması olan projede görünür (script doldurur)
    $phaseMap = \App\Services\PhaseService::optionsForProjects(array_column($projects, 'id')); ?>
    <label class="field" data-phase-field hidden
           data-phases="<?= e(json_encode($phaseMap, JSON_UNESCAPED_UNICODE)) ?>"
           data-selected="<?= (int) $val('phase_id') ?>">
        <span><?= e(__('phases.field')) ?></span>
        <select name="phase_id" data-phase></select>
        <small class="muted" data-phase-hint></small>
        <?= error_for('phase_id') ?>
    </label>

    <div class="form-grid">
        <label class="field">
            <span><?= e(__('time.date')) ?> *</span>
            <input type="date" name="work_date" value="<?= e($val('work_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required>
            <?= error_for('work_date') ?>
        </label>
        <label class="field">
            <span><?= e(__('time.duration')) ?> *</span>
            <div class="dur-wrap">
                <input name="duration" value="<?= e($duration) ?>" placeholder="<?= TimeService::decimal() ? (\App\Core\Lang::locale() === 'tr' ? '1,5' : '1.5') : '1:30' ?>" required autocomplete="off" data-duration>
                <span class="dur-step"><button type="button" data-step="6" tabindex="-1" aria-label="+6">▲</button><button type="button" data-step="-6" tabindex="-1" aria-label="-6">▼</button></span>
            </div>
            <small class="muted" data-duration-preview><?= e(__('time.duration_hint')) ?></small>
            <?= error_for('duration') ?>
        </label>
    </div>

    <label class="field">
        <span><?= e(__('time.description')) ?> *</span>
        <textarea name="description" rows="3" required placeholder="<?= e(__('time.description_hint')) ?>"><?= e($val('description')) ?></textarea>
        <?= error_for('description') ?>
    </label>

    <label class="check" data-billable>
        <input type="checkbox" name="is_billable" value="1" <?= $billable ? 'checked' : '' ?>>
        <span><?= e(__('time.billable')) ?></span>
    </label>

    <div class="form-actions form-actions-tight">
        <?php if ($entry): ?>
            <a href="/time?week=<?= e($entry['work_date']) ?>" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
        <?php endif; ?>
        <button class="btn btn-primary btn-block-sm"><?= e(__($entry ? 'app.save' : 'time.add')) ?></button>
    </div>
</form>
