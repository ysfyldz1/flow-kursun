<?php
use App\Core\Lang;
use App\Core\View;
use App\Services\Settings;

$orgName = Settings::get('org.name', config('app.name'));
$canAdmin = can('users.manage') || can('roles.manage') || can('settings.manage');

$user = auth();
$initials = implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(explode(' ', $user['name']), 0, 2)));
$nav = [
    ['/',          'home',    'nav.dashboard', null],
    ['/clients',   'users',   'nav.clients',   'clients.view'],
    ['/projects',  'folder',  'nav.projects',  'projects.view'],
    ['/time',      'clock',   'nav.time',      null],
    ['/billing',   'receipt', 'nav.billing',   'statements.manage'],
    ['/reports',   'chart',   'nav.reports',   'reports.view'],
];
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <title><?= e(($title ?? '') . ' · ' . $orgName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <?= View::partial('_theme') ?>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a href="/" class="brand">
            <?php if (Settings::get('org.logo')): ?>
                <img src="/logo" alt="<?= e($orgName) ?>" class="brand-logo">
            <?php else: ?>
                <span class="brand-mark"><?= e(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></span><?= e($orgName) ?>
            <?php endif; ?>
        </a>

        <nav class="mainnav">
            <?php foreach ($nav as [$href, $ico, $label, $perm]): ?>
                <?php if ($perm && !can($perm)) continue; ?>
                <a href="<?= $href ?>" class="<?= request_is($href) ? 'active' : '' ?>">
                    <?= icon($ico) ?><span><?= e(__($label)) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="topbar-actions">
            <button class="icon-btn" title="<?= e(__('app.search')) ?>"><?= icon('search') ?></button>
            <button class="icon-btn" title="<?= e(__('app.notifications')) ?>"><?= icon('bell') ?></button>

            <details class="dropdown">
                <summary class="avatar-btn">
                    <span class="avatar"><?= e(mb_strtoupper($initials)) ?></span>
                    <?= icon('chevron-down', 'icon icon-sm') ?>
                </summary>
                <div class="dropdown-menu">
                    <div class="dropdown-head">
                        <strong><?= e($user['name']) ?></strong>
                        <span><?= e($user['email']) ?></span>
                    </div>
                    <form method="post" action="/locale" class="lang-switch">
                        <?= csrf_field() ?>
                        <?= icon('globe') ?>
                        <?php foreach (config('app.locales') as $loc): ?>
                            <button name="locale" value="<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></button>
                        <?php endforeach; ?>
                    </form>
                    <?php if ($canAdmin): ?>
                        <a href="/settings"><?= icon('settings') ?><?= e(__('nav.settings')) ?></a>
                    <?php endif; ?>
                    <form method="post" action="/logout">
                        <?= csrf_field() ?>
                        <button type="submit"><?= icon('logout') ?><?= e(__('app.logout')) ?></button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>

<main class="page">
    <?= $content ?>
</main>

<div class="toasts" id="toasts">
    <?php if (has_errors()): ?>
        <?= View::partial('_toast', ['type' => 'error', 'message' => __('validation.check_form')]) ?>
    <?php endif; ?>
    <?php foreach (['success', 'error'] as $type): ?>
        <?php if ($msg = flash($type)): ?><?= View::partial('_toast', ['type' => $type, 'message' => $msg]) ?><?php endif; ?>
    <?php endforeach; ?>
</div>

<script>
// Bildirimler 4 sn sonra kendiliğinden kapanır; üzerine gelince bekler
document.querySelectorAll('.toast').forEach(t => {
    const close = () => { t.classList.add('toast-out'); setTimeout(() => t.remove(), 250); };
    t.querySelector('.toast-close').addEventListener('click', close);
    let timer = setTimeout(close, 4000);
    t.addEventListener('mouseenter', () => { clearTimeout(timer); t.classList.add('toast-paused'); });
    t.addEventListener('mouseleave', () => { timer = setTimeout(close, 2000); t.classList.remove('toast-paused'); });
});

// Açık açılır menüyü dışarı tıklanınca kapat
document.addEventListener('click', e => {
    document.querySelectorAll('details.dropdown[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
</script>
</body>
</html>
