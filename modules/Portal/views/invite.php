<?php
use App\Services\Settings;

$orgName = Settings::get('org.name', config('app.name'));
?>
<div class="auth-card">
    <div class="auth-brand">
        <?php if (Settings::get('org.logo')): ?>
            <img src="/logo" alt="<?= e($orgName) ?>" class="brand-logo">
        <?php else: ?>
            <span class="brand-mark"><?= e(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></span><?= e($orgName) ?>
        <?php endif; ?>
    </div>

    <?php if (!$user): ?>
        <h1><?= e(__('portal.invite_invalid_title')) ?></h1>
        <p class="muted"><?= e(__('portal.invite_invalid')) ?></p>
        <a href="/portal/login" class="btn btn-outline btn-block"><?= e(__('auth.login')) ?></a>
    <?php else: ?>
        <h1><?= e(__('portal.invite_title', ['name' => $user['name']])) ?></h1>
        <p class="muted" style="margin-top:-12px"><?= e(__('portal.invite_text', ['org' => $orgName, 'client' => $user['client_name']])) ?></p>
        <form method="post" action="/portal/invite/<?= e($token) ?>" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span><?= e(__('auth.email')) ?></span>
                <input value="<?= e($user['email']) ?>" disabled>
            </label>
            <label class="field">
                <span><?= e(__('portal.new_password')) ?></span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="<?= e(__('validation.password_min')) ?>">
                <?= error_for('password') ?>
            </label>
            <label class="field">
                <span><?= e(__('portal.password_confirm')) ?></span>
                <input type="password" name="password_confirm" required autocomplete="new-password">
                <?= error_for('password_confirm') ?>
            </label>
            <?= error_for('token') ?>
            <button class="btn btn-primary btn-block"><?= e(__('portal.activate')) ?></button>
        </form>
    <?php endif; ?>
</div>
