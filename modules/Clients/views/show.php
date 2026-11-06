<?php
$c = $client;
$row = fn (string $label, ?string $value) => $value
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . nl2br(e($value)) . '</dd></div>'
    : '';
?>
<div class="page-head">
    <div>
        <a href="/clients" class="back-link">← <?= e(__('clients.title')) ?></a>
        <div class="title-line">
            <h1><?= e($c['name']) ?></h1>
            <span class="code code-lg"><?= e($c['code']) ?></span>
            <?php if (!$c['is_active']): ?><span class="badge badge-gray"><?= e(__('users.inactive')) ?></span><?php endif; ?>
        </div>
        <p class="muted"><?= e(__('clients.type_' . $c['type'])) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?></p>
    </div>
    <?php if (can('clients.edit')): ?>
        <div class="head-actions">
            <form method="post" action="/clients/<?= $c['id'] ?>/active">
                <?= csrf_field() ?>
                <button class="btn btn-outline"><?= e(__($c['is_active'] ? 'users.deactivate' : 'users.activate')) ?></button>
            </form>
            <a href="/clients/<?= $c['id'] ?>/edit" class="btn btn-primary"><?= e(__('clients.edit')) ?></a>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('folder') ?></span>
                <h2><?= e(__('nav.projects')) ?></h2>
            </header>
            <div class="empty"><?= e(__('clients.no_projects')) ?></div>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('users') ?></span>
                <h2><?= e(__('clients.section_contacts')) ?></h2>
            </header>
            <?php if (!$c['contacts']): ?>
                <div class="empty"><?= e(__('dash.empty')) ?></div>
            <?php else: ?>
                <table class="table">
                    <?php foreach ($c['contacts'] as $p): ?>
                        <tr>
                            <td class="cell-main">
                                <strong><?= e($p['name']) ?> <?php if ($p['is_primary']): ?><span class="badge badge-blue"><?= e(__('clients.primary')) ?></span><?php endif; ?></strong>
                                <span><?= e($p['title'] ?? '') ?></span>
                            </td>
                            <td><?= $p['email'] ? '<a href="mailto:' . e($p['email']) . '" class="link">' . e($p['email']) . '</a>' : '' ?></td>
                            <td class="muted"><?= e($p['phone'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($c['notes']): ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('clients.notes')) ?></h2></header>
                <div class="card-body"><?= nl2br(e($c['notes'])) ?></div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('clients.section_general')) ?></h2></header>
            <dl class="dl">
                <?= $row($c['type'] === 'person' ? 'clients.tckn' : 'clients.vkn', $c['tax_id']) ?>
                <?= $row('clients.tax_office', $c['tax_office']) ?>
                <?= $row('auth.email', $c['email']) ?>
                <?= $row('clients.phone', $c['phone']) ?>
                <?= $row('clients.address', trim(($c['address'] ?? '') . "\n" . implode(' / ', array_filter([$c['city'], $c['country']])))) ?>
            </dl>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('clients.section_billing')) ?></h2></header>
            <dl class="dl">
                <?= $row('clients.currency', $c['currency']) ?>
                <?= $row('clients.locale', __('lang.' . $c['locale'])) ?>
                <?= $row('clients.hourly_rate', $c['hourly_rate'] !== null ? format_money((float) $c['hourly_rate'], $c['currency']) : __('clients.rate_default_used')) ?>
                <?php foreach ($c['user_rates'] as $r): ?>
                    <div class="dl-row"><dt><?= e($r['name']) ?></dt><dd><?= e(format_money((float) $r['hourly_rate'], $c['currency'])) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>
</div>
