<?php use App\Services\TimeService; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('dash.hello', ['name' => explode(' ', auth()['name'])[0]])) ?></h1>
        <p class="muted"><?= e(__('dash.subtitle')) ?></p>
    </div>
    <button class="btn btn-outline"><?= icon('sliders') ?><?= e(__('dash.customize')) ?></button>
</div>

<div class="grid grid-4">
    <?php foreach ($stats as $s): ?>
        <div class="card stat">
            <span class="stat-icon tone-<?= $s['tone'] ?>"><?= icon($s['icon']) ?></span>
            <div class="stat-text">
                <strong><?= e($s['label']) ?></strong>
                <span><?= e($s['hint']) ?></span>
            </div>
            <div class="stat-value"><?= e($s['value']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-3">
    <section class="card widget">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('clock') ?></span>
            <h2><?= e(__('dash.recent_time')) ?></h2>
            <a href="/time" class="widget-link"><?= e(__('app.all')) ?> →</a>
        </header>
        <?php if (!$recentTime): ?>
            <div class="empty"><?= e(__('dash.empty')) ?></div>
        <?php else: ?>
            <?php foreach ($recentTime as $t): ?>
                <a href="/projects/<?= $t['project_id'] ?>" class="widget-row">
                    <div class="cell-main">
                        <strong><?= e($t['project_name']) ?></strong>
                        <span><?= e(mb_strimwidth($t['description'], 0, 60, '…')) ?></span>
                    </div>
                    <div class="cell-main align-right">
                        <strong><?= TimeService::formatMinutes((int) $t['minutes']) ?></strong>
                        <span><?= e(format_date($t['work_date'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card widget">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('folder') ?></span>
            <h2><?= e(__('dash.my_projects')) ?></h2>
            <a href="/projects?mine=1" class="widget-link"><?= e(__('app.all')) ?> →</a>
        </header>
        <?php if (!$myProjects): ?>
            <div class="empty"><?= e(__('dash.empty')) ?></div>
        <?php else: ?>
            <?php foreach (array_slice($myProjects, 0, 6) as $p): ?>
                <a href="/projects/<?= $p['id'] ?>" class="widget-row">
                    <div class="cell-main">
                        <strong><?= e($p['name']) ?></strong>
                        <span><?= e($p['number']) ?> · <?= e($p['client_name']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card widget">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('receipt') ?></span>
            <h2><?= e(__('dash.unpaid_list')) ?></h2>
            <?php if ($unpaid): ?><a href="/billing" class="widget-link"><?= e(__('app.all')) ?> →</a><?php endif; ?>
        </header>
        <?php if (!$unpaid): ?>
            <div class="empty"><?= e(__('dash.empty')) ?></div>
        <?php else: ?>
            <?php foreach (array_slice($unpaid, 0, 6) as $s): ?>
                <a href="/billing/<?= $s['id'] ?>" class="widget-row">
                    <div class="cell-main">
                        <strong><?= e($s['client_name']) ?></strong>
                        <span><?= e($s['number']) ?> · <?= e(__('billing.due_date')) ?> <?= e(format_date($s['due_date'])) ?></span>
                    </div>
                    <div class="cell-main align-right">
                        <strong><?= e(format_money(\App\Services\StatementService::balance($s), $s['currency'])) ?></strong>
                        <?= \App\Core\View::partial('Billing::_status', ['s' => $s]) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
