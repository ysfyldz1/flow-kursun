<?php
$locked = $role && $role['slug'] === 'admin';
$checked = $GLOBALS['__old']['permissions'] ?? ($role['permissions'] ?? []);
?>
<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <a href="/settings/roles" class="back-link">← <?= e(__('roles.title')) ?></a>
                <h1><?= e($role ? localized($role) : __('roles.new')) ?></h1>
            </div>
        </div>

        <?php if ($locked): ?>
            <div class="alert alert-info"><?= e(__('roles.admin_locked')) ?></div>
        <?php endif; ?>
        <?= error_for('permissions') ?>

        <form method="post" action="<?= $role ? '/settings/roles/' . $role['id'] : '/settings/roles' ?>" class="card form-card">
            <?= csrf_field() ?>
            <fieldset <?= $locked ? 'disabled' : '' ?>>
                <div class="form-grid">
                    <label class="field">
                        <span><?= e(__('roles.name_tr')) ?> *</span>
                        <input name="name_tr" value="<?= e(old('name_tr', $role['name_tr'] ?? '')) ?>" required>
                        <?= error_for('name_tr') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('roles.name_en')) ?></span>
                        <input name="name_en" value="<?= e(old('name_en', $role['name_en'] ?? '')) ?>">
                    </label>
                </div>

                <h3 class="section-title"><?= e(__('roles.permissions')) ?></h3>
                <div class="perm-grid">
                    <?php foreach ($permissions as $group => $perms): ?>
                        <div class="perm-group">
                            <strong><?= e(__('perm.group.' . $group)) ?></strong>
                            <?php foreach ($perms as $p): ?>
                                <label class="check">
                                    <input type="checkbox" name="permissions[]" value="<?= e($p) ?>"
                                        <?= $locked || in_array($p, $checked, true) ? 'checked' : '' ?>>
                                    <span><?= e(__('perm.' . $p)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php if (!$locked): ?>
                <div class="form-actions">
                    <a href="/settings/roles" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
                    <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
