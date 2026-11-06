<?php use App\Services\Microsoft; use App\Services\Settings; ?>
<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('ms.title')) ?></h1>
                <p class="muted"><?= e(__('ms.subtitle')) ?></p>
            </div>
            <?php if (Microsoft::isConfigured()): ?>
                <span class="badge badge-green"><?= e(__('ms.configured')) ?></span>
            <?php else: ?>
                <span class="badge badge-gray"><?= e(__('ms.not_configured')) ?></span>
            <?php endif; ?>
        </div>

        <form method="post" action="/settings/microsoft" class="card form-card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="field field-wide">
                    <span><?= e(__('ms.redirect_uri')) ?></span>
                    <input value="<?= e(Microsoft::redirectUri()) ?>" readonly onclick="this.select()">
                    <small class="muted"><?= e(__('ms.redirect_uri_hint')) ?></small>
                </label>
                <label class="field">
                    <span><?= e(__('ms.tenant_id')) ?> *</span>
                    <input name="tenant_id" value="<?= e(old('tenant_id', (string) Settings::get('ms.tenant_id'))) ?>" placeholder="00000000-0000-0000-0000-000000000000" autocomplete="off">
                    <?= error_for('tenant_id') ?>
                </label>
                <label class="field">
                    <span><?= e(__('ms.client_id')) ?> *</span>
                    <input name="client_id" value="<?= e(old('client_id', (string) Settings::get('ms.client_id'))) ?>" placeholder="00000000-0000-0000-0000-000000000000" autocomplete="off">
                    <?= error_for('client_id') ?>
                </label>
                <label class="field">
                    <span><?= e(__('ms.client_secret')) ?> *</span>
                    <input type="password" name="client_secret" autocomplete="new-password"
                           placeholder="<?= e(Settings::get('ms.client_secret') ? __('ms.secret_saved') : '') ?>">
                    <small class="muted"><?= e(__('ms.secret_hint')) ?></small>
                    <?= error_for('client_secret') ?>
                </label>
            </div>
            <label class="check">
                <input type="checkbox" name="login_enabled" value="1" <?= Settings::get('ms.login_enabled') === '1' ? 'checked' : '' ?>>
                <span><?= e(__('ms.login_enabled')) ?> <span class="muted small">— <?= e(__('ms.login_enabled_hint')) ?></span></span>
            </label>
            <div class="form-actions">
                <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
            </div>
        </form>

        <?php if (Microsoft::isConfigured()): ?>
            <div class="card form-card">
                <p class="muted small"><?= e(__('ms.linked_users', ['count' => $linked])) ?></p>
                <div class="form-actions">
                    <form method="post" action="/settings/microsoft/test">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline"><?= e(__('ms.test')) ?></button>
                    </form>
                    <form method="post" action="/settings/microsoft/reset" onsubmit="return confirm(<?= e(json_encode(__('ms.reset_confirm'))) ?>)">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger"><?= e(__('ms.reset')) ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
