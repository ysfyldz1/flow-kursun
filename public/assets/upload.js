/*
 * Belgeleri tarayıcıdan doğrudan M365'e yükler (Graph upload session); dosya sunucudan geçmez.
 * <div data-upload="/projects/5/documents/upload" data-complete="(isteğe bağlı)" data-csrf="..."> içinde
 * bir <input type=file multiple> ve .upload-status bulunur. Bitince "uploaded" olayı yayınlanır.
 */
(() => {
    const CHUNK = 10 * 1024 * 1024;   // 320 KiB'ın katı olmalı (Graph şartı)

    const post = async (url, csrf, data) => {
        const body = new FormData();
        body.append('_csrf', csrf);
        Object.entries(data).forEach(([k, v]) => body.append(k, v));
        const res = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.error || res.statusText);
        return json;
    };

    const uploadFile = async (zone, file, onProgress) => {
        const { uploadUrl } = await post(zone.dataset.upload, zone.dataset.csrf, { name: file.name, size: file.size });
        let item = null;
        for (let start = 0; start < file.size; start += CHUNK) {
            const end = Math.min(start + CHUNK, file.size);
            // Önceden yetkilendirilmiş adres: Authorization başlığı gönderilmez
            const res = await fetch(uploadUrl, {
                method: 'PUT',
                headers: { 'Content-Range': `bytes ${start}-${end - 1}/${file.size}` },
                body: file.slice(start, end),
            });
            if (!res.ok && res.status !== 202) throw new Error(`HTTP ${res.status}`);
            onProgress(end / file.size);
            if (res.status === 200 || res.status === 201) item = await res.json();
        }
        if (zone.dataset.complete && item) {
            await post(zone.dataset.complete, zone.dataset.csrf, { item_id: item.id });
        }
    };

    const run = async (zone, files) => {
        const status = zone.querySelector('.upload-status');
        let failed = 0;
        for (const [i, file] of [...files].entries()) {
            const label = `${i + 1}/${files.length} · ${file.name}`;
            if (file.size === 0) {
                status.textContent = `${label} — ${zone.dataset.msgEmpty}`;
                failed++;
                continue;
            }
            try {
                await uploadFile(zone, file, p => { status.textContent = `${label} · %${Math.round(p * 100)}`; });
            } catch (e) {
                failed++;
                status.textContent = `${label} — ${e.message}`;
                await new Promise(r => setTimeout(r, 2500));
            }
        }
        if (!failed) status.textContent = zone.dataset.msgDone;
        zone.classList.remove('is-busy');
        zone.dispatchEvent(new CustomEvent('uploaded', { bubbles: true }));
    };

    document.addEventListener('change', e => {
        const zone = e.target.closest('[data-upload]');
        if (!zone || e.target.type !== 'file' || !e.target.files.length) return;
        zone.classList.add('is-busy');
        run(zone, e.target.files).finally(() => { e.target.value = ''; });
    });

    ['dragover', 'dragleave', 'drop'].forEach(type => document.addEventListener(type, e => {
        const zone = e.target.closest?.('[data-upload]');
        if (!zone) return;
        e.preventDefault();
        zone.classList.toggle('is-over', type === 'dragover');
        if (type === 'drop' && e.dataTransfer.files.length) {
            zone.classList.add('is-busy');
            run(zone, e.dataTransfer.files);
        }
    }));
})();
