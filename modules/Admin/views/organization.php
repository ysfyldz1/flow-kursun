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
            </div>
            <div class="form-actions">
                <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
            </div>
        </form>
    </div>
</div>
