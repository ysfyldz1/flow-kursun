<?php use App\Services\Mailer; use App\Services\Settings;
$driver = old('driver', Mailer::driver());
?>
<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('mail.title')) ?></h1>
                <p class="muted"><?= e(__('mail.subtitle')) ?></p>
            </div>
        </div>

        <form method="post" action="/settings/mail" class="card form-card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('mail.driver')) ?></span>
                    <select name="driver" id="mail-driver">
                        <?php foreach (Mailer::DRIVERS as $d): ?>
                            <option value="<?= $d ?>" <?= $driver === $d ? 'selected' : '' ?>><?= e(__('mail.driver_' . $d)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_for('driver') ?>
                </label>
                <label class="field" data-show="graph smtp">
                    <span><?= e(__('mail.from_address')) ?> *</span>
                    <input type="email" name="from_address" value="<?= e(old('from_address', (string) Settings::get('mail.from_address'))) ?>" placeholder="bildirim@kurum.com.tr">
                    <small class="muted" data-show="graph"><?= e(__('mail.from_hint_graph')) ?></small>
                    <?= error_for('from_address') ?>
                </label>
            </div>

            <div class="form-grid" data-show="smtp">
                <label class="field">
                    <span><?= e(__('mail.smtp_host')) ?> *</span>
                    <input name="smtp_host" value="<?= e(old('smtp_host', (string) Settings::get('mail.smtp_host'))) ?>" placeholder="smtp.office365.com">
                    <?= error_for('smtp_host') ?>
                </label>
                <label class="field">
                    <span><?= e(__('mail.smtp_port')) ?></span>
                    <input name="smtp_port" inputmode="numeric" value="<?= e(old('smtp_port', (string) Settings::get('mail.smtp_port', 587))) ?>">
                    <?= error_for('smtp_port') ?>
                </label>
                <label class="field">
                    <span><?= e(__('mail.smtp_secure')) ?></span>
                    <select name="smtp_secure">
                        <?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => __('mail.secure_none')] as $v => $label): ?>
                            <option value="<?= $v ?>" <?= Settings::get('mail.smtp_secure', 'tls') === $v ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span><?= e(__('mail.smtp_user')) ?></span>
                    <input name="smtp_user" value="<?= e(old('smtp_user', (string) Settings::get('mail.smtp_user'))) ?>" autocomplete="off">
                </label>
                <label class="field">
                    <span><?= e(__('mail.smtp_pass')) ?></span>
                    <input type="password" name="smtp_pass" autocomplete="new-password"
                           placeholder="<?= e(Settings::get('mail.smtp_pass') ? __('ms.secret_saved') : '') ?>">
                </label>
            </div>
            <p class="muted small" data-show="log"><?= e(__('mail.log_hint')) ?></p>

            <div class="form-actions">
                <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
            </div>
        </form>

        <div class="card form-card">
            <div class="page-head">
                <h3 class="section-title"><?= e(__('mail.recent')) ?></h3>
                <form method="post" action="/settings/mail/test">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline"><?= e(__('mail.test')) ?></button>
                </form>
            </div>
            <?php if (!$log): ?>
                <p class="muted small"><?= e(__('mail.recent_empty')) ?></p>
            <?php else: ?>
                <table class="table">
                    <tbody>
                    <?php foreach ($log as $row): ?>
                        <tr>
                            <td class="muted small"><?= e(format_date($row['created_at'], true)) ?></td>
                            <td><?= e($row['recipient']) ?></td>
                            <td><?= e($row['subject']) ?></td>
                            <td class="small"><?= e(__('mail.driver_' . $row['driver'])) ?></td>
                            <td>
                                <?php if ($row['status'] === 'sent'): ?>
                                    <span class="badge badge-green"><?= e(__('mail.sent')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-red" title="<?= e($row['error']) ?>"><?= e(__('mail.failed')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(() => {
    const sel = document.getElementById('mail-driver');
    const sync = () => document.querySelectorAll('[data-show]').forEach(el => {
        el.hidden = !el.dataset.show.split(' ').includes(sel.value);
    });
    sel.addEventListener('change', sync);
    sync();
})();
</script>
