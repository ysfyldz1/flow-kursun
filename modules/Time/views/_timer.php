<section class="card form-card timer-card <?= $timer ? 'running' : '' ?>"<?= $timer ? '' : ' data-fold-mobile="' . e(__('time.timer')) . '"' ?>>
    <?php if ($timer): ?>
        <div class="timer-head">
            <span class="timer-dot"></span>
            <div class="cell-main">
                <strong><?= e($timer['project_name']) ?></strong>
                <span><?= e($timer['project_number']) ?></span>
            </div>
            <span class="timer-clock" data-started="<?= strtotime($timer['started_at']) ?>" data-now="<?= time() ?>">0:00:00</span>
        </div>
        <form method="post" action="/timer/stop" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span><?= e(__('time.description')) ?> *</span>
                <textarea name="timer_description" rows="2" placeholder="<?= e(__('time.description_hint')) ?>"><?= e(old('timer_description', (string) $timer['description'])) ?></textarea>
                <?= error_for('timer_description') ?>
            </label>
            <?php // Kurşun: projenin aşaması varsa durdururken seçilir
            $timerPhases = \App\Services\PhaseService::optionsForProjects([(int) $timer['project_id']])[(int) $timer['project_id']] ?? [];
            if ($timerPhases): $lastPhase = (int) old('phase_id', (string) ($_SESSION['_time_last']['phase_id'] ?? '')); ?>
                <label class="field">
                    <span><?= e(__('phases.field')) ?></span>
                    <select name="phase_id">
                        <option value=""><?= e(__('phases.choose_none')) ?></option>
                        <?php foreach ($timerPhases as $tp): ?>
                            <option value="<?= $tp['id'] ?>" <?= $tp['active'] ? '' : 'disabled' ?> <?= $tp['id'] === $lastPhase && $tp['active'] ? 'selected' : '' ?>>
                                <?= e($tp['name'] . ($tp['active'] ? '' : ' (' . __('phases.not_approved_short') . ')') . ($tp['state'] === 'over' ? ' ⚠' : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="check"><input type="checkbox" name="is_billable" value="1" checked><span><?= e(__('time.billable')) ?></span></label>
            <div class="timer-actions">
                <button class="btn btn-primary"><?= e(__('time.stop_save')) ?></button>
                <button class="btn btn-outline btn-danger" formaction="/timer/discard" formnovalidate onclick="return confirm('<?= e(__('time.discard_confirm')) ?>')"><?= e(__('time.discard')) ?></button>
            </div>
        </form>
    <?php else: ?>
        <h3 class="card-title"><?= icon('clock') ?> <?= e(__('time.timer')) ?></h3>
        <form method="post" action="/timer/start" class="form">
            <?= csrf_field() ?>
            <select name="timer_project_id" required class="select-plain">
                <option value=""><?= e(__('time.choose_project')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['number'] . ' · ' . $p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error_for('timer_project_id') ?>
            <input name="timer_description" class="input-plain" placeholder="<?= e(__('time.timer_desc_hint')) ?>">
            <button class="btn btn-outline">▶ <?= e(__('time.start')) ?></button>
        </form>
    <?php endif; ?>
</section>
