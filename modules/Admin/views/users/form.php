<?php
use App\Services\UserService;

$v = fn (string $k, $default = '') => old($k, (string) ($user[$k] ?? $default));
?>
<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <a href="/settings/users" class="back-link">← <?= e(__('users.title')) ?></a>
                <h1><?= e($user ? $user['name'] : __('users.new')) ?></h1>
            </div>
        </div>

        <form method="post" action="<?= $user ? '/settings/users/' . $user['id'] : '/settings/users' ?>" class="card form-card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('users.name')) ?> *</span>
                    <input name="name" value="<?= e($v('name')) ?>" required>
                    <?= error_for('name') ?>
                </label>
                <label class="field">
                    <span><?= e(__('auth.email')) ?> *</span>
                    <input type="email" name="email" value="<?= e($v('email')) ?>" required>
                    <?= error_for('email') ?>
                </label>
                <label class="field">
                    <span><?= e(__('users.job_title')) ?></span>
                    <input name="title" value="<?= e($v('title')) ?>" placeholder="<?= e(__('users.job_title_hint')) ?>">
                </label>
                <label class="field">
                    <span><?= e(__('users.role')) ?> *</span>
                    <select name="role_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $v('role_id') == $r['id'] ? 'selected' : '' ?>><?= e(localized($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_for('role_id') ?>
                </label>
                <?php if (can('rates.view')): ?>
                <label class="field">
                    <span><?= e(__('users.hourly_rate')) ?></span>
                    <div class="input-group">
                        <input name="hourly_rate" inputmode="decimal" value="<?= e(money_input($v('hourly_rate'))) ?>">
                        <select name="rate_currency">
                            <option value="">—</option>
                            <?php foreach (UserService::CURRENCIES as $c): ?>
                                <option <?= $v('rate_currency') === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?= error_for('hourly_rate') ?>
                    <?= error_for('rate_currency') ?>
                </label>
                <?php endif; ?>
                <label class="field">
                    <span><?= e(__('app.language')) ?></span>
                    <select name="locale">
                        <?php foreach (config('app.locales') as $loc): ?>
                            <option value="<?= $loc ?>" <?= $v('locale', config('app.locale')) === $loc ? 'selected' : '' ?>><?= e(__('lang.' . $loc)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php $msLogin = \App\Services\Microsoft::loginEnabled(); $pwRequired = !$user && !$msLogin; ?>
                <label class="field">
                    <span><?= e(__('auth.password')) ?><?= $pwRequired ? ' *' : '' ?></span>
                    <input type="password" name="password" autocomplete="new-password" <?= $pwRequired ? 'required' : '' ?>
                           placeholder="<?= e($user ? __('users.password_keep') : __('validation.password_min')) ?>">
                    <?php if (!$user && $msLogin): ?>
                        <small class="muted"><?= e(__('users.password_optional_ms')) ?></small>
                    <?php endif; ?>
                    <?= error_for('password') ?>
                </label>
            </div>
            <h3 class="card-title" style="margin-top:8px"><?= e(__('users.leave_section')) ?></h3>
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('users.hire_date')) ?></span>
                    <input type="date" name="hire_date" value="<?= e($v('hire_date')) ?>">
                    <small class="muted"><?= e(__('users.hire_date_hint')) ?></small>
                    <?= error_for('hire_date') ?>
                </label>
                <label class="field">
                    <span><?= e(__('users.leave_entitlement')) ?></span>
                    <input name="leave_entitlement" inputmode="decimal" value="<?= e(is_numeric($v('leave_entitlement')) ? str_replace('.', ',', (string) (float) $v('leave_entitlement')) : $v('leave_entitlement')) ?>">
                    <small class="muted"><?= e(__('users.leave_entitlement_hint')) ?></small>
                    <?= error_for('leave_entitlement') ?>
                </label>
            </div>
            <div class="field">
                <span><?= e(__('users.leave_approvers')) ?></span>
                <div class="check-grid">
                    <?php foreach ($approverOptions as $a): ?>
                        <?php if ($user && (int) $a['id'] === (int) $user['id']) continue; ?>
                        <label class="check"><input type="checkbox" name="leave_approvers[]" value="<?= $a['id'] ?>" <?= in_array((int) $a['id'], $approvers, true) ? 'checked' : '' ?>><span><?= e($a['name']) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <small class="muted"><?= e(__('users.leave_approvers_hint')) ?></small>
            </div>
            <?php if (!$user && \App\Services\Mailer::delivers()): ?>
                <label class="check">
                    <input type="checkbox" name="send_welcome" value="1" checked>
                    <span><?= e(__('users.send_welcome')) ?> <span class="muted small">— <?= e(__('users.send_welcome_hint')) ?></span></span>
                </label>
            <?php endif; ?>
            <div class="form-actions">
                <a href="/settings/users" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
                <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
            </div>
        </form>
    </div>
</div>
