<?php
use App\Services\TimeService;

$h = fn ($m) => TimeService::hours((int) $m);
$rates = can('rates.view');
?>
<div class="page-head">
    <div>
        <a href="/billing" class="back-link">← <?= e(__('billing.title')) ?></a>
        <h1><?= e(__('billing.new')) ?></h1>
        <p class="muted"><?= e(__('billing.start_hint')) ?></p>
    </div>
</div>

<?php if (!$client): ?>
    <!-- Müvekkil arama: yazdıkça süzülür -->
    <section class="card client-finder">
        <label class="finder-input">
            <?= icon('search') ?>
            <input type="search" id="client-q" placeholder="<?= e(__('billing.find_client')) ?>" autocomplete="off" autofocus>
        </label>
        <div class="finder-results" id="client-results" hidden>
            <?php foreach ($clients as $c): ?>
                <a href="?client_id=<?= $c['id'] ?>" class="finder-item" data-q="<?= e(mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $c['code'] . ' ' . $c['name']))) ?>" hidden>
                    <span class="code"><?= e($c['code']) ?></span>
                    <strong><?= e($c['name']) ?></strong>
                    <?php if ($c['unbilled']): ?><span class="state-pill state-open"><?= e(__('billing.n_unbilled', ['count' => $c['unbilled']])) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <div class="finder-empty muted" hidden><?= e(__('billing.no_client_match')) ?></div>
        </div>
    </section>

    <div class="section-head">
        <h2><?= e(__('billing.waiting_title')) ?></h2>
        <span class="muted small"><?= e(__('billing.waiting_hint')) ?></span>
    </div>
    <?php if (!$waiting): ?>
        <section class="card"><div class="empty empty-lg"><?= icon('check') ?> <?= e(__('billing.nothing_waiting')) ?></div></section>
    <?php else: ?>
        <div class="bill-cards">
            <?php foreach ($waiting as $w): ?>
                <a href="?client_id=<?= $w['client_id'] ?>" class="bill-card bill-open">
                    <div class="bill-card-head">
                        <span class="code"><?= e($w['code']) ?></span>
                        <span class="muted small"><?= e(format_date($w['first_date'])) ?> – <?= e(format_date($w['last_date'])) ?></span>
                    </div>
                    <strong class="bill-card-title"><?= e($w['name']) ?></strong>
                    <div class="bill-card-big">
                        <span><?= e(__('billing.unbilled')) ?></span>
                        <b><?= $h($w['minutes']) ?> <?= e(__('projects.hours')) ?></b>
                        <?php if ($rates): ?><em><?= (float) $w['amount'] > 0 ? e(format_money((float) $w['amount'], $w['currency'])) : e(__('billing.no_rate_short')) ?></em><?php endif; ?>
                    </div>
                    <div class="bill-card-foot">
                        <span><?= (int) $w['entries'] ?> <?= e(__('billing.entries')) ?><?php if ($w['expenses']): ?> · <?= (int) $w['expenses'] ?> <?= e(__('expenses.count_label')) ?><?php endif; ?></span>
                        <span class="bill-card-go"><?= e(__('billing.open_client')) ?> →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
    (() => {
        const q = document.getElementById('client-q'), box = document.getElementById('client-results');
        const items = [...box.querySelectorAll('.finder-item')], empty = box.querySelector('.finder-empty');
        // Türkçe küçük harf: İ→i, I→ı
        const norm = s => s.replace(/İ/g, 'i').replace(/I/g, 'ı').toLocaleLowerCase('tr');
        q.addEventListener('input', () => {
            const term = norm(q.value.trim());
            box.hidden = term === '';
            let shown = 0;
            items.forEach(a => { const ok = term !== '' && a.dataset.q.includes(term) && shown < 12; a.hidden = !ok; if (ok) shown++; });
            empty.hidden = shown > 0;
        });
        q.addEventListener('keydown', e => {
            const first = items.find(a => !a.hidden);
            if (e.key === 'Enter' && first) { e.preventDefault(); location.href = first.href; }
        });
    })();
    </script>
<?php else: ?>
    <div class="client-banner card">
        <div>
            <span class="code"><?= e($client['code']) ?></span>
            <h2><?= e($client['name']) ?></h2>
        </div>
        <div class="head-actions">
            <a href="/billing/new" class="btn btn-sm btn-outline"><?= e(__('billing.change_client')) ?></a>
            <?php if (array_filter($projects, fn ($p) => $p['state'] === 'open')): ?>
                <form method="get" action="/billing/preview">
                    <input type="hidden" name="back" value="/billing/new?client_id=<?= $client['id'] ?>">
                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <input type="hidden" name="currency" value="<?= e($client['currency']) ?>">
                    <button class="btn btn-sm btn-outline"><?= e(__('billing.all_projects_one')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$projects): ?>
        <section class="card"><div class="empty"><?= e(__('billing.no_open_projects')) ?></div></section>
    <?php else: ?>
        <div class="bill-cards">
            <?php foreach ($projects as $p): $cur = $p['currency']; ?>
                <a href="/billing/new/project/<?= $p['id'] ?>" class="bill-card bill-<?= e($p['state']) ?>">
                    <div class="bill-card-head">
                        <span class="code"><?= e($p['number']) ?></span>
                        <?php if ($p['phased']): ?><span class="badge badge-gray"><?= e(__('phases.title')) ?></span><?php endif; ?>
                    </div>
                    <strong class="bill-card-title"><?= e($p['name']) ?></strong>
                    <?php if ($p['state'] === 'done' || $p['state'] === 'none'): ?>
                        <div class="bill-card-note"><?= icon('check', 'icon icon-sm') ?> <?= e(__($p['state'] === 'done' ? 'billing.all_billed' : 'billing.no_entries')) ?></div>
                    <?php else: ?>
                        <div class="bill-card-big">
                            <span><?= e(__('billing.unbilled')) ?></span>
                            <b><?= $h($p['unbilled_minutes']) ?> <?= e(__('projects.hours')) ?></b>
                            <?php if ($rates): ?><em><?= (float) $p['unbilled_amount'] > 0 ? e(format_money((float) $p['unbilled_amount'], $cur)) : e(__('billing.no_rate_short')) ?></em><?php endif; ?>
                        </div>
                        <?php if ($p['draft_count']): ?><div class="bill-card-note bill-card-draft"><?= e(__('billing.n_in_draft', ['count' => $p['draft_count']])) ?></div><?php endif; ?>
                    <?php endif; ?>
                    <div class="bill-card-foot">
                        <span><?= e(__('billing.billed')) ?></span>
                        <span><?= $h($p['billed_minutes']) ?> <?= e(__('projects.hours')) ?><?php if ($rates): ?> · <?= e(format_money((float) $p['billed_amount'], $cur)) ?><?php endif; ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="muted small"><a href="/billing/new/advanced" class="link"><?= e(__('billing.advanced_link')) ?></a></p>
<?php endif; ?>
