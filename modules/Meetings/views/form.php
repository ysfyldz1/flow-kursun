<?php
use App\Services\MeetingService;

$v = fn (string $k, string $default = '') => old($k, (string) ($m[$k] ?? $default));
$date = old('date', $m ? substr($m['meeting_at'], 0, 10) : '');
$time = old('time', $m ? substr($m['meeting_at'], 11, 5) : '10:00');
$projectId = (int) $v('project_id', (string) ($_GET['project_id'] ?? ''));
?>
<div class="page-head">
    <div>
        <a href="<?= $m ? '/meetings/' . $m['id'] : '/meetings' ?>" class="back-link">← <?= e($m ? $m['client_name'] : __('meetings.title')) ?></a>
        <h1><?= e(__($m ? 'meetings.edit' : 'meetings.new')) ?></h1>
    </div>
</div>

<form method="post" action="<?= $m ? '/meetings/' . $m['id'] : '/meetings' ?>" enctype="multipart/form-data" class="grid grid-3-1" id="meeting-form">
    <?= csrf_field() ?>
    <div class="stack">
        <section class="card form-card">
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('meetings.company')) ?> *</span>
                    <?php if ($m): ?>
                        <input value="<?= e($m['client_code'] . ' · ' . $m['client_name']) ?>" disabled>
                    <?php else: ?>
                        <select name="client_id" required>
                            <option value=""><?= e(__('meetings.choose_client')) ?></option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $clientId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <?= error_for('client_id') ?>
                </label>
                <label class="field">
                    <span><?= e(__('time.project')) ?></span>
                    <select name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['number'] . ' · ' . $p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted"><?= e(__('meetings.project_hint')) ?></small>
                    <?= error_for('project_id') ?>
                </label>
                <label class="field">
                    <span><?= e(__('meetings.date')) ?> *</span>
                    <div class="input-group">
                        <input type="date" name="date" value="<?= e($date) ?>" required>
                        <input type="time" name="time" value="<?= e($time) ?>" required>
                    </div>
                    <small class="muted"><?= e(__('meetings.closes_hint')) ?></small>
                    <?= error_for('date') ?>
                </label>
                <label class="field">
                    <span><?= e(__('cal.location')) ?></span>
                    <input name="location" value="<?= e($v('location')) ?>">
                </label>
            </div>
            <label class="field">
                <span><?= e(__('gk.agenda')) ?> *</span>
                <textarea name="agenda" rows="6" required><?= e($v('agenda')) ?></textarea>
                <?= error_for('agenda') ?>
            </label>
        </section>

        <section class="card form-card">
            <div class="card-title-row">
                <h3 class="card-title"><?= e(__('meetings.shareholders')) ?> *</h3>
                <label class="btn btn-sm btn-outline">
                    <?= icon('plus') ?><?= e(__('meetings.import')) ?>
                    <input type="file" accept=".xlsx,.csv,.txt" hidden data-import>
                </label>
            </div>
            <p class="muted small"><?= e(__('meetings.lines_hint')) ?></p>
            <textarea name="shareholders" rows="10" class="mono" placeholder="<?= e(__('meetings.lines_placeholder')) ?>"><?= e(old('shareholders', $lines)) ?></textarea>
            <p class="small" data-import-msg hidden></p>
            <?= error_for('shareholders') ?>
            <?php if ($m && $m['response_count']): ?>
                <p class="muted small"><?= e(__('meetings.lines_keep')) ?></p>
            <?php endif; ?>
        </section>
    </div>

    <div class="stack">
        <section class="card form-card">
            <h3 class="card-title"><?= e(__('meetings.proxy_doc')) ?></h3>
            <?php if (!empty($m['proxy_doc_file'])): ?>
                <div class="logo-preview">
                    <a href="/meetings/<?= $m['id'] ?>/doc" target="_blank" class="link"><?= e($m['proxy_doc_name']) ?> ↗</a>
                    <label class="check"><input type="checkbox" name="remove_doc" value="1"><span><?= e(__('meetings.remove_doc')) ?></span></label>
                </div>
            <?php endif; ?>
            <input type="file" name="proxy_doc" accept=".docx,.pdf">
            <?= error_for('proxy_doc') ?>
            <details class="small">
                <summary class="link"><?= e(__('meetings.fields_title')) ?></summary>
                <p class="muted"><?= e(__('meetings.fields_hint')) ?></p>
                <dl class="dl dl-compact">
                    <?php foreach (MeetingService::TEMPLATE_FIELDS as $f): ?>
                        <div class="dl-row"><dt class="mono">{<?= $f ?>}</dt><dd class="muted"><?= e(__('meetings.field_' . $f)) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </details>
        </section>

        <section class="card form-card">
            <h3 class="card-title"><?= e(__('meetings.default_proxy')) ?></h3>
            <p class="muted small"><?= e(__('meetings.default_proxy_hint')) ?></p>
            <label class="field">
                <span><?= e(__('gk.proxy_name')) ?></span>
                <input name="default_proxy_name" value="<?= e($v('default_proxy_name')) ?>">
                <?= error_for('default_proxy_name') ?>
            </label>
            <label class="field">
                <span><?= e(__('meetings.proxy_tc')) ?></span>
                <input name="default_proxy_tc" inputmode="numeric" maxlength="11" value="<?= e($v('default_proxy_tc')) ?>">
            </label>
            <label class="field">
                <span><?= e(__('meetings.proxy_address')) ?></span>
                <textarea name="default_proxy_address" rows="2"><?= e($v('default_proxy_address')) ?></textarea>
            </label>
        </section>

        <div class="form-actions form-actions-tight">
            <a href="<?= $m ? '/meetings/' . $m['id'] : '/meetings' ?>" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
            <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
        </div>
    </div>
</form>

<script>
(() => {
    const f = document.getElementById('meeting-form');
    // Proje listesi seçilen müvekkile göre süzülür
    const client = f.client_id;
    const filter = () => {
        if (!client) return;
        [...f.project_id.options].forEach(o => { if (o.value) o.hidden = o.dataset.client !== client.value; });
        if (f.project_id.selectedOptions[0]?.hidden) f.project_id.value = '';
    };
    client?.addEventListener('change', filter);
    filter();

    // Excel/CSV içe aktarma: satırlar mevcut listenin sonuna eklenir
    const input = f.querySelector('[data-import]');
    const msg = f.querySelector('[data-import-msg]');
    input.addEventListener('change', async () => {
        if (!input.files[0]) return;
        const body = new FormData();
        body.append('file', input.files[0]);
        body.append('_csrf', document.querySelector('meta[name=csrf-token]').content);
        const r = await fetch('/meetings/import', { method: 'POST', body }).then(r => r.json()).catch(() => ({ error: '?' }));
        msg.hidden = false;
        msg.className = 'small ' + (r.error ? 'text-danger' : 'text-success');
        if (r.error) { msg.textContent = r.error; } else {
            const ta = f.shareholders;
            ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + r.lines.join('\n');
            msg.textContent = <?= json_encode(__('meetings.imported')) ?>.replace(':count', r.lines.length);
        }
        input.value = '';
    });
})();
</script>
