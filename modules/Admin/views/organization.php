<?php use App\Services\Settings; ?>
<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('org.title')) ?></h1>
                <p class="muted"><?= e(__('org.subtitle')) ?></p>
            </div>
        </div>

        <form method="post" action="/settings/organization" enctype="multipart/form-data" class="card form-card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('org.name')) ?> *</span>
                    <input name="org_name" value="<?= e(old('org_name', (string) Settings::get('org.name'))) ?>" required>
                    <?= error_for('org_name') ?>
                </label>
                <label class="field">
                    <span><?= e(__('org.locale')) ?></span>
                    <select name="org_locale">
                        <?php foreach (config('app.locales') as $loc): ?>
                            <option value="<?= $loc ?>" <?= Settings::get('org.locale') === $loc ? 'selected' : '' ?>><?= e(__('lang.' . $loc)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span><?= e(__('org.color')) ?></span>
                    <input type="color" name="org_primary_color" value="<?= e(old('org_primary_color', (string) Settings::get('org.primary_color', '#1e3a8a'))) ?>" class="color-input">
                    <?= error_for('org_primary_color') ?>
                </label>
                <div class="field">
                    <span><?= e(__('org.logo')) ?></span>
                    <?php if (Settings::get('org.logo')): ?>
                        <div class="logo-preview">
                            <img src="/logo?v=<?= time() ?>" alt="">
                            <label class="check"><input type="checkbox" name="remove_logo" value="1"><span><?= e(__('org.logo_remove')) ?></span></label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                    <small class="muted"><?= e(__('org.logo_hint')) ?></small>
                    <?= error_for('logo') ?>
                </div>
                <label class="field">
                    <span><?= e(__('org.address')) ?></span>
                    <textarea name="org_address" rows="3"><?= e(old('org_address', (string) Settings::get('org.address'))) ?></textarea>
                    <small class="muted"><?= e(__('org.address_hint')) ?></small>
                </label>
                <label class="field">
                    <span><?= e(__('org.tax_info')) ?></span>
                    <input name="org_tax_info" value="<?= e(old('org_tax_info', (string) Settings::get('org.tax_info'))) ?>" placeholder="<?= e(__('org.tax_info_hint')) ?>">
                </label>
            </div>

            <h3 class="section-title"><?= e(__('org.billing_section')) ?></h3>
            <div class="form-grid form-grid-3">
                <label class="field">
                    <span><?= e(__('org.vat_rate')) ?></span>
                    <input name="vat_rate" inputmode="decimal" value="<?= e(old('vat_rate', (string) Settings::get('billing.vat_rate', 20))) ?>">
                    <?= error_for('vat_rate') ?>
                </label>
                <label class="field">
                    <span><?= e(__('org.withholding_rate')) ?></span>
                    <input name="withholding_rate" inputmode="decimal" value="<?= e(old('withholding_rate', (string) Settings::get('billing.withholding_rate', 20))) ?>">
                    <small class="muted"><?= e(__('org.withholding_hint')) ?></small>
                    <?= error_for('withholding_rate') ?>
                </label>
                <label class="field">
                    <span><?= e(__('org.due_days')) ?></span>
                    <input name="due_days" inputmode="numeric" value="<?= e(old('due_days', (string) Settings::get('billing.due_days', 15))) ?>">
                    <?= error_for('due_days') ?>
                </label>
                <label class="field field-wide">
                    <span><?= e(__('billing.bank_accounts')) ?></span>
                    <textarea name="bank_accounts" rows="4" placeholder="<?= e(__('org.bank_hint')) ?>"><?= e(old('bank_accounts', (string) Settings::get('billing.bank_accounts'))) ?></textarea>
                    <small class="muted"><?= e(__('org.bank_note')) ?></small>
                </label>
            </div>

            <h3 class="section-title"><?= e(__('portal.title')) ?></h3>
            <label class="check">
                <input type="checkbox" name="portal_show_hours" value="1" <?= Settings::get('portal.show_hours', '1') === '1' ? 'checked' : '' ?>>
                <span><?= e(__('org.portal_show_hours')) ?> <span class="muted small">— <?= e(__('org.portal_show_hours_hint')) ?></span></span>
            </label>
            <div class="form-actions">
                <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
            </div>
        </form>
    </div>
</div>
