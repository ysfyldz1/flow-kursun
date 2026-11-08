<?php use App\Core\View; ?>
<div class="auth-split">
    <?= View::partial('_auth_aside') ?>
    <main class="auth-main">
        <div class="auth-card">
            <?= View::partial('Auth::_brand') ?>
            <h1><?= e(__('auth.forgot_title')) ?></h1>
            <p class="muted auth-lead"><?= e(__('auth.forgot_text')) ?></p>
            <form method="post" action="/forgot-password" class="form">
                <?= csrf_field() ?>
                <label class="field">
                    <span><?= e(__('auth.email')) ?></span>
                    <input type="email" name="email" required autofocus autocomplete="username">
                </label>
                <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.forgot_submit')) ?></button>
                <a href="/login" class="auth-back"><?= e(__('auth.back_to_login')) ?></a>
            </form>
        </div>
    </main>
</div>
