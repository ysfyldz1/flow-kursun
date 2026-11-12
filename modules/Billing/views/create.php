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
        <?php // Kurşun: kapsam — müvekkilin tamamı, tek proje ya da tek aşama ?>
        <label class="field field-wide">
            <span><?= e(__('billing.scope_project')) ?></span>
            <select name="project_id" id="st-project">
                <option value=""><?= e(__('billing.scope_all_projects')) ?></option>
                <?php foreach ($scopeProjects as $sp): ?>
                    <option value="<?= $sp['id'] ?>" data-client="<?= $sp['client_id'] ?>" data-currency="<?= $sp['currency'] ?>" <?= old('project_id', (string) ($_GET['project_id'] ?? '')) == $sp['id'] ? 'selected' : '' ?>><?= e($sp['number'] . ' · ' . $sp['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error_for('project_id') ?>
        </label>
        <label class="field field-wide" id="st-phase-field" hidden>
            <span><?= e(__('phases.field')) ?></span>
            <select name="phase_id" id="st-phase">
                <option value=""><?= e(__('billing.scope_all_phases')) ?></option>
                <?php foreach ($scopePhases as $sph): if ($sph['is_optional'] && !$sph['approved_at']) continue; ?>
                    <option value="<?= $sph['id'] ?>" data-project="<?= $sph['project_id'] ?>" <?= old('phase_id', (string) ($_GET['phase_id'] ?? '')) == $sph['id'] ? 'selected' : '' ?>><?= e($sph['is_out_of_scope'] ? __('phases.out_of_scope') : $sph['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="muted"><?= e(__('billing.scope_phase_hint')) ?></small>
            <?= error_for('phase_id') ?>
        </label>
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
(() => {
    const client = document.getElementById('st-client'), currency = document.getElementById('st-currency'),
          project = document.getElementById('st-project'), phase = document.getElementById('st-phase'),
          phaseField = document.getElementById('st-phase-field');
    // Proje listesi seçili müvekkil ve para birimine göre, aşama listesi seçili projeye göre süzülür
    const filter = () => {
        [...project.options].forEach(o => { if (o.value) o.hidden = o.dataset.client !== client.value || o.dataset.currency !== currency.value; });
        if (project.selectedOptions[0]?.hidden) project.value = '';
        let any = false;
        [...phase.options].forEach(o => { if (o.value) { o.hidden = o.dataset.project !== project.value; any ||= !o.hidden; } });
        if (phase.selectedOptions[0]?.hidden) phase.value = '';
        phaseField.hidden = !any;
    };
    client.addEventListener('change', () => {
        const cur = client.selectedOptions[0]?.dataset.currency;
        if (cur) currency.value = cur;
        filter();
    });
    currency.addEventListener('change', filter);
    project.addEventListener('change', filter);
    // Projeden gelindiyse müvekkil ve para birimi projeden alınır
    const pre = project.selectedOptions[0];
    if (pre?.value && !client.value) { client.value = pre.dataset.client; currency.value = pre.dataset.currency; }
    filter();
})();
</script>
