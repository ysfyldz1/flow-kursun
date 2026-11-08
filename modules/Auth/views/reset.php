<?php use App\Core\View; ?>
<div class="auth-split">
    <?= View::partial('_auth_aside') ?>
    <main class="auth-main">
        <div class="auth-card">
            <?= View::partial('Auth::_brand') ?>
            <?php if (!$user): ?>
                <h1><?= e(__('auth.reset_title')) ?></h1>
                <p class="muted auth-lead"><?= e(__('auth.reset_invalid')) ?></p>
                <a href="/login" class="btn btn-outline btn-block"><?= e(__('auth.back_to_login')) ?></a>
            <?php else: ?>
                <h1><?= e(__('auth.reset_title')) ?></h1>
                <form method="post" action="/reset-password/<?= e($token) ?>" class="form">
                    <?= csrf_field() ?>
                    <label class="field">
                        <span><?= e(__('auth.email')) ?></span>
                        <input value="<?= e($user['email']) ?>" disabled>
                    </label>
                    <label class="field">
                        <span><?= e(__('auth.new_password')) ?></span>
                        <input type="password" name="password" required minlength="8" autofocus autocomplete="new-password" placeholder="<?= e(__('validation.password_min')) ?>">
                        <?= error_for('password') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('portal.password_confirm')) ?></span>
                        <input type="password" name="password_confirm" required autocomplete="new-password">
                        <?= error_for('password_confirm') ?>
                    </label>
                    <?= error_for('token') ?>
                    <button class="btn btn-primary btn-block"><?= e(__('auth.reset_submit')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>
