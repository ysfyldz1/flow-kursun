<?php
// Profil: kişisel bilgiler, şifre ve kişinin kendi Microsoft 365 bağlantısı (posta kutusu + OneDrive, kullanıcı adına).
$scopeLabels = ['Mail.Read' => 'ms.scope_mail', 'Files.ReadWrite' => 'ms.scope_files'];
?>
<div class="page-head">
    <div>
        <h1><?= e(__('app.profile')) ?></h1>
        <p class="muted"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
    </div>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <?php if ($connection !== false): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('globe') ?></span>
                    <h2><?= e(__('ms.my_connection')) ?></h2>
                    <?php if ($connection): ?><span class="badge badge-green head-right"><?= e(__('ms.connected_badge')) ?></span><?php endif; ?>
                </header>
                <div class="card-body stack">
                    <?php if ($connection): ?>
                        <p><?= e(__('ms.connected_as', ['email' => $connection['email'] ?? '', 'date' => format_date($connection['connected_at'], true)])) ?></p>
                        <ul class="ms-scopes">
                            <?php foreach ($scopeLabels as $scope => $label): ?>
                                <li class="<?= in_array($scope, $connection['scopes'], true) ? '' : 'muted' ?>">
                                    <?= in_array($scope, $connection['scopes'], true) ? '✓' : '—' ?> <?= e(__($label)) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="muted small"><?= e(__('ms.privacy')) ?></p>
                        <div class="form-actions form-actions-tight">
                            <a href="/account/microsoft/connect" class="btn btn-outline"><?= e(__('ms.reconnect')) ?></a>
                            <form method="post" action="/account/microsoft/disconnect" onsubmit="return confirm('<?= e(__('ms.confirm_disconnect')) ?>')">
                                <?= csrf_field() ?><button class="btn btn-outline btn-danger"><?= e(__('ms.disconnect')) ?></button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p><?= e(__('ms.connect_intro')) ?></p>
                        <ul class="ms-scopes">
                            <?php foreach ($scopeLabels as $label): ?><li>• <?= e(__($label)) ?></li><?php endforeach; ?>
                        </ul>
                        <p class="muted small"><?= e(__('ms.privacy')) ?></p>
                        <div class="form-actions form-actions-tight">
                            <a href="/account/microsoft/connect" class="btn btn-primary"><?= e(__('ms.connect')) ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($user['password']): ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('auth.change_password')) ?></h2></header>
                <form method="post" action="/account/password" class="card-body form">
                    <?= csrf_field() ?>
                    <label class="field">
                        <span><?= e(__('auth.current_password')) ?></span>
                        <input type="password" name="current_password" required autocomplete="current-password">
                        <?= error_for('current_password') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('auth.new_password')) ?></span>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="<?= e(__('validation.password_min')) ?>">
                        <?= error_for('password') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('portal.password_confirm')) ?></span>
                        <input type="password" name="password_confirm" required autocomplete="new-password">
                        <?= error_for('password_confirm') ?>
                    </label>
                    <p class="muted small"><?= e(__('auth.change_password_note')) ?></p>
                    <div class="form-actions form-actions-tight">
                        <button class="btn btn-primary"><?= e(__('auth.change_password')) ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <section class="card widget widget-auto">
        <header class="widget-head"><h2><?= e(__('clients.section_general')) ?></h2></header>
        <dl class="dl">
            <div class="dl-row"><dt><?= e(__('users.name')) ?></dt><dd><?= e($user['name']) ?></dd></div>
            <div class="dl-row"><dt><?= e(__('auth.email')) ?></dt><dd><?= e($user['email']) ?></dd></div>
            <?php if ($user['title']): ?><div class="dl-row"><dt><?= e(__('users.job_title')) ?></dt><dd><?= e($user['title']) ?></dd></div><?php endif; ?>
            <div class="dl-row"><dt><?= e(__('app.language')) ?></dt><dd><?= e(__('lang.' . $user['locale'])) ?></dd></div>
        </dl>
    </section>
</div>
