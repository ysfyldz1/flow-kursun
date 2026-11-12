<?php
use App\Services\TimeService;

$h = fn ($m) => TimeService::hours((int) $m);
?>
<div class="page-head">
    <div>
        <a href="/billing" class="back-link">← <?= e(__('billing.title')) ?></a>
        <h1><?= e(__('billing.new')) ?></h1>
        <p class="muted"><?= e(__('billing.start_hint')) ?></p>
    </div>
</div>

<form method="get" class="card form-card narrow start-client">
    <label class="field">
        <span><?= e(__('clients.name')) ?></span>
        <select name="client_id" onchange="this.form.submit()">
            <option value=""><?= e(__('projects.choose_client')) ?></option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $client && (int) $client['id'] === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['code'] . ' · ' . $c['name']) ?><?= $c['unbilled'] ? ' — ' . e(__('billing.n_unbilled', ['count' => $c['unbilled']])) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if ($client): ?>
    <div class="section-head">
        <h2><?= e($client['name']) ?></h2>
        <?php if (array_filter($projects, fn ($p) => $p['state'] === 'open')): ?>
            <form method="post" action="/billing" onsubmit="return confirm(<?= e(json_encode(__('billing.all_projects_confirm'), JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                <input type="hidden" name="currency" value="<?= e($client['currency']) ?>">
                <button class="btn btn-sm btn-outline"><?= e(__('billing.all_projects_one')) ?></button>
            </form>
        <?php endif; ?>
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
                            <?php if (can('rates.view')): ?><em><?= e(format_money((float) $p['unbilled_amount'], $cur)) ?></em><?php endif; ?>
                        </div>
                        <?php if ($p['draft_count']): ?><div class="bill-card-note bill-card-draft"><?= e(__('billing.n_in_draft', ['count' => $p['draft_count']])) ?></div><?php endif; ?>
                    <?php endif; ?>
                    <div class="bill-card-foot">
                        <span><?= e(__('billing.billed')) ?></span>
                        <span><?= $h($p['billed_minutes']) ?> <?= e(__('projects.hours')) ?><?php if (can('rates.view')): ?> · <?= e(format_money((float) $p['billed_amount'], $cur)) ?><?php endif; ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="muted small"><a href="/billing/new/advanced" class="link"><?= e(__('billing.advanced_link')) ?></a></p>
<?php endif; ?>
