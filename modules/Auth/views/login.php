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
    <h1><?= e(__('auth.welcome')) ?></h1>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="form">
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

    <div class="divider"><span><?= e(__('auth.or')) ?></span></div>
    <button type="button" class="btn btn-outline btn-block" disabled title="<?= e(__('app.coming_soon')) ?>">
        <svg class="icon" viewBox="0 0 23 23"><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>
        <?= e(__('auth.microsoft')) ?>
    </button>

    <form method="post" action="/locale" class="lang-switch lang-switch-center">
        <?= csrf_field() ?>
        <?php foreach (config('app.locales') as $loc): ?>
            <button name="locale" value="<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></button>
        <?php endforeach; ?>
    </form>
</div>
