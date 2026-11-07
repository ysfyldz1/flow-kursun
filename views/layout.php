<?php
use App\Core\Lang;
use App\Core\View;
use App\Services\Settings;

$orgName = Settings::get('org.name', config('app.name'));
$canAdmin = can('users.manage') || can('roles.manage') || can('settings.manage');

$user = auth();
$initials = implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(explode(' ', $user['name']), 0, 2)));
// Menü: tek öğe [href, ikon, etiket, yetki] ya da grup ['group' => etiket, 'icon' => ikon, 'items' => [...]].
// Yeni modül uygun gruba eklenir; üst menü uzamaz.
$nav = [
    ['/',          'home',    'nav.dashboard', null],
    ['/clients',   'users',   'nav.clients',   'clients.view'],
    ['/projects',  'folder',  'nav.projects',  'projects.view'],
    ['/calendar',  'calendar','nav.calendar',  'calendar.view'],
    ['/tasks',     'check',   'nav.tasks',     ['tasks.own', 'tasks.all']],
    ['/time',      'clock',   'nav.time',      ['time.own', 'time.all']],
    ['group' => 'nav.finance', 'icon' => 'wallet', 'items' => [
        ['/billing',   'receipt', 'nav.billing',   ['statements.manage', 'payments.manage']],
        ['/expenses',  'wallet',  'nav.expenses',  ['expenses.own', 'expenses.all']],
        ['/reports',   'chart',   'nav.reports',   'reports.view'],
    ]],
    ['group' => 'nav.office', 'icon' => 'building', 'items' => [
        ['/meetings',  'users',   'nav.meetings',  'meetings.manage'],
        ['/files',     'folder',  'nav.files',     'documents.manage'],
        ['/leaves',    'sun',     'nav.leaves',    ['leaves.own', 'leaves.approve', 'leaves.manage']],
    ]],
];
$allowed = fn (array $item) => !$item[3] || array_filter((array) $item[3], fn ($p) => can($p));
foreach ($nav as $i => &$entry) {
    if (isset($entry['group'])) {
        $entry['items'] = array_values(array_filter($entry['items'], $allowed));
        if (!$entry['items']) unset($nav[$i]);
    } elseif (!$allowed($entry)) {
        unset($nav[$i]);
    }
}
unset($entry);

// Telefonda alt çubuk: ortada menü düğmesi, iki yanında ikişer sık kullanılan bölüm
$flat = [];
foreach ($nav as $entry) {
    foreach (isset($entry['group']) ? $entry['items'] : [$entry] as $item) $flat[$item[0]] = $item;
}
$pinned = array_slice(array_values(array_merge(
    array_intersect_key($flat, array_flip(['/', '/projects', '/calendar', '/time'])),
    $flat
)), 0, 4);
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
    <?= View::partial('_pwa_head') ?>
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
            <?php foreach ($nav as $entry): ?>
                <?php if (isset($entry['group'])): ?>
                    <?php $active = array_filter($entry['items'], fn ($i) => request_is($i[0])); ?>
                    <details class="dropdown nav-group">
                        <summary class="<?= $active ? 'active' : '' ?>" title="<?= e(__($entry['group'])) ?>">
                            <?= icon($entry['icon']) ?><span><?= e(__($entry['group'])) ?></span><?= icon('chevron-down', 'icon icon-sm') ?>
                        </summary>
                        <div class="dropdown-menu">
                            <?php foreach ($entry['items'] as [$href, $ico, $label]): ?>
                                <a href="<?= $href ?>" class="<?= request_is($href) ? 'active' : '' ?>"><?= icon($ico) ?><?= e(__($label)) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: [$href, $ico, $label] = $entry; ?>
                    <a href="<?= $href ?>" class="<?= request_is($href) ? 'active' : '' ?>" title="<?= e(__($label)) ?>">
                        <?= icon($ico) ?><span><?= e(__($label)) ?></span>
                    </a>
                <?php endif; ?>
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
                    <a href="/account"><?= icon('user') ?><?= e(__('app.profile')) ?></a>
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

<?php // Telefonda üst menü yerine alt çubuk: 2 bölüm · menü düğmesi · 2 bölüm; kalanlar alttan açılan pencerede ?>
<nav class="bottomnav">
    <?php foreach ($pinned as $n => [$href, $ico, $label]): ?>
        <?php if ($n === 2): ?>
            <button type="button" class="bottomnav-menu" id="sheet-open" aria-label="<?= e(__('nav.menu')) ?>"><?= icon('more') ?></button>
        <?php endif; ?>
        <a href="<?= $href ?>" class="<?= request_is($href) ? 'active' : '' ?>"><?= icon($ico) ?><span><?= e(__($label)) ?></span></a>
    <?php endforeach; ?>
    <?php if (count($pinned) < 3): ?>
        <button type="button" class="bottomnav-menu" id="sheet-open" aria-label="<?= e(__('nav.menu')) ?>"><?= icon('more') ?></button>
    <?php endif; ?>
</nav>

<dialog class="sheet" id="sheet">
    <div class="sheet-body">
    <div class="sheet-handle"></div>
    <?php
    $loose = array_filter($nav, fn ($e) => !isset($e['group']));
    $sections = array_merge(
        [['group' => 'nav.main', 'items' => array_values($loose)]],
        array_values(array_filter($nav, fn ($e) => isset($e['group'])))
    );
    if ($canAdmin) {
        $sections[] = ['group' => 'nav.settings', 'items' => [['/settings', 'settings', 'nav.settings', null]]];
    }
    ?>
    <?php foreach ($sections as $s): ?>
        <div class="sheet-title"><?= e(__($s['group'])) ?></div>
        <div class="sheet-grid">
            <?php foreach ($s['items'] as [$href, $ico, $label]): ?>
                <a href="<?= $href ?>" class="<?= request_is($href) ? 'active' : '' ?>"><span class="sheet-icon"><?= icon($ico) ?></span><?= e(__($label)) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </div>
</dialog>

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

// Grafik ipucu: data-tip taşıyan öğenin üzerine gelince imleci izleyen kutu
const tip = document.createElement('div');
tip.className = 'viz-tip';
tip.hidden = true;
document.body.appendChild(tip);
document.addEventListener('mousemove', e => {
    const el = e.target.closest('[data-tip]');
    if (!el) { tip.hidden = true; return; }
    if (tip.dataset.for !== el.dataset.tip) { tip.innerHTML = el.dataset.tip; tip.dataset.for = el.dataset.tip; }
    tip.hidden = false;
    const x = Math.min(e.clientX + 14, innerWidth - tip.offsetWidth - 8);
    const y = e.clientY + 16 + tip.offsetHeight > innerHeight ? e.clientY - tip.offsetHeight - 12 : e.clientY + 16;
    tip.style.left = x + 'px';
    tip.style.top = y + 'px';
});

// Mobil menü penceresi: düğmeyle açılır, boşluğa dokununca kapanır
const sheet = document.getElementById('sheet');
document.getElementById('sheet-open')?.addEventListener('click', () => sheet.showModal());
sheet.addEventListener('click', e => { if (e.target === sheet) sheet.close(); });

// Üst menü sığmazsa yalnız ikonlara geçer (kaydırma çubuğu çıkmasın)
const mainnav = document.querySelector('.mainnav');
const fit = () => { mainnav.classList.remove('compact'); if (mainnav.scrollWidth > mainnav.clientWidth) mainnav.classList.add('compact'); };
addEventListener('resize', fit); fit();

// Açık açılır menüyü dışarı tıklanınca kapat; menüde biri açılınca diğeri kapanır
document.addEventListener('click', e => {
    document.querySelectorAll('details.dropdown[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
</script>
<script>window.FILEPICK = <?= json_encode(['choose' => __('file.choose'), 'drop' => __('file.drop'), 'remove' => __('file.remove')], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('filepick.js') ?>"></script>
</body>
</html>
