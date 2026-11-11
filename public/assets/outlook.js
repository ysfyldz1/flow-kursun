/*
 * Flow Outlook paneli. Okuma ekranında açık e-postayı bir projeye kaydeder; yazma ekranında
 * "gönderince kaydet" için e-postaya x-flow-marker başlığı koyar (saatlik iş Gönderilmiş Öğeler'de bulur).
 * Kimlik: ilk kullanımda Office penceresinde Flow'a giriş, Flow panele kişiye özel anahtar verir.
 */
(() => {
    const T = window.FLOW.t;
    const BASE = location.origin;
    const HEADER = 'x-flow-marker';
    const app = document.getElementById('app');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = (s, v) => String(s).replace(/:(\w+)/g, (m, k) => (k in v ? v[k] : m));

    let me = null;
    let item = null;
    let compose = false;
    let state = { projectId: null, projects: {} };

    // --- Anahtar: Outlook'un kişiye özel ayarlarında (cihazlar arası), yoksa yerel depoda ---------------
    const token = {
        get() {
            try { return Office.context.roamingSettings.get('flowToken') || localStorage.getItem('flowToken'); } catch (e) { return null; }
        },
        set(v) {
            try { localStorage.setItem('flowToken', v); } catch (e) {}
            try { Office.context.roamingSettings.set('flowToken', v); Office.context.roamingSettings.saveAsync(); } catch (e) {}
        },
        clear() {
            try { localStorage.removeItem('flowToken'); } catch (e) {}
            try { Office.context.roamingSettings.remove('flowToken'); Office.context.roamingSettings.saveAsync(); } catch (e) {}
        },
    };

    async function api(path, body) {
        const res = await fetch(BASE + '/outlook/api/' + path, {
            method: body === undefined ? 'GET' : 'POST',
            headers: { 'X-Flow-Token': token.get() || '', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
            token.clear();
            renderConnect();
            throw new Error('unauthorized');
        }
        if (!res.ok) {
            const err = new Error(data.error || T.error);
            err.data = data;
            throw err;
        }
        return data;
    }

    // Office'in geri çağırmalı fonksiyonlarını await ile kullanmak için
    const call = (fn, ...args) => new Promise((resolve, reject) =>
        fn(...args, r => (r.status === Office.AsyncResultStatus.Succeeded ? resolve(r.value) : reject(r.error))));

    // --- Giriş ------------------------------------------------------------------------------------------
    function renderConnect(message) {
        app.innerHTML = `
            <h2 style="margin-top:0">Flow</h2>
            <p>${esc(T.connect_title)}</p>
            <p class="muted small">${esc(T.connect_hint)}</p>
            ${message ? `<div class="banner error">${esc(message)}</div>` : ''}
            <div class="actions"><button class="btn btn-primary" id="connect">${esc(T.connect)}</button></div>`;
        document.getElementById('connect').onclick = connect;
    }

    function connect() {
        Office.context.ui.displayDialogAsync(BASE + '/outlook/connect', { height: 70, width: 40, promptBeforeOpen: false }, r => {
            if (r.status !== Office.AsyncResultStatus.Succeeded) {
                renderConnect(T.connect_failed + ' (' + (r.error && r.error.message) + ')');
                return;
            }
            const dialog = r.value;
            dialog.addEventHandler(Office.EventType.DialogMessageReceived, arg => {
                try {
                    const msg = JSON.parse(arg.message);
                    if (msg.token) token.set(msg.token);
                } catch (e) {}
                dialog.close();
                start();
            });
        });
    }

    // --- Başlangıç --------------------------------------------------------------------------------------
    async function start() {
        app.innerHTML = `<p class="muted">${esc(T.loading)}</p>`;
        if (!token.get()) return renderConnect();
        try {
            me = await api('me');
        } catch (e) {
            if (e.message !== 'unauthorized') renderConnect(e.message);
            return;
        }
        if (!me.connected) return renderConnect(T.not_connected_ms);
        item = Office.context.mailbox.item;
        compose = typeof item.subject !== 'string';   // yazma ekranında subject bir nesne
        try {
            compose ? await startCompose() : await startRead();
        } catch (e) {
            if (e.message !== 'unauthorized') app.innerHTML = `<div class="banner error">${esc(e.message || T.error)}</div>`;
        }
    }

    // --- Okuma ekranı -----------------------------------------------------------------------------------
    function readInfo() {
        const addr = a => (a && a.emailAddress) || '';
        const attachments = (item.attachments || []).filter(a => !a.isInline && a.attachmentType === 'file');
        return {
            itemId: Office.context.mailbox.convertToRestId(item.itemId, Office.MailboxEnums.RestVersion.v2_0),
            internetMessageId: item.internetMessageId || '',
            conversationId: item.conversationId || '',
            subject: item.subject || '',
            addresses: [addr(item.from), ...(item.to || []).map(addr), ...(item.cc || []).map(addr)].filter(Boolean),
            attachments,
        };
    }

    async function startRead() {
        const info = readInfo();
        const ctx = await api('context', info);
        renderForm(info, ctx);
    }

    // --- Yazma ekranı -----------------------------------------------------------------------------------
    async function composeInfo() {
        const list = async field => (await call(field.getAsync.bind(field))).map(r => r.emailAddress);
        return {
            subject: await call(item.subject.getAsync.bind(item.subject)),
            conversationId: item.conversationId || '',
            addresses: [...await list(item.to), ...await list(item.cc)].filter(Boolean),
        };
    }

    async function startCompose() {
        if (!Office.context.requirements.isSetSupported('Mailbox', '1.8')) {
            app.innerHTML = `<div class="banner info">${esc(T.compose_unsupported)}</div>`;
            return;
        }
        const headers = await call(item.internetHeaders.getAsync.bind(item.internetHeaders), [HEADER]).catch(() => ({}));
        const props = await call(item.loadCustomPropertiesAsync.bind(item));
        if (headers[HEADER] && props.get('flowProject')) {
            return renderQueued(props.get('flowProject'), headers[HEADER], props);
        }
        const info = await composeInfo();
        const ctx = await api('context', info);
        renderForm(info, ctx);
    }

    function renderQueued(label, marker, props) {
        app.innerHTML = `
            <div class="banner">${esc(fmt(T.queued, { project: label }))}</div>
            <button class="link" id="unqueue">${esc(T.unqueue)}</button>`;
        document.getElementById('unqueue').onclick = async () => {
            await api('unqueue', { marker });
            await call(item.internetHeaders.removeAsync.bind(item.internetHeaders), [HEADER]).catch(() => {});
            props.remove('flowProject');
            await call(props.saveAsync.bind(props)).catch(() => {});
            start();
        };
    }

    // --- Ortak form ---------------------------------------------------------------------------------------
    const projectHtml = p => `
        <label class="project ${state.projectId === p.id ? 'selected' : ''}" data-id="${p.id}">
            <input type="radio" name="project" value="${p.id}" ${state.projectId === p.id ? 'checked' : ''}>
            <div><strong>${esc(p.name)}</strong><span>${esc(p.number)} · ${esc(p.client)}</span></div>
        </label>`;

    function renderForm(info, ctx) {
        state = { projectId: null, projects: {} };
        [...ctx.saved, ...ctx.suggested, ...ctx.recent].forEach(p => (state.projects[p.id] = p));
        if (ctx.thread && ctx.suggested[0]) state.projectId = ctx.suggested[0].id;   // aynı yazışma zinciri: önceki proje seçili gelir
        else if (ctx.suggested.length === 1) state.projectId = ctx.suggested[0].id;

        const attachments = info.attachments || [];
        app.innerHTML = `
            ${ctx.saved.length ? `<div class="banner">${esc(T.saved_here)} ${ctx.saved.map(p => `<strong>${esc(p.number)}</strong> ${esc(p.name)}`).join(', ')}</div>` : ''}
            ${compose ? `<p class="muted small" style="margin-top:0">${esc(T.compose_hint)}</p>` : ''}
            <input type="search" id="q" placeholder="${esc(T.search)}" autocomplete="off">
            <div class="field-hint">${esc(T.search_hint)}</div>
            <div id="results"></div>
            <div id="lists">
                ${ctx.suggested.length ? `<h2>${esc(T.suggested)}</h2><div class="projects">${ctx.suggested.map(projectHtml).join('')}</div>` : ''}
                ${ctx.thread ? `<div class="field-hint">${esc(T.thread_hint)}</div>` : ''}
                ${ctx.recent.length ? `<h2>${esc(T.recent)}</h2><div class="projects">${ctx.recent.map(projectHtml).join('')}</div>` : ''}
            </div>

            <h2>${esc(T.note)}</h2>
            <textarea id="note" maxlength="500" placeholder="${esc(T.note_hint)}"></textarea>

            ${attachments.length ? `
                <h2>${esc(T.attachments)}</h2>
                <div class="field-hint" style="margin-bottom:4px">${esc(T.attachments_hint)}</div>
                ${attachments.map((a, i) => `<label class="check"><input type="checkbox" class="att" value="${i}"><span>${esc(a.name)} <span class="muted small">${Math.max(1, Math.round(a.size / 1024))} KB</span></span></label>`).join('')}` : ''}

            ${me.canTime ? `
                <label class="check" style="margin-top:14px"><input type="checkbox" id="addTime"><span><strong>${esc(T.add_time)}</strong></span></label>
                <div id="timeFields" hidden>
                    <div class="row"><span>${esc(T.duration)}</span><input type="text" id="duration" inputmode="decimal" placeholder="${window.FLOW.decimal ? (window.FLOW.locale === 'tr' ? '0,2' : '0.2') : '0:15'}"></div>
                    <div class="field-hint" style="margin-left:98px">${esc(T.duration_hint)}</div>
                    <div id="durationError" class="error-text" style="margin-left:98px" hidden></div>
                    <div class="row"><span>${esc(T.time_desc)}</span><input type="text" id="timeDesc" maxlength="500"></div>
                    <label class="check" id="billableRow"><input type="checkbox" id="billable" checked><span>${esc(T.billable)}</span></label>
                </div>` : ''}

            <div id="formError" class="banner error" hidden></div>
            <div class="actions"><button class="btn btn-primary" id="save" disabled>${esc(compose ? T.queue : T.save)}</button></div>
            <div class="user"><span>${esc(me.name)}</span><button class="link" id="logout">${esc(T.disconnect)}</button></div>`;

        const saveBtn = document.getElementById('save');
        const select = id => {
            state.projectId = id;
            app.querySelectorAll('.project').forEach(el => el.classList.toggle('selected', +el.dataset.id === id));
            app.querySelectorAll('input[name=project]').forEach(el => (el.checked = +el.value === id));
            saveBtn.disabled = !id;
            const billableRow = document.getElementById('billableRow');
            if (billableRow) billableRow.hidden = state.projects[id] && !state.projects[id].billable;
        };
        app.addEventListener('change', e => { if (e.target.name === 'project') select(+e.target.value); });
        select(state.projectId);

        // Arama: 2+ harf, 300 ms bekleyerek
        let timer;
        document.getElementById('q').addEventListener('input', e => {
            clearTimeout(timer);
            const q = e.target.value.trim();
            const results = document.getElementById('results');
            const lists = document.getElementById('lists');
            if (q.length < 2) { results.innerHTML = ''; lists.hidden = false; return; }
            timer = setTimeout(async () => {
                const { projects } = await api('projects?q=' + encodeURIComponent(q));
                projects.forEach(p => (state.projects[p.id] = p));
                lists.hidden = true;
                results.innerHTML = projects.length
                    ? `<h2>${esc(T.pick_project)}</h2><div class="projects">${projects.map(projectHtml).join('')}</div>`
                    : `<p class="muted small">${esc(T.no_results)}</p>`;
            }, 300);
        });

        const addTime = document.getElementById('addTime');
        if (addTime) {
            const desc = document.getElementById('timeDesc');
            desc.placeholder = (info.subject || '').replace(/^\s*(re|fw|fwd|ynt|ilt)\s*:\s*/i, '');   // boş bırakılırsa sunucu konudan doldurur
            addTime.onchange = () => {
                document.getElementById('timeFields').hidden = !addTime.checked;
                if (addTime.checked) document.getElementById('duration').focus();
            };
        }

        document.getElementById('logout').onclick = async () => {
            await api('logout', {}).catch(() => {});
            token.clear();
            renderConnect();
        };

        saveBtn.onclick = () => submit(info, attachments);
    }

    async function submit(info, attachments) {
        const btn = document.getElementById('save');
        const formError = document.getElementById('formError');
        const durationError = document.getElementById('durationError');
        formError.hidden = true;
        if (durationError) durationError.hidden = true;

        const addTime = document.getElementById('addTime');
        const time = addTime && addTime.checked ? {
            duration: document.getElementById('duration').value.trim(),
            description: document.getElementById('timeDesc').value.trim(),
            billable: document.getElementById('billable').checked,
        } : null;
        const payload = {
            projectId: state.projectId,
            subject: info.subject,
            addresses: info.addresses,
            note: document.getElementById('note').value.trim(),
            time,
        };

        btn.disabled = true;
        btn.textContent = T.saving;
        try {
            if (compose) {
                const marker = [...crypto.getRandomValues(new Uint8Array(16))].map(b => b.toString(16).padStart(2, '0')).join('');
                await api('queue', { ...payload, marker });
                await call(item.internetHeaders.setAsync.bind(item.internetHeaders), { [HEADER]: marker });
                const props = await call(item.loadCustomPropertiesAsync.bind(item));
                const p = state.projects[state.projectId];
                props.set('flowProject', p.number + ' · ' + p.name);
                await call(props.saveAsync.bind(props));
                renderQueued(p.number + ' · ' + p.name, marker, props);
                return;
            }
            const names = [...document.querySelectorAll('.att:checked')].map(el => attachments[+el.value].name);
            const r = await api('save', { ...payload, itemId: info.itemId, attachments: names });
            renderDone(r);
        } catch (e) {
            if (e.message === 'unauthorized') return;
            btn.disabled = false;
            btn.textContent = compose ? T.queue : T.save;
            const errs = e.data && e.data.errors;
            if (errs && errs.duration && durationError) {
                durationError.textContent = errs.duration;
                durationError.hidden = false;
            } else {
                formError.textContent = errs ? Object.values(errs).join(' ') : (e.message || T.error);
                formError.hidden = false;
            }
        }
    }

    function renderDone(r) {
        const p = r.project;
        app.innerHTML = `
            <div class="done">
                <div class="tick">✓</div>
                <p><strong>${esc(r.saved ? fmt(T.saved_ok, { project: p.number + ' · ' + p.name }) : fmt(T.already_saved, { project: p.number + ' · ' + p.name }))}</strong></p>
                ${r.attachments && r.attachments.length ? `<p class="muted small">${esc(fmt(T.attachments_saved, { count: r.attachments.length }))}</p>` : ''}
                ${r.time_entry_id ? `<p class="muted small">${esc(T.time_saved)}</p>` : ''}
            </div>
            <div class="actions" style="display:flex;flex-direction:column;gap:8px">
                <a class="btn" href="${BASE}/projects/${p.id}" target="_blank" rel="noopener">${esc(T.open_project)}</a>
                <button class="link" id="again">${esc(T.save_another)}</button>
            </div>`;
        document.getElementById('again').onclick = () => start();
    }

    Office.onReady(() => start());
})();
