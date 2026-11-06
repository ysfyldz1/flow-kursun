<?php
use App\Core\Lang;

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
    <title><?= e(($title ?? '') . ' · ' . config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a href="/" class="brand"><span class="brand-mark">F</span><?= e(config('app.name')) ?></a>

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
                    <?php if (can('*')): ?>
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
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
    <?= $content ?>
</main>

<script>
// Açık açılır menüyü dışarı tıklanınca kapat
document.addEventListener('click', e => {
    document.querySelectorAll('details.dropdown[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
</script>
</body>
</html>
