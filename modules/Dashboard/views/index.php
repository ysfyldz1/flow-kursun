<?php use App\Services\TimeService; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('dash.hello', ['name' => explode(' ', auth()['name'])[0]])) ?></h1>
        <p class="muted"><?= e(__('dash.subtitle')) ?></p>
    </div>
</div>

<?php if ($office): ?>
    <?= \App\Core\View::partial('Dashboard::_office', ['o' => $office]) ?>
    <div class="office-head"><h2><?= e(__('dash.mine')) ?></h2></div>
<?php endif; ?>

<div class="grid grid-<?= max(1, count($stats)) ?>">
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
    <?php if ($tasks !== null): ?>
        <section class="card widget">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('check') ?></span>
                <h2><?= e(__('tasks.mine')) ?></h2>
                <a href="/tasks" class="widget-link"><?= e(__('app.all')) ?> →</a>
            </header>
            <?php if (!$tasks): ?>
                <div class="empty"><?= e(__('tasks.empty')) ?></div>
            <?php endif; ?>
            <?php foreach (array_slice($tasks, 0, 6) as $t): ?>
                <?= \App\Core\View::partial('Tasks::_row', ['t' => $t]) ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if ($upcoming !== null): ?>
        <section class="card widget">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('calendar') ?></span>
                <h2><?= e(__('cal.upcoming')) ?></h2>
                <a href="/calendar" class="widget-link"><?= e(__('app.all')) ?> →</a>
            </header>
            <?php if (!$upcoming): ?>
                <div class="empty"><?= e(__('cal.upcoming_empty')) ?></div>
            <?php else: ?>
                <?php foreach (array_slice($upcoming, 0, 7) as $ev): ?>
                    <?= \App\Core\View::partial('Calendar::_row', ['e' => $ev, 'showDate' => true]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($leavePending || $onLeave): ?>
        <section class="card widget">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('sun') ?></span>
                <h2><?= e(__('leaves.widget')) ?></h2>
                <a href="/leaves" class="widget-link"><?= e(__('app.all')) ?> →</a>
            </header>
            <?php foreach ($leavePending as $l): ?>
                <a href="/leaves/<?= $l['id'] ?>" class="widget-row">
                    <div class="cell-main">
                        <strong><?= e($l['user_name']) ?></strong>
                        <span><?= e(__('leaves.type_' . $l['type'])) ?> · <?= e(format_date($l['start_date'])) ?><?= $l['end_date'] !== $l['start_date'] ? ' – ' . e(format_date($l['end_date'])) : '' ?></span>
                    </div>
                    <div class="cell-main align-right"><span class="badge badge-amber"><?= e(__('leaves.awaiting')) ?></span></div>
                </a>
            <?php endforeach; ?>
            <?php foreach ($onLeave as $l): ?>
                <?= \App\Core\View::partial('Leaves::_cal_row', ['l' => $l]) ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($recentTime !== null): ?>
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

    <?php endif; ?>

    <?php if ($myProjects !== null): ?>
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

    <?php endif; ?>

    <?php if ($unpaid !== null): ?>
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
    <?php endif; ?>
</div>
