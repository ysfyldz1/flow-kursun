/*
 * Dosya Transferi: dosya ve klasörleri tarayıcıdan doğrudan M365'e yükler (Graph upload session).
 * Bağlantı koparsa Graph'a nerede kaldığı sorulup oradan devam edilir. [data-copy] düğmeleri de buradadır.
 */
(() => {
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        navigator.clipboard.writeText(document.querySelector(btn.dataset.copy).value);
        const previous = btn.textContent;
        btn.textContent = btn.dataset.copied || T.copied;
        setTimeout(() => { btn.textContent = previous; }, 1600);
    });

    const root = document.getElementById('tf-new');
    const T = root ? JSON.parse(root.dataset.text) : {};
    if (!root) return;

    const CHUNK = 10 * 1024 * 1024;   // 320 KiB'ın katı olmalı (Graph şartı)
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const $ = id => document.getElementById(id);
    const fill = (text, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replace(':' + k, v), text);
    const fmt = b => {
        const u = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (b >= 1024 && i < 3) { b /= 1024; i++; }
        return (i ? b.toFixed(b < 10 ? 1 : 0).replace('.', ',') : b) + ' ' + u[i];
    };

    let items = [];   // {file, path}
    let busy = false;
    let skipped = 0;

    // Klasörden gelen dosyalar listede tek satırdır
    const groupKey = it => it.path.includes('/') ? 'd:' + it.path.split('/')[0] : 'f:' + it.path;

    const render = () => {
        const list = $('tf-list');
        list.querySelectorAll('.tf-row').forEach(r => r.remove());
        $('tf-empty').hidden = items.length > 0;
        const groups = new Map();
        items.forEach(it => {
            const k = groupKey(it);
            if (!groups.has(k)) groups.set(k, { name: k.slice(2), folder: k[0] === 'd', count: 0, size: 0 });
            groups.get(k).count++;
            groups.get(k).size += it.file.size;
        });
        groups.forEach((g, key) => {
            const row = document.createElement('div');
            row.className = 'tf-row';
            row.dataset.group = key;
            const name = document.createElement('span');
            name.className = 'tf-path';
            name.textContent = g.folder ? `${g.name}/ (${g.count})` : g.name;
            const size = document.createElement('small');
            size.className = 'muted';
            size.textContent = fmt(g.size);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'filepick-clear';
            remove.setAttribute('aria-label', T.remove);
            remove.textContent = '✕';
            remove.disabled = busy;
            remove.addEventListener('click', () => { items = items.filter(it => groupKey(it) !== key); render(); });
            row.append(name, size, remove);
            list.appendChild(row);
        });
        const total = items.reduce((s, it) => s + it.file.size, 0);
        $('tf-summary').textContent = items.length ? `${fill(T.files, { count: items.length })} · ${fmt(total)}` : '';
        $('tf-send').disabled = busy || !items.length;
    };

    const add = (file, path) => {
        if (!file.size) { skipped++; return; }
        path = (path || file.name).replace(/^\/+/, '');
        if (!items.some(it => it.path === path)) items.push({ file, path });
    };
    const afterAdd = () => {
        render();
        showError(skipped ? fill(T.skipped, { count: skipped }) : '');
        skipped = 0;
    };
    const showError = msg => { $('tf-error').textContent = msg; $('tf-error').hidden = !msg; };

    $('tf-files').addEventListener('change', function () { [...this.files].forEach(f => add(f)); this.value = ''; afterAdd(); });
    $('tf-folder').addEventListener('change', function () { [...this.files].forEach(f => add(f, f.webkitRelativePath)); this.value = ''; afterAdd(); });

    // Sürükle-bırak: alt klasör yapısı korunur
    const readEntry = (entry, prefix) => new Promise(resolve => {
        if (entry.isFile) {
            entry.file(f => { add(f, prefix + f.name); resolve(); }, () => resolve());
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const all = [];
            const next = () => reader.readEntries(batch => {
                if (!batch.length) {
                    Promise.all(all.map(en => readEntry(en, prefix + entry.name + '/'))).then(resolve);
                    return;
                }
                all.push(...batch);
                next();
            }, () => resolve());
            next();
        } else {
            resolve();
        }
    });
    const drop = $('tf-drop');
    ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); if (!busy) drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', async e => {
        if (busy) return;
        const entries = [...e.dataTransfer.items].map(i => i.webkitGetAsEntry && i.webkitGetAsEntry()).filter(Boolean);
        if (entries.length) await Promise.all(entries.map(en => readEntry(en, '')));
        else [...e.dataTransfer.files].forEach(f => add(f));
        afterAdd();
    });

    const api = async (url, data) => {
        const body = new FormData();
        body.append('_csrf', CSRF);
        Object.entries(data).forEach(([k, v]) => body.append(k, v));
        const res = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
        const json = await res.json().catch(() => ({ error: T.badResponse }));
        if (!res.ok || json.error) throw new Error(json.error || res.statusText);
        return json;
    };

    const uploadFile = async (url, file, onProgress) => {
        let offset = 0;
        let attempts = 0;
        for (;;) {
            const end = Math.min(offset + CHUNK, file.size);
            let res = null;
            try {
                // Önceden yetkilendirilmiş adres: Authorization başlığı gönderilmez
                res = await fetch(url, {
                    method: 'PUT',
                    headers: { 'Content-Range': `bytes ${offset}-${end - 1}/${file.size}` },
                    body: file.slice(offset, end),
                });
            } catch (e) { /* ağ hatası: aşağıda yeniden denenir */ }

            if (res && (res.status === 200 || res.status === 201)) {
                onProgress(file.size);
                return;
            }
            if (res && res.status === 202) {
                const data = await res.json();
                offset = parseInt(data.nextExpectedRanges[0], 10);
                attempts = 0;
                onProgress(offset);
                continue;
            }
            if (++attempts > 5) throw new Error(T.retryFailed);
            await new Promise(r => setTimeout(r, 1000 * attempts * attempts));
            try {
                const status = await (await fetch(url)).json();
                if (status.nextExpectedRanges?.length) offset = parseInt(status.nextExpectedRanges[0], 10);
            } catch (e) { /* bağlantı yoksa aynı parça yeniden denenir */ }
        }
    };

    addEventListener('beforeunload', e => { if (busy) { e.preventDefault(); e.returnValue = ''; } });

    const setProgress = (done, total, label) => {
        const pct = total ? Math.floor(done * 100 / total) : 100;
        $('tf-bar').style.width = pct + '%';
        $('tf-pct').textContent = pct + '%';
        if (label) $('tf-label').textContent = label;
    };

    $('tf-send').addEventListener('click', async () => {
        if (busy || !items.length) return;
        busy = true;
        render();
        showError('');
        $('tf-progress').hidden = false;
        const total = items.reduce((s, it) => s + it.file.size, 0);
        let done = 0;
        try {
            setProgress(0, total, T.preparing);
            const created = await api('/transfers', {
                title: $('tf-title').value,
                message: $('tf-message').value,
                recipient_email: $('tf-email').value,
                password: $('tf-password').value,
                expiry: $('tf-expiry').value,
                locale: $('tf-locale').value,
                notify: $('tf-notify').checked ? '1' : '',
                files: JSON.stringify(items.map(it => ({ path: it.path, size: it.file.size }))),
            });
            for (const [i, it] of items.entries()) {
                const fileId = created.file_ids[i];
                const label = fill(T.uploading, { current: i + 1, total: items.length, name: it.path.split('/').pop() });
                const { upload_url } = await api(`/transfers/${created.id}/session`, { file_id: fileId });
                await uploadFile(upload_url, it.file, sent => setProgress(done + sent, total, label));
                await api(`/transfers/${created.id}/file-done`, { file_id: fileId });
                done += it.file.size;
            }
            setProgress(total, total, T.finishing);
            const fin = await api(`/transfers/${created.id}/finalize`, {});
            busy = false;
            $('tf-form').hidden = true;
            $('tf-done').hidden = false;
            $('tf-url').value = fin.url;
            $('tf-detail').href = '/transfers/' + fin.id;
            $('tf-done-note').textContent = fin.emailed ? T.emailed : ($('tf-email').value.trim() ? T.emailFailed : T.share);
        } catch (e) {
            busy = false;
            render();
            showError(`${e.message} ${T.halfDone}`);
        }
    });

    render();
})();
