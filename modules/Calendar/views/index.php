<?php
use App\Core\View;

$prev = date('Y-m', strtotime("$month-01 -1 month"));
$next = date('Y-m', strtotime("$month-01 +1 month"));
$q = fn (array $over) => '?' . http_build_query($over + ['month' => $month, 'mine' => $mine ? '1' : '0']);
$monthLabel = __('cal.month_' . (int) substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
$today = date('Y-m-d');
?>
<div class="page-head">
    <div>
        <h1><?= e(__('cal.title')) ?></h1>
        <p class="muted"><?= e(__('cal.subtitle')) ?></p>
    </div>
    <?php if (can('calendar.edit')): ?>
        <a href="/calendar/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('cal.new')) ?></a>
    <?php endif; ?>
</div>

<?php if ($overdue): ?>
    <section class="card widget widget-auto widget-warn">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('alert') ?></span>
            <h2><?= e(__('cal.overdue_title')) ?></h2>
            <span class="muted small"><?= count($overdue) ?></span>
        </header>
        <?php foreach ($overdue as $e): ?>
            <?= View::partial('Calendar::_row', ['e' => $e, 'showDate' => true]) ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="card">
    <div class="toolbar">
        <div class="tabs">
            <a href="<?= e($q(['mine' => '1'])) ?>" class="<?= $mine ? 'active' : '' ?>"><?= e(__('cal.mine')) ?></a>
            <a href="<?= e($q(['mine' => '0'])) ?>" class="<?= !$mine ? 'active' : '' ?>"><?= e(__('cal.everyone')) ?></a>
        </div>
        <div class="month-nav">
            <a href="<?= e($q(['month' => $prev])) ?>" class="btn btn-sm btn-outline" aria-label="‹">‹</a>
            <strong><?= e($monthLabel) ?></strong>
            <a href="<?= e($q(['month' => $next])) ?>" class="btn btn-sm btn-outline" aria-label="›">›</a>
            <?php if ($month !== date('Y-m')): ?>
                <a href="<?= e($q(['month' => date('Y-m')])) ?>" class="btn btn-sm btn-outline"><?= e(__('cal.today')) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$days): ?>
        <div class="empty empty-lg"><?= e(__('cal.empty_month')) ?></div>
    <?php else: ?>
        <?php foreach ($days as $day => $events): ?>
            <div class="agenda-day<?= $day === $today ? ' is-today' : '' ?><?= $day < $today ? ' is-past' : '' ?>">
                <div class="agenda-date">
                    <strong><?= (int) substr($day, 8, 2) ?></strong>
                    <span><?= e(__('cal.dow_' . date('N', strtotime($day)))) ?></span>
                </div>
                <div class="agenda-events">
                    <?php foreach ($events as $e): ?>
                        <?= View::partial('Calendar::_row', ['e' => $e]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
