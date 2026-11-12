<?php
use App\Core\View;
use App\Services\TimeService;

$prev = date('Y-m-d', strtotime("$monday -7 day"));
$next = date('Y-m-d', strtotime("$monday +7 day"));
$isCurrentWeek = $monday === date('Y-m-d', strtotime('monday this week'));
$userParam = $isSelf ? '' : '&user=' . $viewUser['id'];
$weekMinutes = array_sum(array_column($days, 'minutes'));
$billableMinutes = array_sum(array_map(fn ($e) => $e['is_billable'] ? (int) $e['minutes'] : 0, $entries));
$dayNames = explode(',', __('time.day_names'));
?>
<div class="page-head">
    <div>
        <h1><?= e(__('time.title')) ?></h1>
        <p class="muted"><?= e($isSelf ? __('time.subtitle') : __('time.viewing', ['name' => $viewUser['name']])) ?></p>
    </div>
    <div class="head-actions">
        <?php if ($users): ?>
            <form method="get">
                <input type="hidden" name="week" value="<?= e($monday) ?>">
                <select name="user" class="toolbar-select" onchange="this.form.submit()">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int) $u['id'] === (int) $viewUser['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <div class="week-nav">
            <a href="?week=<?= $prev . $userParam ?>" class="icon-btn" title="<?= e(__('time.prev_week')) ?>">‹</a>
            <span><?= e(format_date($monday)) ?> – <?= e(format_date(date('Y-m-d', strtotime("$monday +6 day")))) ?></span>
            <a href="?week=<?= $next . $userParam ?>" class="icon-btn" title="<?= e(__('time.next_week')) ?>">›</a>
            <?php if (!$isCurrentWeek): ?><a href="?week=<?= date('Y-m-d') . $userParam ?>" class="btn btn-sm btn-outline"><?= e(__('time.this_week')) ?></a><?php endif; ?>
        </div>
    </div>
</div>

<div class="time-layout">
    <div class="stack">
        <?= View::partial('Time::_timer', ['timer' => $timer, 'projects' => TimeService::projectOptions(\App\Core\Auth::id())]) ?>

        <section class="card form-card">
            <h3 class="card-title"><?= e(__('time.new_entry')) ?></h3>
            <?= View::partial('Time::_form', ['entry' => null, 'projects' => $projects, 'userId' => $viewUser['id']]) ?>
        </section>
    </div>

    <div class="stack">
        <section class="card week-strip">
            <?php $i = 0; foreach ($days as $day => $d): ?>
                <a href="#d-<?= $day ?>" class="week-day <?= $day === date('Y-m-d') ? 'today' : '' ?> <?= $d['minutes'] ? '' : 'empty-day' ?>">
                    <span><?= e($dayNames[$i++]) ?> <?= date('d', strtotime($day)) ?></span>
                    <strong><?= TimeService::formatMinutes($d['minutes']) ?></strong>
                </a>
            <?php endforeach; ?>
            <div class="week-total">
                <span><?= e(__('time.week_total')) ?></span>
                <strong><?= TimeService::formatMinutes($weekMinutes) ?></strong>
                <small><?= e(__('time.billable_short')) ?>: <?= TimeService::formatMinutes($billableMinutes) ?></small>
            </div>
        </section>

        <?php if (!$entries): ?>
            <section class="card"><div class="empty empty-lg"><?= e(__('time.empty_week')) ?></div></section>
        <?php endif; ?>

        <?php foreach (array_reverse($days, true) as $day => $d): ?>
            <?php if (!$d['entries']) continue; ?>
            <section class="card day-card" id="d-<?= $day ?>">
                <header class="day-head">
                    <strong><?= e(format_date($day)) ?></strong>
                    <span class="muted"><?= e($dayNames[(int) date('N', strtotime($day)) - 1]) ?></span>
                    <span class="day-total"><?= TimeService::formatMinutes($d['minutes']) ?></span>
                </header>
                <?php foreach ($d['entries'] as $t): ?>
                    <div class="entry">
                        <div class="entry-main">
                            <div class="entry-project">
                                <span class="code"><?= e($t['project_number']) ?></span>
                                <a href="/projects/<?= $t['project_id'] ?>" class="link"><?= e($t['project_name']) ?></a>
                                <span class="muted">· <?= e($t['client_name']) ?></span>
                                <?php if ($t['phase_name'] !== null): ?>
                                    <span class="badge <?= $t['phase_oos'] ? 'badge-amber' : 'badge-gray' ?>"><?= e($t['phase_oos'] ? __('phases.out_of_scope') : $t['phase_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="entry-desc"><?= nl2br(e($t['description'])) ?></p>
                        </div>
                        <div class="entry-side">
                            <strong class="entry-time"><?= TimeService::formatMinutes((int) $t['minutes']) ?></strong>
                            <?php if (!$t['is_billable']): ?>
                                <span class="badge badge-gray"><?= e(__('time.non_billable')) ?></span>
                            <?php elseif (can('rates.view') && $t['amount'] !== null): ?>
                                <span class="muted small"><?= e(format_money((float) $t['amount'], $t['currency'])) ?></span>
                            <?php endif; ?>
                            <?php if ($t['statement_id']): ?>
                                <span class="badge badge-blue" title="<?= e(__('time.locked')) ?>"><?= e(__('time.billed')) ?></span>
                            <?php elseif (TimeService::canModify($t)): ?>
                                <div class="entry-actions">
                                    <a href="/time/<?= $t['id'] ?>/edit" class="btn btn-sm btn-outline"><?= e(__('clients.edit')) ?></a>
                                    <form method="post" action="/time/<?= $t['id'] ?>/delete" onsubmit="return confirm('<?= e(__('app.confirm_delete')) ?>')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<?= View::partial('Time::_script') ?>
