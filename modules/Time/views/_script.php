<script>
(() => {
    // Süre önizleme: sunucudaki TimeService::parseDuration ile aynı kurallar
    const parse = raw => {
        const s = raw.trim().toLowerCase();
        let m;
        if (!s) return null;
        if ((m = s.match(/^(\d{1,2}):([0-5]\d)$/))) return +m[1] * 60 + +m[2];
        if (/^\d+[.,]\d+$/.test(s)) return Math.round(parseFloat(s.replace(',', '.')) * 60);
        if (/^\d+$/.test(s)) return +s <= 24 ? +s * 60 : +s;
        if ((m = s.match(/^(?:(\d+)\s*(?:s|sa|saat|h)\s*)?(?:(\d+)\s*(?:d|dk|dakika|m|min))?$/)) && (m[1] || m[2])) return (+m[1] || 0) * 60 + (+m[2] || 0);
        return null;
    };
    const fmt = min => `${Math.floor(min / 60)}:${String(min % 60).padStart(2, '0')}`;
    const hint = <?= json_encode(__('time.duration_hint')) ?>;
    const invalid = <?= json_encode(__('time.invalid_duration')) ?>;
    const rounded = <?= json_encode(__('time.rounded_to')) ?>;

    document.querySelectorAll('[data-duration]').forEach(input => {
        const preview = input.parentElement.querySelector('[data-duration-preview]');
        const update = () => {
            const v = parse(input.value);
            if (!input.value.trim()) { preview.textContent = hint; preview.classList.remove('text-warn'); return; }
            if (v === null || v <= 0) { preview.textContent = invalid; preview.classList.add('text-warn'); return; }
            const r = Math.ceil(v / 6) * 6;
            preview.textContent = rounded.replace(':time', fmt(r)).replace(':hours', (r / 60).toFixed(1));
            preview.classList.remove('text-warn');
        };
        input.addEventListener('input', update);
        update();
    });

    // Faturalanmaz projede "faturalanabilir" kutusu gizlenir
    document.querySelectorAll('.time-form').forEach(form => {
        const select = form.querySelector('[data-project]');
        const box = form.querySelector('[data-billable]');
        const sync = () => box.hidden = select.selectedOptions[0]?.dataset.billing === 'non_billable';
        select.addEventListener('change', sync);
        sync();
    });

    // Çalışan sayaç (sunucu saatine göre)
    const clock = document.querySelector('.timer-clock');
    if (clock) {
        const offset = Date.now() / 1000 - +clock.dataset.now;
        const tick = () => {
            const s = Math.max(0, Math.floor(Date.now() / 1000 - offset - +clock.dataset.started));
            clock.textContent = `${Math.floor(s / 3600)}:${String(Math.floor(s / 60) % 60).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
        };
        tick();
        setInterval(tick, 1000);
    }
})();
</script>
