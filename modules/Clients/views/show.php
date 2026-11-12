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
        <?php if (can('projects.view')): ?>
        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('folder') ?></span>
                <h2><?= e(__('nav.projects')) ?></h2>
                <?php if (can('projects.edit') && $c['is_active']): ?>
                    <a href="/projects/new?client_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('projects.new')) ?></a>
                <?php endif; ?>
            </header>
            <?php if (!$projects): ?>
                <div class="empty"><?= e(__('clients.no_projects')) ?></div>
            <?php else: ?>
                <table class="table table-m">
                    <?php foreach ($projects as $p): ?>
                        <tr class="row-link" onclick="location='/projects/<?= $p['id'] ?>'">
                            <td class="m-meta"><span class="code"><?= e($p['number']) ?></span></td>
                            <td class="cell-main m-title">
                                <strong><?= e($p['name']) ?></strong>
                                <span><?= e(__('projects.type_' . $p['type'])) ?><?= $p['responsible_name'] ? ' · ' . e($p['responsible_name']) : '' ?></span>
                            </td>
                            <td class="cell-actions m-value"><?= \App\Core\View::partial('Projects::_status', ['status' => $p['status']]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('users') ?></span>
                <h2><?= e(__('clients.section_contacts')) ?></h2>
            </header>
            <?php if (!$c['contacts']): ?>
                <div class="empty"><?= e(__('dash.empty')) ?></div>
            <?php else: ?>
                <table class="table table-m">
                    <?php foreach ($c['contacts'] as $p): ?>
                        <tr>
                            <td class="cell-main m-title">
                                <strong><?= e($p['name']) ?> <?php if ($p['is_primary']): ?><span class="badge badge-blue"><?= e(__('clients.primary')) ?></span><?php endif; ?></strong>
                                <span><?= e($p['title'] ?? '') ?></span>
                            </td>
                            <td class="m-meta"><?= $p['email'] ? '<a href="mailto:' . e($p['email']) . '" class="link">' . e($p['email']) . '</a>' : '' ?></td>
                            <td class="muted <?= $p['phone'] ? 'm-meta' : 'm-empty' ?>"><?= e($p['phone'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($meetings !== null): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('users') ?></span>
                    <h2><?= e(__('meetings.title')) ?></h2>
                    <a href="/meetings/new?client_id=<?= $c['id'] ?>" class="widget-link"><?= e(__('meetings.new')) ?> →</a>
                </header>
                <?php if (!$meetings): ?>
                    <div class="empty"><?= e(__('meetings.empty_client')) ?></div>
                <?php endif; ?>
                <?php foreach ($meetings as $m): ?>
                    <a href="/meetings/<?= $m['id'] ?>" class="widget-row">
                        <div class="cell-main">
                            <strong><?= e(format_date($m['meeting_at'], true)) ?></strong>
                            <span><?= e(__('meetings.responses')) ?>: <?= (int) $m['response_count'] ?> / <?= (int) $m['shareholder_count'] ?></span>
                        </div>
                        <div class="cell-main align-right"><?= \App\Core\View::partial('Meetings::_status', ['m' => $m]) ?></div>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (can('portal.manage')): ?>
            <?php $inviteLink = $_SESSION['_portal_invite_link'] ?? null; unset($_SESSION['_portal_invite_link']); ?>
            <section class="card widget widget-auto" id="portal">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('globe') ?></span>
                    <h2><?= e(__('portal.access')) ?></h2>
                </header>
                <?php if ($inviteLink): ?>
                    <div class="card-body invite-box">
                        <strong><?= e(__('portal.invite_link')) ?></strong>
                        <p class="muted small"><?= e(__('portal.invite_link_hint')) ?></p>
                        <div class="copy-row">
                            <input class="input-plain" value="<?= e($inviteLink) ?>" readonly onclick="this.select()" id="invite-link">
                            <button type="button" class="btn btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('invite-link').value); this.textContent='✓'"><?= e(__('portal.copy')) ?></button>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($portalUsers): ?>
                    <table class="table table-m">
                        <?php foreach ($portalUsers as $pu): ?>
                            <tr class="<?= $pu['is_active'] ? '' : 'row-muted' ?>">
                                <td class="cell-main m-title"><strong><?= e($pu['name']) ?></strong><span><?= e($pu['email']) ?></span></td>
                                <td class="m-value">
                                    <?php if (!$pu['is_active']): ?>
                                        <span class="badge badge-gray"><?= e(__('users.inactive')) ?></span>
                                    <?php elseif (!$pu['password']): ?>
                                        <span class="badge badge-amber"><?= e(__('portal.invited')) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-green"><?= e(__('users.active')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="muted small m-meta"><?= $pu['last_login_at'] ? e(__('users.last_login')) . ': ' . e(format_date($pu['last_login_at'], true)) : '' ?></td>
                                <td class="cell-actions m-actions">
                                    <?php if ($pu['is_active'] && !$pu['password']): ?>
                                        <form method="post" action="/clients/<?= $c['id'] ?>/portal">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="portal_name" value="<?= e($pu['name']) ?>">
                                            <input type="hidden" name="portal_email" value="<?= e($pu['email']) ?>">
                                            <button class="btn btn-sm btn-outline"><?= e(__('portal.new_link')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/clients/<?= $c['id'] ?>/portal/<?= $pu['id'] ?>/active">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline <?= $pu['is_active'] ? 'btn-danger' : '' ?>"><?= e(__($pu['is_active'] ? 'portal.revoke' : 'users.activate')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                <?php if ($c['is_active']): ?>
                    <form method="post" action="/clients/<?= $c['id'] ?>/portal" class="card-body portal-invite">
                        <?= csrf_field() ?>
                        <p class="muted small" style="margin:0 0 10px"><?= e(__('portal.invite_hint')) ?></p>
                        <div class="add-line portal-invite-row">
                            <select class="select-plain" onchange="const o=this.selectedOptions[0]; this.form.portal_name.value=o.dataset.name||''; this.form.portal_email.value=o.dataset.email||''; this.form.contact_id.value=o.value">
                                <option value=""><?= e(__('portal.pick_contact')) ?></option>
                                <?php foreach ($c['contacts'] as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-email="<?= e((string) $p['email']) ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="contact_id">
                            <input name="portal_name" class="input-plain" placeholder="<?= e(__('users.name')) ?>" value="<?= e(old('portal_name')) ?>" required>
                            <input name="portal_email" type="email" class="input-plain" placeholder="<?= e(__('auth.email')) ?>" value="<?= e(old('portal_email')) ?>" required>
                            <button class="btn btn-primary"><?= e(__('portal.invite')) ?></button>
                        </div>
                        <?= error_for('portal_name') ?> <?= error_for('portal_email') ?>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

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
                <?php if (can('rates.view')): ?>
                    <?= $row('clients.hourly_rate', $c['hourly_rate'] !== null ? format_money((float) $c['hourly_rate'], $c['currency']) : __('clients.rate_default_used')) ?>
                    <?= $row('discount.field', $c['discount_pct'] !== null ? '%' . rtrim(rtrim((string) $c['discount_pct'], '0'), '.') : null) ?>
                    <?php foreach ($c['user_rates'] as $r): ?>
                        <div class="dl-row"><dt><?= e($r['name']) ?></dt><dd><?= e(format_money((float) $r['hourly_rate'], $c['currency'])) ?></dd></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </dl>
        </section>

        <?php if (can('payments.manage')): ?>
            <?php $advances = \Modules\Advances\AdvanceService::balances((int) $c['id']); ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <h2><?= e(__('adv.title')) ?></h2>
                    <a href="/clients/<?= $c['id'] ?>/advance" class="widget-link"><?= e(__($c['uses_advance'] || $advances ? 'adv.open' : 'adv.turn_on')) ?> →</a>
                </header>
                <?php if (!$c['uses_advance'] && !$advances): ?>
                    <div class="card-body muted small"><?= e(__('adv.off_hint')) ?></div>
                <?php else: ?>
                    <dl class="dl">
                        <?php foreach ($advances as $b): ?>
                            <div class="dl-row"><dt><?= e(__('adv.balance_in', ['currency' => $b['currency']])) ?></dt><dd class="<?= (float) $b['balance'] < 0 ? 'text-danger' : '' ?>"><strong><?= e(format_money((float) $b['balance'], $b['currency'])) ?></strong></dd></div>
                        <?php endforeach; ?>
                        <?php if (!$c['uses_advance']): ?><div class="dl-row"><dt></dt><dd class="muted small"><?= e(__('adv.off')) ?></dd></div><?php endif; ?>
                    </dl>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
