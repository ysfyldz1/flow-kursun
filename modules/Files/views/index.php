<?php
// Dosyalarım: kişinin kendi OneDrive'ı; seçilen dosyalar bir projenin Belgeler klasörüne kopyalanır.
$text = [
    'search' => __('onedrive.search'), 'searchBtn' => __('app.search'), 'root' => __('onedrive.root'),
    'results' => __('onedrive.results'), 'items' => __('docs.folder_items'), 'empty' => __('onedrive.empty'),
    'noResults' => __('onedrive.no_results'), 'tooLarge' => __('onedrive.too_large_short'), 'connect' => __('ms.connect'),
    'shortcut' => __('onedrive.shortcut'),
];
?>
<div class="page-head">
    <div>
        <h1><?= e(__('onedrive.title')) ?></h1>
        <p class="muted"><?= e(__('onedrive.subtitle')) ?></p>
    </div>
</div>

<?php if (!$connected): ?>
    <div class="card card-body stack">
        <p><?= e(__('onedrive.not_connected')) ?></p>
        <div><a href="/account" class="btn btn-primary"><?= e(__('ms.connect')) ?></a></div>
    </div>
<?php else: ?>
    <div class="grid grid-3-1">
        <section class="card od-panel" id="od"></section>
        <section class="card form-card od-send">
            <h3 class="card-title"><?= e(__('onedrive.send_title')) ?></h3>
            <p class="muted small" data-count><?= e(__('onedrive.pick_hint')) ?></p>
            <label class="field">
                <span><?= e(__('time.project')) ?></span>
                <select name="project_id">
                    <option value=""><?= e(__('time.choose_project')) ?></option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['number'] . ' · ' . $p['name'] . ' — ' . $p['client_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="small muted"><?= e(__('onedrive.copy_note')) ?></p>
            <p class="small" data-status></p>
            <div class="form-actions form-actions-tight">
                <button type="button" class="btn btn-primary" data-send disabled><?= e(__('onedrive.send')) ?></button>
            </div>
        </section>
    </div>

    <script src="<?= asset('onedrive.js') ?>"></script>
    <script>
    (() => {
        const t = <?= json_encode($text, JSON_UNESCAPED_UNICODE) ?>;
        const panel = document.querySelector('.od-send');
        const project = panel.querySelector('[name=project_id]');
        const send = panel.querySelector('[data-send]');
        const count = panel.querySelector('[data-count]');
        const status = panel.querySelector('[data-status]');
        const hint = count.textContent;
        const selectedText = <?= json_encode(__('emails.selected')) ?>;
        const sync = ids => {
            count.textContent = ids.length ? selectedText.replace(':count', ids.length) : hint;
            send.disabled = !ids.length || !project.value;
        };
        const browser = OneDriveBrowser(document.getElementById('od'), { text: t, onChange: sync });
        project.addEventListener('change', () => sync(browser.selected()));
        send.addEventListener('click', async () => {
            send.disabled = true;
            status.className = 'small muted';
            status.textContent = <?= json_encode(__('onedrive.copying')) ?>;
            try {
                const r = await OneDriveSend('/files/send', <?= json_encode(\App\Core\Csrf::token()) ?>, browser.selected(), { project_id: project.value });
                status.className = 'small text-success';
                status.innerHTML = '';
                status.append(r.message + (r.errors.length ? ' · ' + r.errors.join(' ') : '') + ' ');
                const a = document.createElement('a');
                a.href = r.project; a.className = 'link'; a.textContent = <?= json_encode(__('onedrive.open_project')) ?> + ' →';
                status.append(a);
                browser.reload();
            } catch (e) {
                status.className = 'small text-danger';
                status.textContent = e.message;
                send.disabled = false;
            }
        });
        browser.load();
    })();
    </script>
<?php endif; ?>
