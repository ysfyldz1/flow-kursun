<?php
$p = $project;
$canRates = can('rates.view');
?>
<div class="page-head">
    <div>
        <a href="/projects/<?= $p['id'] ?>/phases" class="back-link">← <?= e(__('phases.title')) ?></a>
        <div class="title-line">
            <h1><?= e(__('phases.import_title')) ?></h1>
            <span class="code code-lg"><?= e($p['number']) ?></span>
        </div>
        <p class="muted"><?= e(__('phases.import_preview_hint', ['file' => $file, 'count' => count($rows)])) ?></p>
    </div>
</div>

<form method="post" action="/projects/<?= $p['id'] ?>/phases/import/save" class="stack">
    <?= csrf_field() ?>
    <?php foreach ($rows as $i => $r): ?>
        <section class="card form-card import-row">
            <div class="import-row-head">
                <label class="check">
                    <input type="checkbox" name="rows[<?= $i ?>][include]" value="1" checked>
                    <span><strong><?= e(__('phases.import_row', ['n' => $i + 1])) ?></strong></span>
                </label>
                <label class="check">
                    <input type="checkbox" name="rows[<?= $i ?>][is_optional]" value="1" <?= $r['is_optional'] ? 'checked' : '' ?>>
                    <span><?= e(__('phases.optional')) ?></span>
                </label>
            </div>
            <div class="form-grid">
                <label class="field field-wide">
                    <span><?= e(__('phases.name')) ?> *</span>
                    <input name="rows[<?= $i ?>][name]" value="<?= e($r['name']) ?>" required maxlength="255">
                </label>
                <label class="field field-wide">
                    <span><?= e(__('phases.scope')) ?></span>
                    <textarea name="rows[<?= $i ?>][scope]" rows="<?= max(3, min(10, substr_count($r['scope'], "\n") + 2)) ?>"><?= e($r['scope']) ?></textarea>
                    <small class="muted"><?= e(__('phases.scope_help')) ?></small>
                </label>
                <?php if ($canRates): ?>
                    <label class="field">
                        <span><?= e(__('phases.fee')) ?> (<?= e($p['currency']) ?>)</span>
                        <input name="rows[<?= $i ?>][fee]" value="<?= e(money_input($r['fee'])) ?>" inputmode="decimal" class="<?= $r['fee'] === null ? 'cell-missing' : '' ?>">
                        <?php if ($r['fee'] === null): ?><small class="text-warn"><?= e(__('phases.import_no_fee')) ?></small><?php endif; ?>
                    </label>
                <?php endif; ?>
                <div class="form-grid-2">
                    <label class="field">
                        <span><?= e(__('phases.duration')) ?></span>
                        <input name="rows[<?= $i ?>][duration_value]" inputmode="numeric" placeholder="3">
                    </label>
                    <label class="field">
                        <span>&nbsp;</span>
                        <select name="rows[<?= $i ?>][duration_unit]">
                            <?php foreach (\App\Services\PhaseService::UNITS as $u): ?>
                                <option value="<?= $u ?>" <?= $u === 'month' ? 'selected' : '' ?>><?= e(__('phases.unit_' . $u)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="form-actions">
        <button class="btn btn-outline" formaction="/projects/<?= $p['id'] ?>/phases/import/cancel" formnovalidate><?= e(__('app.cancel')) ?></button>
        <button class="btn btn-primary"><?= e(__('phases.import_save')) ?></button>
    </div>
</form>
