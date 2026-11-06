<?php
use App\Services\ProjectService;
use App\Services\UserService;

$p = $project;
$v = fn (string $k, $default = '') => old($k, (string) ($p[$k] ?? $default));
$selectedClient = (int) ($p['client_id'] ?? old('client_id', (string) $clientId));
$clientCurrency = '';
foreach ($clients as $c) {
    if ((int) $c['id'] === $selectedClient) {
        $clientCurrency = $c['currency'];
    }
}
if (has_errors()) {
    $memberIds = array_map('intval', (array) ($GLOBALS['__old']['members'] ?? []));
    $memberRates = (array) ($GLOBALS['__old']['member_rates'] ?? []);
} else {
    $memberIds = array_map('intval', array_column($p['members'] ?? [], 'user_id'));
    $memberRates = array_column($p['members'] ?? [], 'hourly_rate', 'user_id');
}
$billing = $v('billing_type', 'hourly');
$currency = $v('currency', $clientCurrency ?: 'TRY');
$back = $p ? '/projects/' . $p['id'] : ($clientId ? '/clients/' . $clientId : '/projects');
?>
<div class="page-head">
    <div>
        <a href="<?= $back ?>" class="back-link">← <?= e($p ? $p['name'] : __('projects.title')) ?></a>
        <h1><?= e($p ? __('projects.edit') : __('projects.new')) ?></h1>
    </div>
    <?php if ($p): ?><span class="code code-lg"><?= e($p['number']) ?></span><?php endif; ?>
</div>

<form method="post" action="<?= $p ? '/projects/' . $p['id'] : '/projects' ?>" class="form-stack" id="project-form">
    <?= csrf_field() ?>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('clients.section_general')) ?></h3>
        <div class="form-grid">
            <label class="field field-wide">
                <span><?= e(__('clients.name')) ?> *</span>
                <?php if ($p): ?>
                    <input value="<?= e($p['client_code'] . ' · ' . $p['client_name']) ?>" disabled>
                    <small class="muted"><?= e(__('projects.client_locked')) ?></small>
                <?php else: ?>
                    <select name="client_id" id="client" required>
                        <option value=""><?= e(__('projects.choose_client')) ?></option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" data-currency="<?= $c['currency'] ?>" <?= $selectedClient === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['code'] . ' · ' . $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?= error_for('client_id') ?>
            </label>
            <label class="field field-wide">
                <span><?= e(__('projects.name')) ?> *</span>
                <input name="name" value="<?= e($v('name')) ?>" required placeholder="<?= e(__('projects.name_hint')) ?>">
                <?= error_for('name') ?>
            </label>
            <label class="field">
                <span><?= e(__('projects.type')) ?></span>
                <select name="type" id="ptype">
                    <?php foreach (ProjectService::TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= $v('type', 'advisory') === $t ? 'selected' : '' ?>><?= e(__('projects.type_' . $t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span><?= e(__('projects.responsible')) ?></span>
                <select name="responsible_user_id">
                    <option value="">—</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int) $v('responsible_user_id', $p ? '' : (string) auth()['id']) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field" data-litigation>
                <span><?= e(__('projects.court')) ?></span>
                <input name="court" value="<?= e($v('court')) ?>" placeholder="<?= e(__('projects.court_hint')) ?>">
            </label>
            <label class="field" data-litigation>
                <span><?= e(__('projects.case_number')) ?></span>
                <input name="case_number" value="<?= e($v('case_number')) ?>" placeholder="2026/123 E.">
            </label>
            <label class="field">
                <span><?= e(__('projects.opened_at')) ?></span>
                <input type="date" name="opened_at" value="<?= e($v('opened_at', date('Y-m-d'))) ?>">
                <?= error_for('opened_at') ?>
            </label>
            <?php if ($p): ?>
                <label class="field">
                    <span><?= e(__('users.status')) ?></span>
                    <select name="status">
                        <?php foreach (ProjectService::STATUSES as $s): ?>
                            <option value="<?= $s ?>" <?= $v('status') === $s ? 'selected' : '' ?>><?= e(__('projects.status_' . $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="field field-wide">
                <span><?= e(__('projects.description')) ?></span>
                <textarea name="description" rows="3"><?= e($v('description')) ?></textarea>
            </label>
            <label class="check field-wide">
                <input type="checkbox" name="portal_visible" value="1" <?= (has_errors() ? !empty($GLOBALS['__old']['portal_visible']) : (bool) ($p['portal_visible'] ?? true)) ? 'checked' : '' ?>>
                <span><?= e(__('projects.portal_visible')) ?> <span class="muted small">— <?= e(__('projects.portal_visible_hint')) ?></span></span>
            </label>
        </div>
    </section>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('clients.section_billing')) ?></h3>
        <?php if (can('rates.view')): ?>
            <div class="segmented">
                <?php foreach (ProjectService::BILLING as $b): ?>
                    <label><input type="radio" name="billing_type" value="<?= $b ?>" <?= $billing === $b ? 'checked' : '' ?>><span><?= e(__('projects.billing_' . $b)) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="form-grid form-grid-3">
                <label class="field">
                    <span><?= e(__('clients.currency')) ?></span>
                    <select name="currency" id="currency">
                        <?php foreach (UserService::CURRENCIES as $cur): ?>
                            <option <?= $currency === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted"><?= e(__('projects.currency_hint')) ?></small>
                </label>
                <label class="field" data-billing="hourly">
                    <span><?= e(__('projects.hourly_rate')) ?></span>
                    <div class="input-group">
                        <input name="hourly_rate" inputmode="decimal" value="<?= e(money_input($v('hourly_rate'))) ?>">
                        <span class="input-addon" data-currency><?= e($currency) ?></span>
                    </div>
                    <small class="muted"><?= e(__('projects.hourly_rate_hint')) ?></small>
                    <?= error_for('hourly_rate') ?>
                </label>
                <label class="field" data-billing="fixed">
                    <span><?= e(__('projects.fixed_fee')) ?> *</span>
                    <div class="input-group">
                        <input name="fixed_fee" inputmode="decimal" value="<?= e(money_input($v('fixed_fee'))) ?>">
                        <span class="input-addon" data-currency><?= e($currency) ?></span>
                    </div>
                    <?= error_for('fixed_fee') ?>
                </label>
                <label class="field" data-billing="retainer">
                    <span><?= e(__('projects.retainer_amount')) ?> *</span>
                    <div class="input-group">
                        <input name="retainer_amount" inputmode="decimal" value="<?= e(money_input($v('retainer_amount'))) ?>">
                        <span class="input-addon" data-currency><?= e($currency) ?></span>
                    </div>
                    <?= error_for('retainer_amount') ?>
                </label>
                <label class="field">
                    <span><?= e(__('projects.budget_hours')) ?></span>
                    <input name="budget_hours" inputmode="decimal" value="<?= e($v('budget_hours')) ?>">
                    <?= error_for('budget_hours') ?>
                </label>
            </div>
        <?php else: ?>
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('projects.budget_hours')) ?></span>
                    <input name="budget_hours" inputmode="decimal" value="<?= e($v('budget_hours')) ?>">
                    <?= error_for('budget_hours') ?>
                </label>
            </div>
        <?php endif; ?>
    </section>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('projects.team')) ?></h3>
        <p class="muted small"><?= e(__('projects.team_hint')) ?></p>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th><?= e(__('users.name')) ?></th>
                    <?php if (can('rates.view')): ?><th style="width:220px"><?= e(__('projects.member_rate')) ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><input type="checkbox" name="members[]" value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $memberIds, true) ? 'checked' : '' ?> class="check-input"></td>
                    <td><strong><?= e($u['name']) ?></strong><?= $u['title'] ? ' <span class="muted">· ' . e($u['title']) . '</span>' : '' ?></td>
                    <?php if (can('rates.view')): ?>
                        <td>
                            <div class="input-group">
                                <input name="member_rates[<?= $u['id'] ?>]" inputmode="decimal" value="<?= e(money_input($memberRates[$u['id']] ?? '')) ?>">
                                <span class="input-addon" data-currency><?= e($currency) ?></span>
                            </div>
                            <?= error_for('member_rates.' . $u['id']) ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="form-actions">
            <a href="<?= $back ?>" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
            <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
        </div>
    </section>
</form>

<script>
(() => {
    const form = document.getElementById('project-form');
    const type = document.getElementById('ptype');
    const syncType = () => form.querySelectorAll('[data-litigation]').forEach(el => el.hidden = type.value !== 'litigation');
    type.addEventListener('change', syncType);
    syncType();

    const syncBilling = () => {
        const checked = form.querySelector('input[name=billing_type]:checked');
        if (!checked) return;
        form.querySelectorAll('[data-billing]').forEach(el => el.hidden = el.dataset.billing !== checked.value);
    };
    form.querySelectorAll('input[name=billing_type]').forEach(r => r.addEventListener('change', syncBilling));
    syncBilling();

    const currency = document.getElementById('currency');
    const setCurrency = val => {
        if (currency) currency.value = val;
        form.querySelectorAll('[data-currency]').forEach(el => el.textContent = val);
    };
    currency?.addEventListener('change', e => setCurrency(e.target.value));
    // Müvekkil seçilince para birimi müvekkilinkine ayarlanır
    document.getElementById('client')?.addEventListener('change', e => {
        const cur = e.target.selectedOptions[0]?.dataset.currency;
        if (cur) setCurrency(cur);
    });
})();
</script>
