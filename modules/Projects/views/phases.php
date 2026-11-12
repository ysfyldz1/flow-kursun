<?php
use App\Services\TimeService;

$p = $project;
$canEdit = can('projects.edit');
$canRates = can('rates.view');
$f = $editing;
$isOos = $f && $f['is_out_of_scope'];
$v = fn (string $k, $default = '') => old($k, (string) ($f[$k] ?? $default));
$csrf = csrf_field();
$post = fn (string $action, string $label, array $fields = [], string $class = 'btn btn-sm btn-outline', string $confirm = '') =>
    '<form method="post" action="/projects/' . $p['id'] . '/phases/' . $action . '" class="inline-form"'
    . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ')"' : '') . '>' . $csrf
    . implode('', array_map(fn ($k, $val) => '<input type="hidden" name="' . e($k) . '" value="' . e($val) . '">', array_keys($fields), $fields))
    . '<button class="' . $class . '">' . $label . '</button></form>';
$regular = array_values(array_filter($phases, fn ($ph) => !$ph['is_out_of_scope']));
?>
<div class="page-head">
    <div>
        <a href="/projects/<?= $p['id'] ?>" class="back-link">← <?= e($p['name']) ?></a>
        <div class="title-line">
            <h1><?= e(__('phases.title')) ?></h1>
            <span class="code code-lg"><?= e($p['number']) ?></span>
        </div>
        <p class="muted"><?= e(__('phases.subtitle')) ?></p>
    </div>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <?php if (!$phases): ?>
            <section class="card"><div class="empty"><?= e(__('phases.none')) ?></div></section>
        <?php endif; ?>

        <?php foreach ($phases as $ph): $n = array_search($ph, $regular, true); ?>
            <section class="card phase-card<?= $ph['is_active'] ? '' : ' phase-inactive' ?>" id="phase-<?= $ph['id'] ?>">
                <header class="phase-head">
                    <div class="phase-title">
                        <?php if (!$ph['is_out_of_scope']): ?><span class="phase-no"><?= $n + 1 ?></span><?php endif; ?>
                        <h2><?= e($ph['name']) ?></h2>
                        <?php if ($ph['is_out_of_scope']): ?><span class="badge badge-gray"><?= e(__('phases.oos_badge')) ?></span><?php endif; ?>
                        <?php if ($ph['is_optional']): ?>
                            <span class="badge <?= $ph['approved_at'] ? 'badge-green' : 'badge-amber' ?>"><?= e(__($ph['approved_at'] ? 'phases.optional_approved' : 'phases.optional_pending')) ?></span>
                        <?php endif; ?>
                        <?php if ($ph['completed_at']): ?><span class="badge badge-green"><?= e(__('phases.completed')) ?></span><?php endif; ?>
                        <?php if ($ph['is_overdue']): ?><span class="badge badge-red"><?= e(__('phases.overdue')) ?></span><?php endif; ?>
                    </div>
                    <?php if ($canEdit): ?>
                        <div class="phase-actions">
                            <?php if (can('statements.manage') && $ph['is_active'] && $ph['entry_count'] + (int) ($ph['fee'] !== null) > 0): ?>
                                <a href="/billing/new?project_id=<?= $p['id'] ?>&phase_id=<?= $ph['id'] ?>" class="btn btn-sm btn-outline"><?= e(__('phases.make_statement')) ?></a>
                            <?php endif; ?>
                            <?php if (!$ph['is_out_of_scope']): ?>
                                <a href="?edit=<?= $ph['id'] ?>#phase-form" class="btn btn-sm btn-outline"><?= e(__('clients.edit')) ?></a>
                                <?php if ($n > 0): ?><?= $post($ph['id'] . '/move', '↑', ['dir' => 'up'], 'btn btn-sm btn-outline', '') ?><?php endif; ?>
                                <?php if ($n < count($regular) - 1): ?><?= $post($ph['id'] . '/move', '↓', ['dir' => 'down'], 'btn btn-sm btn-outline', '') ?><?php endif; ?>
                                <?php if ($ph['is_optional']): ?>
                                    <?= $post($ph['id'] . '/approve', e(__($ph['approved_at'] ? 'phases.unapprove' : 'phases.approve')), ['approved' => $ph['approved_at'] ? '' : '1'], $ph['approved_at'] ? 'btn btn-sm btn-outline' : 'btn btn-sm btn-primary') ?>
                                <?php endif; ?>
                                <?php if ($ph['is_active']): ?>
                                    <?= $post($ph['id'] . '/complete', e(__($ph['completed_at'] ? 'phases.reopen' : 'phases.complete')), ['done' => $ph['completed_at'] ? '' : '1']) ?>
                                <?php endif; ?>
                                <?php if (!$ph['entry_count']): ?>
                                    <?= $post($ph['id'] . '/delete', e(__('app.delete')), [], 'btn btn-sm btn-danger', __('app.confirm_delete')) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="phase-facts">
                    <?php if ($canRates && $ph['fee'] !== null): ?>
                        <div><span><?= e(__('phases.fee')) ?></span><strong><?= e(format_money((float) $ph['fee'], $p['currency'])) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($ph['deadline']): ?>
                        <div>
                            <span><?= e(__('phases.duration')) ?></span>
                            <strong><?= (int) $ph['duration_value'] ?> <?= e(__('phases.unit_' . $ph['duration_unit'])) ?></strong>
                            <small class="muted"><?= e(format_date($ph['start'])) ?> → <span class="<?= $ph['is_overdue'] ? 'text-red' : '' ?>"><?= e(format_date($ph['deadline'])) ?></span></small>
                        </div>
                    <?php endif; ?>
                    <div><span><?= e(__('time.total')) ?></span><strong><?= TimeService::hours((int) $ph['minutes']) ?> <?= e(__('projects.hours')) ?></strong></div>
                    <?php if ($canRates): ?>
                        <div><span><?= e(__('phases.spent')) ?></span><strong><?= e(format_money((float) $ph['spent'], $p['currency'])) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($canRates && $ph['budget_state']): ?>
                        <div>
                            <span><?= e(__($ph['over'] ? 'phases.over' : 'phases.remaining')) ?></span>
                            <strong class="<?= $ph['over'] ? 'text-red' : ($ph['budget_state'] === 'warn' ? 'text-warn' : '') ?>"><?= e(format_money($ph['over'] ?: (float) $ph['remaining'], $p['currency'])) ?></strong>
                            <span class="meter"><span style="width: <?= min(100, (int) $ph['used_pct']) ?>%" class="<?= $ph['budget_state'] === 'ok' ? '' : ($ph['budget_state'] === 'over' ? 'meter-over' : 'meter-warn') ?>"></span></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($canRates && $ph['over']): ?>
                    <p class="phase-note text-red small"><?= e(__('phases.over_note', ['amount' => format_money($ph['over'], $p['currency'])])) ?></p>
                <?php elseif ($canRates && $ph['budget_state'] === 'warn'): ?>
                    <p class="phase-note text-warn small"><?= e(__('phases.warn_note', ['pct' => $ph['used_pct']])) ?></p>
                <?php endif; ?>
                <?php if ($ph['is_out_of_scope'] && !$ph['scope_items']): ?>
                    <p class="phase-note muted small"><?= e(__('phases.oos_hint')) ?></p>
                <?php endif; ?>

                <?php if ($ph['scope_items']): ?>
                    <ul class="phase-scope">
                        <?php foreach ($ph['scope_items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="stack">
        <?php if ($canEdit): ?>
            <form method="post" action="/projects/<?= $p['id'] ?>/phases<?= $f ? '/' . $f['id'] : '' ?>" class="card form-card" id="phase-form">
                <?= $csrf ?>
                <h3 class="card-title"><?= e(__($f ? 'phases.edit' : 'phases.add')) ?></h3>
                <div class="form-stack">
                    <label class="field">
                        <span><?= e(__('phases.name')) ?> *</span>
                        <input name="name" value="<?= e($v('name')) ?>" required maxlength="255" placeholder="<?= e(__('phases.name_hint')) ?>">
                        <?= error_for('name') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('phases.scope')) ?></span>
                        <textarea name="scope" rows="6" placeholder="<?= e(__('phases.scope_hint')) ?>"><?= e($v('scope')) ?></textarea>
                        <small class="muted"><?= e(__('phases.scope_help')) ?></small>
                    </label>
                    <?php if (!$isOos): ?>
                        <?php if ($canRates): ?>
                            <label class="field">
                                <span><?= e(__('phases.fee')) ?> (<?= e($p['currency']) ?>)</span>
                                <input name="fee" value="<?= e(old('fee', money_input($f['fee'] ?? null))) ?>" inputmode="decimal">
                                <?= error_for('fee') ?>
                            </label>
                        <?php endif; ?>
                        <div class="form-grid-2">
                            <label class="field">
                                <span><?= e(__('phases.duration')) ?></span>
                                <input name="duration_value" value="<?= e($v('duration_value')) ?>" inputmode="numeric" placeholder="3">
                                <?= error_for('duration_value') ?>
                            </label>
                            <label class="field">
                                <span>&nbsp;</span>
                                <select name="duration_unit">
                                    <?php foreach (\App\Services\PhaseService::UNITS as $u): ?>
                                        <option value="<?= $u ?>" <?= $v('duration_unit', 'month') === $u ? 'selected' : '' ?>><?= e(__('phases.unit_' . $u)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <label class="field">
                            <span><?= e(__('phases.start_date')) ?></span>
                            <input type="date" name="start_date" value="<?= e($v('start_date')) ?>">
                            <small class="muted"><?= e(__('phases.start_help', ['date' => format_date($p['opened_at'])])) ?></small>
                            <?= error_for('start_date') ?>
                        </label>
                        <label class="check">
                            <input type="checkbox" name="is_optional" value="1" <?= $v('is_optional') === '1' ? 'checked' : '' ?>>
                            <span><?= e(__('phases.optional')) ?> <span class="muted small">— <?= e(__('phases.optional_help')) ?></span></span>
                        </label>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <?php if ($f): ?><a href="/projects/<?= $p['id'] ?>/phases" class="btn btn-outline"><?= e(__('app.cancel')) ?></a><?php endif; ?>
                    <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
                </div>
            </form>

            <?php if ($canRates): ?>
                <form method="post" action="/projects/<?= $p['id'] ?>/phases/settings" class="card form-card">
                    <?= $csrf ?>
                    <h3 class="card-title"><?= e(__('phases.settings')) ?></h3>
                    <label class="field">
                        <span><?= e(__('phases.warn_pct')) ?></span>
                        <input name="phase_warn_pct" value="<?= (int) $p['phase_warn_pct'] ?>" inputmode="numeric">
                        <small class="muted"><?= e(__('phases.warn_pct_help')) ?></small>
                    </label>
                    <div class="form-actions"><button class="btn btn-outline"><?= e(__('app.save')) ?></button></div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
