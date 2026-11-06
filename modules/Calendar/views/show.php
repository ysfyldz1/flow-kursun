<?php
use App\Services\CalendarService;

$isDeadline = $e['type'] === 'deadline';
$time = $e['all_day'] ? __('cal.all_day') : substr($e['starts_at'], 11, 5) . ($e['ends_at'] ? ' – ' . substr($e['ends_at'], 11, 5) : '');
$row = fn (string $label, ?string $value) => $value !== null && $value !== ''
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . $value . '</dd></div>'
    : '';
?>
<div class="page-head">
    <div>
        <a href="<?= $e['project_id'] ? '/projects/' . $e['project_id'] : '/calendar' ?>" class="back-link">← <?= e($e['project_id'] ? $e['project_name'] : __('cal.title')) ?></a>
        <div class="title-line">
            <h1><?= e($e['title']) ?></h1>
            <span class="badge event-badge-<?= e($e['type']) ?>"><?= e(__('cal.type_' . $e['type'])) ?></span>
            <?php if ($isDeadline && $e['done_at']): ?><span class="badge badge-green"><?= e(__('cal.done')) ?></span><?php endif; ?>
        </div>
        <p class="muted"><?= e(format_date(substr($e['starts_at'], 0, 10))) ?> · <?= e(__('cal.dow_' . date('N', strtotime($e['starts_at'])))) ?> · <?= e($time) ?></p>
    </div>
    <?php if (can('calendar.edit')): ?>
        <div class="form-actions" style="margin:0">
            <?php if ($isDeadline): ?>
                <form method="post" action="/calendar/<?= $e['id'] ?>/done">
                    <?= csrf_field() ?>
                    <button class="btn <?= $e['done_at'] ? 'btn-outline' : 'btn-primary' ?>"><?= e(__($e['done_at'] ? 'cal.mark_open' : 'cal.mark_done')) ?></button>
                </form>
            <?php endif; ?>
            <a href="/calendar/<?= $e['id'] ?>/edit" class="btn btn-outline"><?= e(__('clients.edit')) ?></a>
            <form method="post" action="/calendar/<?= $e['id'] ?>/delete" onsubmit="return confirm('<?= e(__('cal.confirm_delete')) ?>')">
                <?= csrf_field() ?><button class="btn btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-3-1">
    <section class="card widget widget-auto">
        <dl class="dl">
            <?= $row('time.project', $e['project_id'] ? '<a href="/projects/' . $e['project_id'] . '" class="link">' . e($e['project_number'] . ' · ' . $e['project_name']) . '</a> — ' . e($e['client_name']) : null) ?>
            <?php if ($e['type'] === 'hearing'): ?>
                <?= $row('projects.court', $e['court'] ? e($e['court']) : null) ?>
                <?= $row('projects.case_number', $e['case_number'] ? e($e['case_number']) : null) ?>
            <?php endif; ?>
            <?= $row('cal.location', $e['location'] ? e($e['location']) : null) ?>
            <?= $row('cal.notes', $e['notes'] ? nl2br(e($e['notes'])) : null) ?>
        </dl>
    </section>

    <section class="card widget widget-auto">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('users') ?></span>
            <h2><?= e(__('cal.attendees')) ?></h2>
        </header>
        <dl class="dl">
            <?php foreach ($e['attendees'] as $a): ?>
                <div class="dl-row">
                    <dt class="text-strong"><?= e($a['name']) ?></dt>
                    <dd class="small">
                        <?php if (!CalendarService::outlookEnabled()): ?>
                        <?php elseif ($a['sync_error']): ?>
                            <span class="text-warn" title="<?= e($a['sync_error']) ?>">⚠ <?= e(__('cal.sync_failed')) ?></span>
                        <?php elseif ($a['ms_event_id']): ?>
                            <span class="muted">✓ Outlook</span>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php if (CalendarService::outlookEnabled() && $e['sync_errors'] && can('calendar.edit')): ?>
            <form method="post" action="/calendar/<?= $e['id'] ?>/resync" class="card-body">
                <?= csrf_field() ?><button class="btn btn-sm btn-outline"><?= e(__('cal.resync')) ?></button>
            </form>
        <?php endif; ?>
    </section>
</div>
