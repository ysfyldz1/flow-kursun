<?php
// Proje sayfasında Yazışmalar: kaydedilen e-postalar ve kişinin kendi posta kutusundan seçme penceresi.
use App\Services\Microsoft;
use App\Services\ProjectEmailService;

$emails = ProjectEmailService::forProject((int) $p['id']);
$canManage = can('documents.manage');
$connected = $canManage && Microsoft::connection((int) auth()['id']);
$chips = $canManage ? ProjectEmailService::clientAddresses((int) $p['client_id']) : [];
?>
<section class="card widget widget-auto" id="emails">
    <header class="widget-head">
        <span class="widget-icon"><?= icon('bell') ?></span>
        <h2><?= e(__('emails.title')) ?></h2>
        <?php if ($emails): ?><span class="muted small"><?= count($emails) ?></span><?php endif; ?>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-sm btn-outline head-right" data-email-open><?= icon('plus', 'icon icon-sm') ?><?= e(__('emails.add')) ?></button>
        <?php endif; ?>
    </header>
    <?php if (!$emails): ?>
        <div class="empty"><?= e(__('emails.empty')) ?></div>
    <?php endif; ?>
    <?php foreach ($emails as $m): ?>
        <div class="widget-row">
            <a href="/projects/<?= $p['id'] ?>/documents/<?= e((string) $m['drive_item_id']) ?>/download" class="cell-main" title="<?= e(__('emails.download_hint')) ?>">
                <strong><?= $m['has_attachments'] ? '📎 ' : '' ?><?= e($m['subject'] ?: __('emails.no_subject')) ?></strong>
                <span><?= e($m['from_name'] ?: $m['from_email']) ?><?= $m['preview'] ? ' — ' . e(mb_strimwidth($m['preview'], 0, 90, '…')) : '' ?></span>
                <?php if (!empty($m['note'])): ?><span class="email-note">✎ <?= e($m['note']) ?></span><?php endif; ?>
            </a>
            <div class="cell-main align-right nowrap">
                <strong><?= e(format_date($m['received_at'], true)) ?></strong>
                <span><?= e(__('emails.saved_by', ['name' => (string) $m['saved_by_name']])) ?></span>
            </div>
            <?php if ($canManage): ?>
                <form method="post" action="/projects/<?= $p['id'] ?>/emails/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= e(__('emails.confirm_remove')) ?>')">
                    <?= csrf_field() ?><button class="btn btn-sm btn-outline btn-danger" title="<?= e(__('emails.remove')) ?>">✕</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php if ($canManage): ?>
<dialog class="modal" id="email-picker">
    <div class="modal-head">
        <h3><?= e(__('emails.pick_title')) ?></h3>
        <button type="button" class="icon-btn" data-close aria-label="×">✕</button>
    </div>
    <?php if (!$connected): ?>
        <div class="modal-body">
            <p><?= e(__('emails.not_connected')) ?></p>
            <a href="/account" class="btn btn-primary"><?= e(__('ms.connect')) ?></a>
        </div>
    <?php else: ?>
        <form class="modal-search" data-search>
            <input type="search" name="q" placeholder="<?= e(__('emails.search_hint')) ?>" autocomplete="off">
            <button class="btn btn-outline"><?= icon('search') ?></button>
        </form>
        <?php if ($chips): ?>
            <div class="modal-chips">
                <span class="muted small"><?= e(__('emails.client_filter')) ?></span>
                <?php foreach ($chips as $c): ?><button type="button" class="chip" data-chip="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="modal-body modal-list" data-list><div class="empty"><?= e(__('docs.loading')) ?></div></div>
        <div class="modal-foot">
            <span class="muted small" data-status></span>
            <button type="button" class="btn btn-outline" data-close><?= e(__('app.cancel')) ?></button>
            <button type="button" class="btn btn-primary" data-save disabled><?= e(__('emails.save')) ?></button>
        </div>
    <?php endif; ?>
</dialog>

<script>
(() => {
    const dlg = document.getElementById('email-picker');
    const base = '/projects/<?= $p['id'] ?>/emails';
    const csrf = <?= json_encode(\App\Core\Csrf::token()) ?>;
    const t = <?= json_encode([
        'empty' => __('emails.none_found'), 'saved' => __('emails.already'), 'selected' => __('emails.selected'),
        'saving' => __('emails.saving'), 'noSubject' => __('emails.no_subject'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    document.querySelector('[data-email-open]').addEventListener('click', () => { dlg.showModal(); if (list && !loaded) search(''); });
    dlg.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => dlg.close()));
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

    const list = dlg.querySelector('[data-list]');
    if (!list) return;
    const status = dlg.querySelector('[data-status]');
    const saveBtn = dlg.querySelector('[data-save]');
    const form = dlg.querySelector('[data-search]');
    let loaded = false;

    const sync = () => {
        const n = list.querySelectorAll('input:checked').length;
        saveBtn.disabled = !n;
        status.textContent = n ? t.selected.replace(':count', n) : '';
    };
    const search = async q => {
        loaded = true;
        list.innerHTML = '<div class="empty">…</div>';
        const r = await fetch(base + '/search?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
        const json = await r.json().catch(() => ({}));
        if (!r.ok) {
            list.innerHTML = '<div class="empty text-warn">' + esc(json.error || r.statusText) + (json.connect ? ' <a class="link" href="' + json.connect + '">→</a>' : '') + '</div>';
            return;
        }
        list.innerHTML = json.messages.length ? json.messages.map(m => `
            <label class="pick-row${m.saved ? ' is-saved' : ''}">
                <input type="checkbox" value="${esc(m.id)}" ${m.saved ? 'disabled' : ''}>
                <span class="cell-main">
                    <strong>${m.attachments ? '📎 ' : ''}${esc(m.subject || t.noSubject)}</strong>
                    <span>${esc(m.from)} — ${esc(m.preview)}</span>
                </span>
                <span class="nowrap muted small">${m.saved ? t.saved : esc(m.date)}</span>
            </label>`).join('') : '<div class="empty">' + t.empty + '</div>';
        sync();
    };
    list.addEventListener('change', sync);
    form.addEventListener('submit', e => { e.preventDefault(); search(form.q.value); });
    dlg.querySelectorAll('[data-chip]').forEach(c => c.addEventListener('click', () => { form.q.value = c.dataset.chip; search(c.dataset.chip); }));

    saveBtn.addEventListener('click', async () => {
        const ids = [...list.querySelectorAll('input:checked')].map(i => i.value);
        saveBtn.disabled = true;
        status.textContent = t.saving;
        const fd = new FormData();
        fd.append('_csrf', csrf);
        ids.forEach(id => fd.append('ids[]', id));
        const r = await fetch(base, { method: 'POST', body: fd, credentials: 'same-origin' });
        const json = await r.json().catch(() => ({}));
        if (!r.ok && !json.saved) { status.textContent = json.error || (json.errors || []).join(' ') || r.statusText; saveBtn.disabled = false; return; }
        location.hash = 'emails';
        location.reload();
    });
})();
</script>
<?php endif; ?>
