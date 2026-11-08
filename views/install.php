<?php
use App\Core\Lang;

$hidden = '<input type="hidden" name="_csrf" value="' . e($csrf) . '">';
$val = fn (string $k, string $d = '') => e((string) ($_POST[$k] ?? $d));
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(__('install.title')) ?></title>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body class="gk-body">
<div class="gk-wrap">
    <div class="gk-top">
        <strong>Flow · <?= e(__('install.title')) ?></strong>
        <?php if ($step !== 'done'): ?>
            <div class="lang-switch">
                <?php foreach (['tr', 'en'] as $loc): ?>
                    <a href="?lang=<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?><div class="tf-error"><?= e($error) ?></div><?php endif; ?>

    <?php if ($step === 'code'): ?>
        <form method="post" class="card form-card">
            <?= $hidden ?><input type="hidden" name="action" value="code">
            <h3 class="card-title"><?= e(__('install.code_title')) ?></h3>
            <p class="muted"><?= __('install.code_hint') ?></p>
            <label class="field"><span><?= e(__('install.code')) ?></span><input name="code" required autofocus autocomplete="off"></label>
            <button class="btn btn-primary btn-block"><?= e(__('install.continue')) ?></button>
        </form>

    <?php elseif ($step === 'db'): ?>
        <section class="card form-card">
            <h3 class="card-title"><?= e(__('install.checks')) ?></h3>
            <dl class="tf-facts">
                <?php foreach ($checks as $label => $ok): ?>
                    <div><dt><?= e($label) ?></dt><dd><span class="badge <?= $ok ? 'badge-green' : 'badge-red' ?>"><?= e(__($ok ? 'install.ok' : 'install.missing')) ?></span></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php if ($checksOk): ?>
            <form method="post" class="card form-card" style="margin-top:16px">
                <?= $hidden ?><input type="hidden" name="action" value="db">
                <h3 class="card-title"><?= e(__('install.db_title')) ?></h3>
                <p class="muted small"><?= e(__('install.db_hint')) ?></p>
                <label class="field"><span><?= e(__('install.db_host')) ?></span><input name="db_host" value="<?= $val('db_host', 'localhost') ?>" required></label>
                <label class="field"><span><?= e(__('install.db_name')) ?></span><input name="db_name" value="<?= $val('db_name') ?>" required></label>
                <label class="field"><span><?= e(__('install.db_user')) ?></span><input name="db_user" value="<?= $val('db_user') ?>" required autocomplete="off"></label>
                <label class="field"><span><?= e(__('install.db_pass')) ?></span><input type="password" name="db_pass" autocomplete="new-password"></label>
                <button class="btn btn-primary btn-block"><?= e(__('install.continue')) ?></button>
            </form>
        <?php endif; ?>

    <?php elseif ($step === 'setup'): ?>
        <form method="post" class="card form-card">
            <?= $hidden ?><input type="hidden" name="action" value="setup">
            <h3 class="card-title"><?= e(__('install.org_title')) ?></h3>
            <label class="field"><span><?= e(__('org.name')) ?> *</span><input name="org_name" value="<?= $val('org_name') ?>" required></label>
            <label class="field"><span><?= e(__('org.locale')) ?></span>
                <select name="org_locale">
                    <?php foreach (['tr', 'en'] as $loc): ?><option value="<?= $loc ?>" <?= ($_POST['org_locale'] ?? Lang::locale()) === $loc ? 'selected' : '' ?>><?= e(__('lang.' . $loc)) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span><?= e(__('org.color')) ?></span><input type="color" name="org_color" value="<?= $val('org_color', '#1e3a8a') ?>" class="color-input"></label>
            <h3 class="card-title" style="margin-top:20px"><?= e(__('install.admin_title')) ?></h3>
            <label class="field"><span><?= e(__('install.admin_name')) ?> *</span><input name="admin_name" value="<?= $val('admin_name') ?>" required></label>
            <label class="field"><span><?= e(__('install.admin_email')) ?> *</span><input type="email" name="admin_email" value="<?= $val('admin_email') ?>" required></label>
            <label class="field"><span><?= e(__('install.admin_password')) ?> *</span><input type="password" name="admin_password" minlength="8" required autocomplete="new-password"></label>
            <button class="btn btn-primary btn-block"><?= e(__('install.finish')) ?></button>
        </form>

    <?php else: ?>
        <section class="card form-card">
            <span class="gk-check"><?= icon('check') ?></span>
            <h3 class="card-title"><?= e(__('install.done')) ?></h3>
            <p class="muted"><?= e(__('install.done_hint')) ?></p>
            <ol class="guide-list">
                <li><?= e(__('install.todo_cron')) ?><br><code><?= e($done['cron']) ?></code></li>
                <li><?= e(__('install.todo_org')) ?></li>
                <li><?= e(__('install.todo_users')) ?></li>
                <li><?= e(__('install.todo_m365')) ?></li>
            </ol>
            <a href="/login" class="btn btn-primary btn-block"><?= e(__('install.go_login')) ?></a>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
