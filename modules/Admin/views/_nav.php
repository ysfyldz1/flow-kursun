<?php
$items = [
    ['/settings/users',        'users',    'users.title', 'users.manage'],
    ['/settings/roles',        'shield',   'roles.title', 'roles.manage'],
    ['/settings/organization', 'building', 'org.title',   'settings.manage'],
    ['/settings/holidays',     'calendar', 'holidays.title', 'settings.manage'],
    ['/settings/microsoft',    'globe',    'ms.title',    'settings.manage'],
    ['/settings/mail',         'bell',     'mail.title',  'settings.manage'],
    ['/settings/system',       'sliders',  'system.title', 'settings.manage'],
    ['/settings/setup',        'check',    'guide.title', 'settings.manage'],
];
?>
<aside class="subnav card">
    <div class="subnav-title"><?= e(__('nav.settings')) ?></div>
    <?php foreach ($items as [$href, $ico, $label, $perm]): ?>
        <?php if (!can($perm)) continue; ?>
        <a href="<?= $href ?>" class="<?= request_is($href) ? 'active' : '' ?>"><?= icon($ico) ?><?= e(__($label)) ?></a>
    <?php endforeach; ?>
</aside>
