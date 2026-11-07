// Dosya alanlarını "dosya seç ya da sürükle-bırak" kutusuna çevirir. Asıl <input type=file> kutunun üstünde
// görünmez durur; tıklama ve sürükle-bırak tarayıcının kendisiyle çalışır, form gönderimi değişmez.
// Metinler sayfadaki window.FILEPICK nesnesinden (lang) gelir. hidden ya da data-plain olan alanlara dokunulmaz.
(() => {
    const t = window.FILEPICK || { choose: 'Choose a file', drop: 'or drag it here', remove: 'Remove' };
    const size = b => b < 1024 * 1024 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1).replace('.', ',') + ' MB';
    const hint = accept => (accept || '').split(',').map(a => a.trim().replace(/^\./, '').replace(/^image\//, '').toUpperCase()).filter(Boolean).join(', ');

    const enhance = input => {
        if (input.hidden || input.dataset.plain !== undefined || input.closest('.filepick')) return;
        const box = document.createElement('div');
        box.className = 'filepick';
        box.innerHTML = `<span class="filepick-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg></span>
            <span class="filepick-text"><strong></strong><small></small></span>
            <button type="button" class="filepick-clear" aria-label="${t.remove}" hidden>✕</button>`;
        input.parentNode.insertBefore(box, input);
        box.appendChild(input);
        const title = box.querySelector('strong');
        const sub = box.querySelector('small');
        const clear = box.querySelector('.filepick-clear');

        const render = () => {
            const f = input.files[0];
            box.classList.toggle('has-file', !!f);
            clear.hidden = !f;
            title.textContent = f ? f.name : t.choose;
            sub.textContent = f ? size(f.size) : [t.drop, hint(input.accept)].filter(Boolean).join(' · ');
        };
        input.addEventListener('change', render);
        input.addEventListener('dragenter', () => box.classList.add('is-over'));
        ['dragleave', 'drop'].forEach(e => input.addEventListener(e, () => box.classList.remove('is-over')));
        clear.addEventListener('click', () => { input.value = ''; render(); });
        render();
    };

    document.querySelectorAll('input[type=file]').forEach(enhance);
})();
