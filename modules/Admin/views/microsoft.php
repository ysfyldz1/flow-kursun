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

        <?php if (Microsoft::isConfigured()): $provider = old('docs_provider', (string) Settings::get('docs.provider')); ?>
            <form method="post" action="/settings/microsoft/documents" class="card form-card">
                <?= csrf_field() ?>
                <div class="page-head">
                    <div>
                        <h3 class="section-title"><?= e(__('docs.settings_title')) ?></h3>
                        <p class="muted small"><?= e(__('docs.settings_hint')) ?></p>
                    </div>
                    <?php if (\App\Services\DocumentService::enabled()): ?>
                        <span class="badge badge-green"><?= e(__('ms.configured')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-grid">
                    <label class="field">
                        <span><?= e(__('docs.provider')) ?></span>
                        <select name="docs_provider" id="docs-provider">
                            <option value=""><?= e(__('docs.provider_none')) ?></option>
                            <?php foreach (\App\Services\DocumentService::PROVIDERS as $pv): ?>
                                <option value="<?= $pv ?>" <?= $provider === $pv ? 'selected' : '' ?>><?= e(__('docs.provider_' . $pv)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" data-docs="sharepoint onedrive">
                        <span data-docs="sharepoint"><?= e(__('docs.site_url')) ?> *</span>
                        <span data-docs="onedrive"><?= e(__('docs.onedrive_user')) ?> *</span>
                        <input name="docs_target" value="<?= e(old('docs_target', (string) Settings::get('docs.target'))) ?>"
                               data-ph-sharepoint="https://kurum.sharepoint.com/sites/Flow" data-ph-onedrive="belgeler@kurum.com.tr">
                        <?= error_for('docs_target') ?>
                    </label>
                    <label class="field" data-docs="sharepoint onedrive">
                        <span><?= e(__('docs.root')) ?></span>
                        <input name="docs_root" value="<?= e(old('docs_root', (string) Settings::get('docs.root', 'Flow'))) ?>">
                        <small class="muted"><?= e(__('docs.root_hint')) ?></small>
                    </label>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
                </div>
            </form>
            <?php $tfProvider = old('transfer_provider', (string) Settings::get('transfer.provider')); ?>
            <form method="post" action="/settings/microsoft/transfers" class="card form-card">
                <?= csrf_field() ?>
                <div class="page-head">
                    <div>
                        <h3 class="section-title"><?= e(__('transfers.settings_title')) ?></h3>
                        <p class="muted small"><?= e(__('transfers.settings_hint', ['days' => \App\Services\TransferService::RETENTION_DAYS])) ?></p>
                    </div>
                    <?php if (\App\Services\TransferService::enabled()): ?>
                        <span class="badge badge-green"><?= e(__('ms.configured')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-grid">
                    <label class="field">
                        <span><?= e(__('docs.provider')) ?></span>
                        <select name="transfer_provider" id="tf-provider">
                            <option value=""><?= e(__('docs.provider_none')) ?></option>
                            <?php foreach (\App\Services\DocumentService::PROVIDERS as $pv): ?>
                                <option value="<?= $pv ?>" <?= $tfProvider === $pv ? 'selected' : '' ?>><?= e(__('docs.provider_' . $pv)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" data-tf="sharepoint onedrive">
                        <span data-tf="sharepoint"><?= e(__('docs.site_url')) ?> *</span>
                        <span data-tf="onedrive"><?= e(__('docs.onedrive_user')) ?> *</span>
                        <input name="transfer_target" value="<?= e(old('transfer_target', (string) Settings::get('transfer.target'))) ?>">
                        <?= error_for('transfer_target') ?>
                    </label>
                    <label class="field" data-tf="sharepoint onedrive">
                        <span><?= e(__('docs.root')) ?></span>
                        <input name="transfer_root" value="<?= e(old('transfer_root', (string) Settings::get('transfer.root', 'Flow Transfer'))) ?>">
                    </label>
                </div>
                <?php $cronRun = Settings::get('cron.job.transfers'); ?>
                <p class="muted small"><?= e($cronRun ? __('transfers.cron_last', ['date' => format_date($cronRun, true)]) : __('transfers.cron_never')) ?></p>
                <div class="form-actions">
                    <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
                </div>
            </form>
            <script>
            (() => {
                const sel = document.getElementById('tf-provider');
                const sync = () => document.querySelectorAll('[data-tf]').forEach(el => { el.hidden = !el.dataset.tf.split(' ').includes(sel.value); });
                sel.addEventListener('change', sync);
                sync();
            })();
            </script>
            <form method="post" action="/settings/microsoft/calendar" class="card form-card">
                <?= csrf_field() ?>
                <h3 class="section-title"><?= e(__('cal.settings_title')) ?></h3>
                <label class="check">
                    <input type="checkbox" name="calendar_outlook" value="1" <?= Settings::get('calendar.outlook') === '1' ? 'checked' : '' ?>>
                    <span><?= e(__('cal.settings_outlook')) ?> <span class="muted small">— <?= e(__('cal.settings_outlook_hint')) ?></span></span>
                </label>
                <label class="check">
                    <input type="checkbox" name="login_connect" value="1" <?= Settings::get('ms.login_connect') === '1' ? 'checked' : '' ?>>
                    <span><?= e(__('ms.login_connect')) ?> <span class="muted small">— <?= e(__('ms.login_connect_hint')) ?></span></span>
                </label>
                <div class="form-actions">
                    <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
                </div>
            </form>

            <script>
            (() => {
                const sel = document.getElementById('docs-provider');
                const input = document.querySelector('[name=docs_target]');
                const sync = () => {
                    document.querySelectorAll('[data-docs]').forEach(el => { el.hidden = !el.dataset.docs.split(' ').includes(sel.value); });
                    input.placeholder = input.dataset['ph' + sel.value.charAt(0).toUpperCase() + sel.value.slice(1)] || '';
                };
                sel.addEventListener('change', sync);
                sync();
            })();
            </script>

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
