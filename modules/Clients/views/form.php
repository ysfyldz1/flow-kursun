<?php
use App\Services\UserService;

$v = fn (string $k, $default = '') => old($k, (string) ($client[$k] ?? $default));
$contacts = has_errors() ? ($GLOBALS['__old']['contacts'] ?? []) : ($client['contacts'] ?? []);
$contacts = array_values($contacts) ?: [['name' => '', 'title' => '', 'email' => '', 'phone' => '', 'is_primary' => 1]];
$rates = has_errors()
    ? ($GLOBALS['__old']['user_rates'] ?? [])
    : array_column($client['user_rates'] ?? [], 'hourly_rate', 'user_id');
$type = $v('type', 'company');
?>
<div class="page-head">
    <div>
        <a href="<?= $client ? '/clients/' . $client['id'] : '/clients' ?>" class="back-link">← <?= e($client ? $client['name'] : __('clients.title')) ?></a>
        <h1><?= e($client ? __('clients.edit') : __('clients.new')) ?></h1>
    </div>
    <?php if ($client): ?><span class="code code-lg"><?= e($client['code']) ?></span><?php endif; ?>
</div>

<form method="post" action="<?= $client ? '/clients/' . $client['id'] : '/clients' ?>" class="form-stack">
    <?= csrf_field() ?>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('clients.section_general')) ?></h3>
        <div class="segmented">
            <label><input type="radio" name="type" value="company" <?= $type === 'company' ? 'checked' : '' ?>><span><?= e(__('clients.type_company')) ?></span></label>
            <label><input type="radio" name="type" value="person" <?= $type === 'person' ? 'checked' : '' ?>><span><?= e(__('clients.type_person')) ?></span></label>
        </div>
        <div class="form-grid">
            <label class="field field-wide">
                <span data-company><?= e(__('clients.company_name')) ?> *</span>
                <span data-person><?= e(__('users.name')) ?> *</span>
                <input name="name" value="<?= e($v('name')) ?>" required>
                <?= error_for('name') ?>
            </label>
            <label class="field">
                <span data-company><?= e(__('clients.vkn')) ?></span>
                <span data-person><?= e(__('clients.tckn')) ?></span>
                <input name="tax_id" value="<?= e($v('tax_id')) ?>" inputmode="numeric" maxlength="11">
                <?= error_for('tax_id') ?>
            </label>
            <label class="field">
                <span><?= e(__('clients.tax_office')) ?></span>
                <input name="tax_office" value="<?= e($v('tax_office')) ?>">
            </label>
            <label class="field">
                <span><?= e(__('auth.email')) ?></span>
                <input type="email" name="email" value="<?= e($v('email')) ?>">
                <?= error_for('email') ?>
            </label>
            <label class="field">
                <span><?= e(__('clients.phone')) ?></span>
                <input name="phone" value="<?= e($v('phone')) ?>">
            </label>
            <label class="field field-wide">
                <span><?= e(__('clients.address')) ?></span>
                <textarea name="address" rows="2"><?= e($v('address')) ?></textarea>
            </label>
            <label class="field">
                <span><?= e(__('clients.city')) ?></span>
                <input name="city" value="<?= e($v('city')) ?>">
            </label>
            <label class="field">
                <span><?= e(__('clients.country')) ?></span>
                <input name="country" value="<?= e($v('country', 'Türkiye')) ?>">
            </label>
        </div>
    </section>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('clients.section_contacts')) ?></h3>
        <div class="repeater" id="contacts">
            <div class="repeater-head">
                <span><?= e(__('users.name')) ?></span><span><?= e(__('users.job_title')) ?></span>
                <span><?= e(__('auth.email')) ?></span><span><?= e(__('clients.phone')) ?></span>
                <span title="<?= e(__('clients.primary')) ?>"><?= e(__('clients.primary_short')) ?></span><span></span>
            </div>
            <?php foreach ($contacts as $i => $c): ?>
                <div class="repeater-row">
                    <input name="contacts[<?= $i ?>][name]" value="<?= e($c['name'] ?? '') ?>" placeholder="<?= e(__('users.name')) ?>">
                    <input name="contacts[<?= $i ?>][title]" value="<?= e($c['title'] ?? '') ?>" placeholder="<?= e(__('users.job_title')) ?>">
                    <div>
                        <input type="email" name="contacts[<?= $i ?>][email]" value="<?= e($c['email'] ?? '') ?>" placeholder="<?= e(__('auth.email')) ?>">
                        <?= error_for("contacts.$i.email") ?>
                    </div>
                    <input name="contacts[<?= $i ?>][phone]" value="<?= e($c['phone'] ?? '') ?>" placeholder="<?= e(__('clients.phone')) ?>">
                    <input type="radio" name="primary_contact" value="<?= $i ?>" <?= !empty($c['is_primary']) ? 'checked' : '' ?> class="primary-radio">
                    <input type="hidden" name="contacts[<?= $i ?>][is_primary]" value="<?= !empty($c['is_primary']) ? 1 : 0 ?>">
                    <button type="button" class="icon-btn icon-btn-sm" data-remove title="<?= e(__('app.delete')) ?>">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline" id="add-contact"><?= icon('plus') ?><?= e(__('clients.add_contact')) ?></button>
    </section>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('clients.section_billing')) ?></h3>
        <div class="form-grid form-grid-3">
            <label class="field">
                <span><?= e(__('clients.currency')) ?></span>
                <select name="currency" id="currency">
                    <?php foreach (UserService::CURRENCIES as $cur): ?>
                        <option <?= $v('currency', 'TRY') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span><?= e(__('clients.locale')) ?></span>
                <select name="locale">
                    <?php foreach (config('app.locales') as $loc): ?>
                        <option value="<?= $loc ?>" <?= $v('locale', 'tr') === $loc ? 'selected' : '' ?>><?= e(__('lang.' . $loc)) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="muted"><?= e(__('clients.locale_hint')) ?></small>
            </label>
            <label class="field">
                <span><?= e(__('clients.hourly_rate')) ?></span>
                <div class="input-group">
                    <input name="hourly_rate" inputmode="decimal" value="<?= e(money_input($v('hourly_rate'))) ?>">
                    <span class="input-addon" data-currency><?= e($v('currency', 'TRY')) ?></span>
                </div>
                <small class="muted"><?= e(__('clients.hourly_rate_hint')) ?></small>
                <?= error_for('hourly_rate') ?>
            </label>
        </div>

        <h4 class="subsection-title"><?= e(__('clients.user_rates')) ?></h4>
        <p class="muted small"><?= e(__('clients.user_rates_hint')) ?></p>
        <table class="table table-compact">
            <thead>
                <tr><th><?= e(__('users.name')) ?></th><th><?= e(__('clients.default_rate')) ?></th><th style="width:220px"><?= e(__('clients.client_rate')) ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong><?= $u['title'] ? ' <span class="muted">· ' . e($u['title']) . '</span>' : '' ?></td>
                    <td class="muted"><?= $u['hourly_rate'] !== null ? e(format_money((float) $u['hourly_rate'], $u['rate_currency'])) : '—' ?></td>
                    <td>
                        <div class="input-group">
                            <input name="user_rates[<?= $u['id'] ?>]" inputmode="decimal" value="<?= e(money_input($rates[$u['id']] ?? '')) ?>">
                            <span class="input-addon" data-currency><?= e($v('currency', 'TRY')) ?></span>
                        </div>
                        <?= error_for('user_rates.' . $u['id']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card form-card">
        <h3 class="card-title"><?= e(__('clients.notes')) ?></h3>
        <label class="field">
            <textarea name="notes" rows="4"><?= e($v('notes')) ?></textarea>
        </label>
        <div class="form-actions">
            <a href="<?= $client ? '/clients/' . $client['id'] : '/clients' ?>" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
            <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
        </div>
    </section>
</form>

<script>
(() => {
    const form = document.currentScript.previousElementSibling;
    const box = document.getElementById('contacts');

    // Tür seçimine göre etiketler (VKN/TCKN, Şirket adı/Ad soyad)
    const syncType = () => {
        const person = form.querySelector('input[name=type]:checked').value === 'person';
        form.querySelectorAll('[data-person]').forEach(el => el.hidden = !person);
        form.querySelectorAll('[data-company]').forEach(el => el.hidden = person);
    };
    form.querySelectorAll('input[name=type]').forEach(r => r.addEventListener('change', syncType));
    syncType();

    // Para birimi etiketleri
    document.getElementById('currency').addEventListener('change', e =>
        form.querySelectorAll('[data-currency]').forEach(el => el.textContent = e.target.value));

    // İletişim kişisi satırları
    let next = box.querySelectorAll('.repeater-row').length;
    document.getElementById('add-contact').addEventListener('click', () => {
        const row = box.querySelector('.repeater-row').cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            if (input.name) input.name = input.name.replace(/contacts\[\d+\]/, `contacts[${next}]`);
            if (input.type === 'radio') { input.value = next; input.checked = false; }
            else if (input.type === 'hidden') input.value = 0;
            else input.value = '';
        });
        row.querySelectorAll('.field-error').forEach(el => el.remove());
        box.appendChild(row);
        next++;
    });
    box.addEventListener('click', e => {
        if (!e.target.closest('[data-remove]')) return;
        const rows = box.querySelectorAll('.repeater-row');
        const row = e.target.closest('.repeater-row');
        if (rows.length > 1) row.remove(); else row.querySelectorAll('input:not([type=radio]):not([type=hidden])').forEach(i => i.value = '');
    });

    // Birincil kişi: radyo seçimi gizli alanlara yazılır
    form.addEventListener('submit', () => {
        box.querySelectorAll('.repeater-row').forEach(row => {
            row.querySelector('input[type=hidden]').value = row.querySelector('.primary-radio').checked ? 1 : 0;
        });
    });
})();
</script>
