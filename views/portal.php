<?php
use App\Core\Lang;
use App\Core\PortalAuth;
use App\Core\View;
use App\Services\Settings;

$orgName = Settings::get('org.name', config('app.name'));
$pu = PortalAuth::user();
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(($title ?? '') . ' · ' . $orgName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <?= View::partial('_theme') ?>
    <?= View::partial('_pwa_head', ['portalApp' => true]) ?>
</head>
<body class="<?= $pu ? '' : 'auth-body' ?>">
<?php if ($pu): ?>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="/portal" class="brand">
                <?php if (Settings::get('org.logo')): ?>
                    <img src="/logo" alt="<?= e($orgName) ?>" class="brand-logo">
                <?php else: ?>
                    <span class="brand-mark"><?= e(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></span><?= e($orgName) ?>
                <?php endif; ?>
            </a>
            <span class="portal-tag"><?= e(__('portal.title')) ?></span>
            <div class="topbar-actions" style="margin-left:auto">
                <form method="post" action="/locale" class="lang-switch">
                    <?= csrf_field() ?>
                    <?php foreach (config('app.locales') as $loc): ?>
                        <button name="locale" value="<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></button>
                    <?php endforeach; ?>
                </form>
                <div class="portal-user cell-main align-right">
                    <strong><?= e($pu['name']) ?></strong>
                    <span><?= e($pu['client_name']) ?></span>
                </div>
                <form method="post" action="/portal/logout">
                    <?= csrf_field() ?>
                    <button class="icon-btn" title="<?= e(__('app.logout')) ?>"><?= icon('logout') ?></button>
                </form>
            </div>
        </div>
    </header>
    <main class="page page-narrow"><?= $content ?></main>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>

<div class="toasts">
    <?php if (has_errors()): ?><?= View::partial('_toast', ['type' => 'error', 'message' => __('validation.check_form')]) ?><?php endif; ?>
    <?php foreach (['success', 'error'] as $type): ?>
        <?php if ($msg = flash($type)): ?><?= View::partial('_toast', ['type' => $type, 'message' => $msg]) ?><?php endif; ?>
    <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.toast').forEach(t => {
    const close = () => { t.classList.add('toast-out'); setTimeout(() => t.remove(), 250); };
    t.querySelector('.toast-close').addEventListener('click', close);
    let timer = setTimeout(close, 4000);
    t.addEventListener('mouseenter', () => { clearTimeout(timer); t.classList.add('toast-paused'); });
    t.addEventListener('mouseleave', () => { timer = setTimeout(close, 2000); t.classList.remove('toast-paused'); });
});
</script>
<script>window.FILEPICK = <?= json_encode(['choose' => __('file.choose'), 'drop' => __('file.drop'), 'remove' => __('file.remove')], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('filepick.js') ?>"></script>
</body>
</html>
