/*
 * Kişinin OneDrive'ı için gezgin: klasörlerde gezinme, yol gösterici, arama, çoklu dosya seçimi.
 * Dosyalarım sayfası ve proje Belgeler'deki "OneDrive'dan ekle" penceresi birlikte kullanır.
 *   const b = OneDriveBrowser(el, { text: {...}, onChange: ids => … });  b.load(); b.selected(); b.reload();
 * Sunucu: GET /files/browse?folder=&q= → {items: [...], search}
 */
window.OneDriveBrowser = (root, { text, onChange = () => {} }) => {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    root.innerHTML = `
        <form class="modal-search" data-od-search>
            <input type="search" name="q" placeholder="${esc(text.search)}" autocomplete="off">
            <button class="btn btn-outline">${esc(text.searchBtn)}</button>
        </form>
        <nav class="docs-crumbs od-crumbs" data-od-crumbs></nav>
        <div class="od-list" data-od-list></div>`;
    const list = root.querySelector('[data-od-list]');
    const crumbs = root.querySelector('[data-od-crumbs]');
    const form = root.querySelector('[data-od-search]');
    // Yol gösterici tarayıcıda tutulur: [[id, ad], ...]; kısayolun içinde gezinirken de doğru kalır
    let trail = [];
    const folder = () => trail.length ? trail[trail.length - 1][0] : '';

    const selected = () => [...list.querySelectorAll('input:checked')].map(i => i.value);
    const changed = () => onChange(selected());

    const load = async (q = '') => {
        list.innerHTML = '<div class="empty">…</div>';
        const r = await fetch('/files/browse?' + new URLSearchParams({ folder: q ? '' : folder(), q }), { credentials: 'same-origin' });
        const json = await r.json().catch(() => ({}));
        if (!r.ok) {
            crumbs.innerHTML = '';
            list.innerHTML = '<div class="empty text-warn">' + esc(json.error || r.statusText)
                + (json.connect ? ` <a class="link" href="${json.connect}">${esc(text.connect)} →</a>` : '') + '</div>';
            changed();
            return;
        }
        const steps = json.search ? [] : trail;
        crumbs.innerHTML = `<button type="button" class="link" data-od-crumb="0">⌂ ${esc(text.root)}</button>`
            + (json.search ? ` <span class="muted">›</span> <strong>${esc(text.results)}</strong>` : '')
            + steps.map(([, name], i) => ` <span class="muted">›</span> ` + (i === steps.length - 1
                ? `<strong>${esc(name)}</strong>` : `<button type="button" class="link" data-od-crumb="${i + 1}">${esc(name)}</button>`)).join('');
        list.innerHTML = json.items.length ? json.items.map(i => i.folder
            ? `<button type="button" class="pick-row od-folder" data-od-folder="${esc(i.id)}" data-name="${esc(i.name)}">
                   <span class="od-icon" ${i.shortcut ? `title="${esc(text.shortcut || '')}"` : ''}>${i.shortcut ? '🔗' : '📁'}</span>
                   <span class="cell-main"><strong>${esc(i.name)}</strong><span>${esc(text.items.replace(':count', i.count ?? 0))}</span></span>
                   <span class="muted">›</span>
               </button>`
            : `<label class="pick-row${i.tooLarge ? ' is-saved' : ''}" ${i.tooLarge ? `title="${esc(text.tooLarge)}"` : ''}>
                   <input type="checkbox" value="${esc(i.id)}" ${i.tooLarge ? 'disabled' : ''}>
                   <span class="cell-main"><strong>${esc(i.name)}</strong><span>${esc(i.size)} · ${esc(i.modified)}</span></span>
               </label>`).join('') : '<div class="empty">' + esc(json.search ? text.noResults : text.empty) + '</div>';
        changed();
    };

    root.addEventListener('click', e => {
        const f = e.target.closest('[data-od-folder]');
        const c = e.target.closest('[data-od-crumb]');
        if (!f && !c) return;
        // Aramadan bir klasöre girilirse yol o klasörden başlar
        if (f) trail = form.q.value.trim() ? [[f.dataset.odFolder, f.dataset.name]] : [...trail, [f.dataset.odFolder, f.dataset.name]];
        else trail = trail.slice(0, +c.dataset.odCrumb);
        form.q.value = '';
        load();
    });
    list.addEventListener('change', changed);
    form.addEventListener('submit', e => { e.preventDefault(); load(form.q.value.trim()); });

    return { load, selected, reload: () => load(form.q.value.trim()) };
};

/* Seçilenleri gönderir: POST url {ids[], ...extra}; {copied, errors, message} döner */
window.OneDriveSend = async (url, csrf, ids, extra = {}) => {
    const fd = new FormData();
    fd.append('_csrf', csrf);
    ids.forEach(id => fd.append('ids[]', id));
    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await r.json().catch(() => ({}));
    if (!r.ok && !json.copied) throw new Error(json.error || (json.errors || []).join(' ') || r.statusText);
    return json;
};
