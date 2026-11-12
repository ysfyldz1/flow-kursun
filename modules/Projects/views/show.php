<?php
use App\Core\View;
use App\Services\TimeService;

$p = $project;
$row = fn (string $label, ?string $value) => $value
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . nl2br(e($value)) . '</dd></div>'
    : '';
$billingValue = match ($p['billing_type']) {
    'fixed'    => $p['fixed_fee'] !== null ? format_money((float) $p['fixed_fee'], $p['currency']) : null,
    'retainer' => $p['retainer_amount'] !== null ? format_money((float) $p['retainer_amount'], $p['currency']) . ' / ' . __('projects.month') : null,
    default    => $p['hourly_rate'] !== null ? format_money((float) $p['hourly_rate'], $p['currency']) . ' / ' . __('projects.hour') : null,
};
?>
<div class="page-head">
    <div>
        <a href="/clients/<?= $p['client_id'] ?>" class="back-link">← <?= e($p['client_name']) ?></a>
        <div class="title-line">
            <h1><?= e($p['name']) ?></h1>
            <span class="code code-lg"><?= e($p['number']) ?></span>
            <?= View::partial('Projects::_status', ['status' => $p['status']]) ?>
        </div>
        <p class="muted">
            <?= e(__('projects.type_' . $p['type'])) ?>
            <?= $p['court'] ? ' · ' . e($p['court']) : '' ?>
            <?= $p['case_number'] ? ' · ' . e($p['case_number']) : '' ?>
        </p>
    </div>
    <div class="head-actions">
        <?php if ($phases || can('projects.edit')): ?>
            <a href="/projects/<?= $p['id'] ?>/phases" class="btn btn-outline"><?= e(__('phases.title')) ?></a>
        <?php endif; ?>
        <?php if (can('projects.edit')): ?>
            <a href="/projects/<?= $p['id'] ?>/edit" class="btn btn-primary"><?= e(__('clients.edit')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <?php if ($phases): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('sliders') ?></span>
                    <h2><?= e(__('phases.title')) ?></h2>
                    <a href="/projects/<?= $p['id'] ?>/phases" class="btn btn-sm btn-outline head-right"><?= e(__('phases.manage')) ?></a>
                </header>
                <div class="phase-mini">
                    <?php $no = 0; foreach ($phases as $ph): ?>
                        <a href="/projects/<?= $p['id'] ?>/phases#phase-<?= $ph['id'] ?>" class="phase-mini-row">
                            <span class="cell-main">
                                <strong><?= $ph['is_out_of_scope'] ? '' : ++$no . '. ' ?><?= e($ph['name']) ?></strong>
                                <span class="muted small">
                                    <?= TimeService::hours((int) $ph['minutes']) ?> <?= e(__('projects.hours')) ?>
                                    <?php if ($ph['deadline']): ?> · <?= e(format_date($ph['deadline'])) ?><?php endif; ?>
                                </span>
                            </span>
                            <span class="phase-title">
                                <?php if ($ph['is_optional'] && !$ph['approved_at']): ?><span class="badge badge-amber"><?= e(__('phases.optional_pending')) ?></span><?php endif; ?>
                                <?php if ($ph['completed_at']): ?><span class="badge badge-green"><?= e(__('phases.completed')) ?></span><?php endif; ?>
                                <?php if ($ph['is_overdue']): ?><span class="badge badge-red"><?= e(__('phases.overdue')) ?></span><?php endif; ?>
                                <?php if (can('rates.view') && $ph['budget_state']): ?>
                                    <span class="cell-main align-right">
                                        <strong class="<?= $ph['over'] ? 'text-red' : ($ph['budget_state'] === 'warn' ? 'text-warn' : '') ?>">%<?= (int) $ph['used_pct'] ?></strong>
                                        <span class="meter"><span style="width: <?= min(100, (int) $ph['used_pct']) ?>%" class="<?= $ph['budget_state'] === 'ok' ? '' : ($ph['budget_state'] === 'over' ? 'meter-over' : 'meter-warn') ?>"></span></span>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('clock') ?></span>
                <h2><?= e(__('dash.recent_time')) ?></h2>
                <?php if ($p['status'] === 'open' && (can('time.own') || can('time.all'))): ?>
                    <a href="/time" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('time.add')) ?></a>
                <?php endif; ?>
            </header>
            <div class="totals-row">
                <div><span><?= e(__('time.total')) ?></span><strong><?= TimeService::hours((int) $totals['minutes']) ?> <?= e(__('projects.hours')) ?></strong></div>
                <div><span><?= e(__('time.billable')) ?></span><strong><?= TimeService::hours((int) $totals['billable_minutes']) ?> <?= e(__('projects.hours')) ?></strong></div>
                <?php if ($p['budget_hours'] !== null): $pct = min(100, round($totals['minutes'] / 60 / max((float) $p['budget_hours'], 0.1) * 100)); ?>
                    <div><span><?= e(__('projects.budget_hours')) ?></span><strong>%<?= $pct ?></strong><span class="meter"><span style="width: <?= $pct ?>%" class="<?= $pct >= 90 ? 'meter-warn' : '' ?>"></span></span></div>
                <?php endif; ?>
                <?php if (can('rates.view') && $p['billing_type'] === 'hourly'): ?>
                    <div><span><?= e(__('time.amount')) ?></span><strong><?= e(format_money((float) $totals['amount'], $p['currency'])) ?></strong></div>
                <?php endif; ?>
            </div>
            <?php if (!$entries): ?>
                <div class="empty"><?= e(__('projects.no_time')) ?></div>
            <?php else: ?>
                <table class="table table-m">
                    <?php foreach ($entries as $t): ?>
                        <tr>
                            <td class="muted nowrap m-meta"><?= e(format_date($t['work_date'])) ?></td>
                            <td class="cell-main m-title"><strong><?= e($t['user_name']) ?><?php if ($t['phase_name'] !== null): ?> <span class="badge <?= $t['phase_oos'] ? 'badge-amber' : 'badge-gray' ?>"><?= e($t['phase_oos'] ? __('phases.out_of_scope') : $t['phase_name']) ?></span><?php endif; ?></strong><span><?= e(mb_strimwidth($t['description'], 0, 120, '…')) ?></span></td>
                            <td class="cell-actions m-value">
                                <strong><?= TimeService::formatMinutes((int) $t['minutes']) ?></strong>
                                <?php if (!$t['is_billable']): ?><br><span class="muted small"><?= e(__('time.non_billable')) ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('wallet') ?></span>
                <h2><?= e(__('nav.expenses')) ?></h2>
                <?php if ($expenses): ?><span class="muted small"><?= e(format_money(array_sum(array_column($expenses, 'amount')), $p['currency'])) ?></span><?php endif; ?>
                <?php if ($p['status'] !== 'closed' && (can('expenses.own') || can('expenses.all'))): ?>
                    <a href="/expenses?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('expenses.add')) ?></a>
                <?php endif; ?>
            </header>
            <?php if (!$expenses): ?>
                <div class="empty"><?= e(__('expenses.none_for_project')) ?></div>
            <?php else: ?>
                <table class="table table-m">
                    <?php foreach ($expenses as $x): ?>
                        <tr>
                            <td class="muted nowrap m-meta"><?= e(format_date($x['expense_date'])) ?></td>
                            <td class="cell-main m-title"><strong><?= e(__('expenses.cat_' . $x['category'])) ?></strong><span><?= e($x['description']) ?> · <?= e($x['user_name']) ?></span></td>
                            <td class="cell-actions m-value">
                                <strong><?= e(format_money((float) $x['amount'], $x['currency'])) ?></strong><br>
                                <span class="muted small"><?= e(__($x['statement_id'] ? 'time.billed' : ($x['from_advance'] ? 'adv.state_advance' : ($x['is_billable'] ? 'expenses.state_unbilled' : 'expenses.state_internal')))) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <?php if (can('tasks.own') || can('tasks.all')): $tasks = \App\Services\TaskService::forProject((int) $p['id']); ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('check') ?></span>
                    <h2><?= e(__('tasks.title')) ?></h2>
                    <?php if ($p['status'] !== 'closed'): ?>
                        <a href="/tasks?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('tasks.add')) ?></a>
                    <?php endif; ?>
                </header>
                <?php if (!$tasks): ?>
                    <div class="empty"><?= e(__('tasks.none_for_project')) ?></div>
                <?php endif; ?>
                <?php foreach ($tasks as $t): ?>
                    <?= View::partial('Tasks::_row', ['t' => $t, 'hideProject' => true]) ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (can('calendar.view')): $events = \App\Services\CalendarService::forProject((int) $p['id']); ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('calendar') ?></span>
                    <h2><?= e(__('cal.title')) ?></h2>
                    <?php if (can('calendar.edit') && $p['status'] !== 'closed'): ?>
                        <a href="/calendar/new?project_id=<?= $p['id'] ?>&type=<?= $p['type'] === 'litigation' ? 'hearing' : 'meeting' ?>" class="btn btn-sm btn-outline head-right"><?= icon('plus', 'icon icon-sm') ?><?= e(__('cal.add')) ?></a>
                    <?php endif; ?>
                </header>
                <?php if (!$events): ?>
                    <div class="empty"><?= e(__('cal.none_for_project')) ?></div>
                <?php else: ?>
                    <?php foreach ($events as $ev): ?>
                        <?= View::partial('Calendar::_row', ['e' => $ev, 'showDate' => true, 'hideProject' => true]) ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (\App\Services\DocumentService::enabled()): ?>
            <section class="card widget widget-auto" id="documents">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('folder') ?></span>
                    <h2><?= e(__('docs.title')) ?></h2>
                    <?php if (can('documents.manage')): ?>
                        <button type="button" class="btn btn-sm btn-outline head-right" data-new-folder><?= icon('folder', 'icon icon-sm') ?><?= e(__('docs.new_folder')) ?></button>
                        <button type="button" class="btn btn-sm btn-outline" data-od-open><?= icon('globe', 'icon icon-sm') ?><?= e(__('onedrive.add')) ?></button>
                        <label class="btn btn-sm btn-outline upload-zone" data-upload="/projects/<?= $p['id'] ?>/documents/upload"
                               data-csrf="<?= e(\App\Core\Csrf::token()) ?>" data-msg-done="<?= e(__('docs.uploaded')) ?>" data-msg-empty="<?= e(__('docs.file_empty')) ?>">
                            <?= icon('plus', 'icon icon-sm') ?><?= e(__('docs.upload')) ?>
                            <input type="file" multiple hidden>
                            <span class="upload-status"></span>
                        </label>
                    <?php endif; ?>
                </header>
                <?php if (can('documents.manage')): ?>
                    <form class="docs-newfolder" hidden>
                        <?= icon('folder') ?>
                        <input name="name" maxlength="200" placeholder="<?= e(__('docs.new_folder_prompt')) ?>" autocomplete="off">
                        <button class="btn btn-sm btn-primary"><?= e(__('docs.create')) ?></button>
                        <button type="button" class="btn btn-sm btn-outline" data-cancel><?= e(__('app.cancel')) ?></button>
                        <span class="field-error" data-error></span>
                    </form>
                <?php endif; ?>
                <div class="docs-body" data-src="/projects/<?= $p['id'] ?>/documents"><div class="empty"><?= e(__('docs.loading')) ?></div></div>
            </section>
            <?php if (can('documents.manage')): ?>
                <dialog class="modal" id="od-picker">
                    <div class="modal-head">
                        <h3><?= e(__('onedrive.pick_title')) ?></h3>
                        <button type="button" class="icon-btn" data-close aria-label="×">✕</button>
                    </div>
                    <div class="od-modal" data-od-root></div>
                    <div class="modal-foot">
                        <span class="muted small" data-od-status></span>
                        <button type="button" class="btn btn-outline" data-close><?= e(__('app.cancel')) ?></button>
                        <button type="button" class="btn btn-primary" data-od-copy disabled><?= e(__('onedrive.copy_here')) ?></button>
                    </div>
                </dialog>
                <script src="<?= asset('onedrive.js') ?>"></script>
            <?php endif; ?>
            <script src="<?= asset('upload.js') ?>" defer></script>
            <script>
            (() => {
                const section = document.getElementById('documents');
                const body = section.querySelector('.docs-body');
                const zone = section.querySelector('[data-upload]');
                const uploadBase = zone?.dataset.upload;
                const csrf = <?= json_encode(\App\Core\Csrf::token()) ?>;
                // Açık klasör: liste, yükleme ve yeni klasör bu klasöre göre çalışır
                let folder = '';
                const q = () => folder ? '?folder=' + encodeURIComponent(folder) : '';
                const load = () => fetch(body.dataset.src + q(), { credentials: 'same-origin' }).then(r => r.text()).then(html => {
                    body.innerHTML = html;
                    folder = body.querySelector('[data-current-folder]')?.dataset.currentFolder || '';
                    if (zone) zone.dataset.upload = uploadBase + q();
                });
                load();
                section.addEventListener('uploaded', load);
                // OneDrive'dan ekle: seçilenler açık klasöre kopyalanır
                const od = document.getElementById('od-picker');
                if (od) {
                    const odStatus = od.querySelector('[data-od-status]');
                    const odCopy = od.querySelector('[data-od-copy]');
                    const odSelected = <?= json_encode(__('onedrive.selected')) ?>;
                    let browser = null;
                    section.querySelector('[data-od-open]').addEventListener('click', () => {
                        od.showModal();
                        odStatus.textContent = '';
                        if (!browser) {
                            browser = OneDriveBrowser(od.querySelector('[data-od-root]'), {
                                text: <?= json_encode([
                                    'search' => __('onedrive.search'), 'searchBtn' => __('app.search'), 'root' => __('onedrive.root'),
                                    'results' => __('onedrive.results'), 'items' => __('docs.folder_items'), 'empty' => __('onedrive.empty'),
                                    'noResults' => __('onedrive.no_results'), 'tooLarge' => __('onedrive.too_large_short'), 'connect' => __('ms.connect'), 'shortcut' => __('onedrive.shortcut'),
                                ], JSON_UNESCAPED_UNICODE) ?>,
                                onChange: ids => { odCopy.disabled = !ids.length; odStatus.textContent = ids.length ? odSelected.replace(':count', ids.length) : ''; },
                            });
                            browser.load();
                        }
                    });
                    od.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => od.close()));
                    od.addEventListener('click', e => { if (e.target === od) od.close(); });
                    odCopy.addEventListener('click', async () => {
                        odCopy.disabled = true;
                        odStatus.textContent = <?= json_encode(__('onedrive.copying')) ?>;
                        try {
                            const r = await OneDriveSend('/projects/<?= $p['id'] ?>/documents/from-onedrive', csrf, browser.selected(), { folder });
                            if (r.errors.length) { odStatus.textContent = r.message + ' · ' + r.errors.join(' '); browser.reload(); }
                            else { od.close(); }
                            load();
                        } catch (e) {
                            odStatus.textContent = e.message;
                            odCopy.disabled = false;
                        }
                    });
                }
                // Yeni klasör: kutunun içinde açılan satır (Enter oluşturur, Esc kapatır)
                const nf = section.querySelector('.docs-newfolder');
                const nfError = nf?.querySelector('[data-error]');
                const closeNf = () => { nf.hidden = true; nf.name.value = ''; nfError.textContent = ''; };
                section.querySelector('[data-new-folder]')?.addEventListener('click', () => {
                    nf.hidden = !nf.hidden;
                    if (!nf.hidden) nf.name.focus();
                });
                nf?.querySelector('[data-cancel]').addEventListener('click', closeNf);
                nf?.addEventListener('keydown', e => { if (e.key === 'Escape') closeNf(); });
                nf?.addEventListener('submit', async e => {
                    e.preventDefault();
                    const name = nf.name.value.trim();
                    if (!name) { nf.name.focus(); return; }
                    const btn = nf.querySelector('.btn-primary');
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.append('_csrf', csrf);
                    fd.append('name', name);
                    fd.append('folder', folder);
                    const r = await fetch('/projects/<?= $p['id'] ?>/documents/folder', { method: 'POST', body: fd, credentials: 'same-origin' });
                    const json = await r.json().catch(() => ({}));
                    btn.disabled = false;
                    if (!r.ok) { nfError.textContent = json.error || r.statusText; return; }
                    closeNf();
                    load();
                });
                body.addEventListener('click', async e => {
                    const nav = e.target.closest('[data-folder]');
                    if (nav) { folder = nav.dataset.folder; load(); return; }
                    const btn = e.target.closest('[data-share]');
                    if (!btn) return;
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.append('_csrf', <?= json_encode(\App\Core\Csrf::token()) ?>);
                    await fetch(btn.dataset.share, { method: 'POST', body: fd, credentials: 'same-origin' });
                    load();
                });
            })();
            </script>
        <?php endif; ?>

        <?php if (\App\Services\DocumentService::enabled()): ?>
            <?= View::partial('Projects::_emails', ['p' => $p]) ?>
        <?php endif; ?>

        <?php if ($p['description']): ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('projects.description')) ?></h2></header>
                <div class="card-body"><?= nl2br(e($p['description'])) ?></div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('clients.section_general')) ?></h2></header>
            <dl class="dl">
                <div class="dl-row"><dt><?= e(__('clients.name')) ?></dt><dd><a href="/clients/<?= $p['client_id'] ?>" class="link"><?= e($p['client_name']) ?></a></dd></div>
                <?= $row('projects.responsible', $p['responsible_name']) ?>
                <?= $row('projects.opened_at', format_date($p['opened_at'])) ?>
                <?= $row('projects.closed_at', format_date($p['closed_at'])) ?>
                <?= $row('portal.title', __($p['portal_visible'] ? 'projects.portal_shown' : 'projects.portal_hidden')) ?>
                <?= $row('projects.budget_hours', $p['budget_hours'] !== null ? rtrim(rtrim((string) $p['budget_hours'], '0'), '.') . ' ' . __('projects.hours') : null) ?>
                <?php if (can('rates.view')): ?>
                    <?= $row('projects.billing_type', __('projects.billing_' . $p['billing_type'])) ?>
                    <?= $row('clients.currency', $p['currency']) ?>
                    <?= $row('projects.fee', $billingValue) ?>
                <?php endif; ?>
            </dl>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('users') ?></span>
                <h2><?= e(__('projects.team')) ?></h2>
            </header>
            <?php if (!$p['members']): ?>
                <div class="empty"><?= e(__('dash.empty')) ?></div>
            <?php else: ?>
                <dl class="dl">
                    <?php foreach ($p['members'] as $m): ?>
                        <div class="dl-row">
                            <dt class="cell-main">
                                <strong class="text-strong"><?= e($m['name']) ?></strong>
                                <?php if ((int) $m['user_id'] === (int) $p['responsible_user_id']): ?><span><?= e(__('projects.responsible')) ?></span><?php endif; ?>
                            </dt>
                            <?php if (isset($rates[$m['user_id']])): $r = $rates[$m['user_id']]; ?>
                                <dd class="cell-main align-right">
                                    <?= $r['rate'] !== null ? e(format_money($r['rate'], $r['currency'])) : '<span class="text-warn">' . e(__('projects.no_rate')) . '</span>' ?>
                                    <?php if ($r['source']): ?><span><?= e(__('projects.rate_source_' . $r['source'])) ?></span><?php endif; ?>
                                </dd>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>
</div>
