<?php use App\Services\UserService; ?>
<div class="page-head">
    <div>
        <a href="/billing" class="back-link">← <?= e(__('billing.title')) ?></a>
        <h1><?= e(__('billing.new')) ?></h1>
        <p class="muted"><?= e(__('billing.new_hint')) ?></p>
    </div>
</div>

<form method="post" action="/billing" class="card form-card narrow" id="new-statement">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label class="field field-wide">
            <span><?= e(__('clients.name')) ?> *</span>
            <select name="client_id" id="st-client" required>
                <option value=""><?= e(__('projects.choose_client')) ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" data-currency="<?= $c['currency'] ?>" <?= old('client_id') == $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error_for('client_id') ?>
        </label>
        <label class="field">
            <span><?= e(__('clients.currency')) ?></span>
            <select name="currency" id="st-currency">
                <?php foreach (UserService::CURRENCIES as $cur): ?>
                    <option <?= old('currency', 'TRY') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                <?php endforeach; ?>
            </select>
            <small class="muted"><?= e(__('billing.currency_hint')) ?></small>
        </label>
        <div></div>
        <label class="field">
            <span><?= e(__('billing.period_start')) ?></span>
            <input type="date" name="period_start" value="<?= e(old('period_start')) ?>">
            <small class="muted"><?= e(__('billing.period_start_hint')) ?></small>
            <?= error_for('period_start') ?>
        </label>
        <label class="field">
            <span><?= e(__('billing.period_end')) ?> *</span>
            <input type="date" name="period_end" value="<?= e(old('period_end', date('Y-m-d'))) ?>" required>
            <?= error_for('period_end') ?>
        </label>
    </div>
    <div class="form-actions">
        <a href="/billing" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
        <button class="btn btn-primary"><?= e(__('billing.create_draft')) ?></button>
    </div>
</form>

<script>
document.getElementById('st-client').addEventListener('change', e => {
    const cur = e.target.selectedOptions[0]?.dataset.currency;
    if (cur) document.getElementById('st-currency').value = cur;
});
</script>
