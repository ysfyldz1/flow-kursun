<?php
use App\Core\Lang;
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
    <h1><?= e(__('portal.login_title')) ?></h1>
    <p class="muted" style="margin-top:-12px"><?= e(__('portal.login_hint')) ?></p>

    <form method="post" action="/portal/login" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(__('auth.email')) ?></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username">
        </label>
        <label class="field">
            <span><?= e(__('auth.password')) ?></span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.submit')) ?></button>
    </form>

    <form method="post" action="/locale" class="lang-switch lang-switch-center">
        <?= csrf_field() ?>
        <?php foreach (config('app.locales') as $loc): ?>
            <button name="locale" value="<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></button>
        <?php endforeach; ?>
    </form>
</div>
