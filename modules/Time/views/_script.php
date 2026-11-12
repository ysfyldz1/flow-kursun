<script>
(() => {
    // Süre önizleme: sunucudaki TimeService::parseDuration ile aynı kurallar
    const parse = raw => {
        const s = raw.trim().toLowerCase();
        let m;
        if (!s) return null;
        if ((m = s.match(/^(\d{1,2}):([0-5]\d)$/))) return +m[1] * 60 + +m[2];
        if (/^\d*[.,]\d+$/.test(s)) return Math.round(parseFloat(s.replace(',', '.')) * 60);
        if (/^\d+$/.test(s)) return +s <= 24 ? +s * 60 : +s;
        if ((m = s.match(/^(?:(\d+)\s*(?:s|sa|saat|h)\s*)?(?:(\d+)\s*(?:d|dk|dakika|m|min))?$/)) && (m[1] || m[2])) return (+m[1] || 0) * 60 + (+m[2] || 0);
        return null;
    };
    const fmt = min => `${Math.floor(min / 60)}:${String(min % 60).padStart(2, '0')}`;
    const hint = <?= json_encode(__('time.duration_hint')) ?>;
    const invalid = <?= json_encode(__('time.invalid_duration')) ?>;
    const decimal = <?= json_encode(\App\Services\TimeService::decimal()) ?>;
    const sep = <?= json_encode(\App\Core\Lang::locale() === 'tr' ? ',' : '.') ?>;
    const rounded = <?= json_encode(__(\App\Services\TimeService::decimal() ? 'time.rounded_to_decimal' : 'time.rounded_to')) ?>;

    document.querySelectorAll('[data-duration]').forEach(input => {
        const preview = input.parentElement.querySelector('[data-duration-preview]');
        const update = () => {
            const v = parse(input.value);
            if (!input.value.trim()) { preview.textContent = hint; preview.classList.remove('text-warn'); return; }
            if (v === null || v <= 0) { preview.textContent = invalid; preview.classList.add('text-warn'); return; }
            const r = Math.ceil(v / 6) * 6;
            preview.textContent = rounded.replace(':time', fmt(r)).replace(':hours', (r / 60).toFixed(1).replace('.', sep));
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

    // Kurşun: aşama seçimi. Proje değişince o projenin aşamaları listelenir; aşaması yoksa alan gizlenir.
    const pt = <?= json_encode([
        'none' => __('phases.choose_none'), 'notApproved' => __('phases.not_approved_short'),
        'remaining' => __('phases.hint_remaining'), 'pct' => __('phases.hint_pct'),
        'over' => __('phases.hint_over'), 'overShort' => __('phases.hint_over_short'),
        'oos' => __('phases.hint_oos'), 'noneHint' => __('phases.hint_none'),
        'overdue' => __('phases.overdue'), 'completed' => __('phases.completed'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('.time-form').forEach(form => {
        const field = form.querySelector('[data-phase-field]');
        if (!field) return;
        const project = form.querySelector('[data-project]');
        const select = field.querySelector('[data-phase]');
        const hint = field.querySelector('[data-phase-hint]');
        const map = JSON.parse(field.dataset.phases || '{}');
        let wanted = field.dataset.selected;
        const showHint = () => {
            const ph = (map[project.value] || []).find(p => String(p.id) === select.value);
            hint.className = 'muted';
            if (!ph) { hint.textContent = pt.noneHint; return; }
            const tags = [ph.overdue ? pt.overdue : '', ph.completed ? pt.completed : ''].filter(Boolean).join(' · ');
            let text = '';
            if (ph.oos) text = pt.oos;
            else if (ph.state === 'over') { text = ph.over ? pt.over.replace(':amount', ph.over) : pt.overShort; hint.className = 'text-red'; }
            else if (ph.state) {
                text = ph.remaining ? pt.remaining.replace(':amount', ph.remaining).replace(':pct', ph.pct) : pt.pct.replace(':pct', ph.pct);
                if (ph.state === 'warn') hint.className = 'text-warn';
            }
            hint.textContent = [tags, text].filter(Boolean).join(' · ');
        };
        const fill = () => {
            const list = map[project.value] || [];
            field.hidden = !list.length;
            select.innerHTML = '';
            select.add(new Option(pt.none, ''));
            list.forEach(p => {
                const o = new Option(p.name + (p.active ? '' : ' (' + pt.notApproved + ')'), p.id);
                // Onaylanmamış opsiyonel aşama seçilemez; zaten o aşamadaki kayıt düzenleniyorsa korunur
                o.disabled = !p.active && String(p.id) !== wanted;
                select.add(o);
            });
            select.value = list.some(p => String(p.id) === wanted) ? wanted : '';
            wanted = '';
            showHint();
        };
        project.addEventListener('change', fill);
        select.addEventListener('change', showHint);
        fill();
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
