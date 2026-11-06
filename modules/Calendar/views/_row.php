<?php
// Tek takvim kaydı satırı: ajanda, proje sayfası ve panoda ortak. $showDate: tarihi de yaz.
$isDeadline = $e['type'] === 'deadline';
$overdue = $isDeadline && !$e['done_at'] && substr($e['starts_at'], 0, 10) < date('Y-m-d');
$time = $e['all_day'] ? __('cal.all_day') : substr($e['starts_at'], 11, 5) . ($e['ends_at'] ? '–' . substr($e['ends_at'], 11, 5) : '');
?>
<a href="/calendar/<?= $e['id'] ?>" class="widget-row event-row event-<?= e($e['type']) ?><?= $e['done_at'] ? ' is-done' : '' ?><?= $overdue ? ' is-overdue' : '' ?>">
    <span class="event-bar"></span>
    <div class="cell-main">
        <strong><?= $e['done_at'] ? '✓ ' : '' ?><?= e($e['title']) ?></strong>
        <span>
            <?= e(__('cal.type_' . $e['type'])) ?>
            <?php if (!empty($e['project_number']) && empty($hideProject)): ?> · <?= e($e['project_number']) ?> · <?= e($e['client_name']) ?><?php endif; ?>
            <?php if ($e['location']): ?> · <?= e($e['location']) ?><?php endif; ?>
            <?php if (!empty($e['attendee_names'])): ?> · <?= e($e['attendee_names']) ?><?php endif; ?>
        </span>
    </div>
    <div class="cell-main align-right nowrap">
        <strong><?= !empty($showDate) ? e(format_date(substr($e['starts_at'], 0, 10))) : e($time) ?></strong>
        <span>
            <?php if ($overdue): ?><span class="text-warn"><?= e(__('cal.overdue')) ?></span>
            <?php elseif (!empty($showDate)): ?><?= e($time) ?><?php endif; ?>
            <?php if (!empty($e['sync_errors'])): ?> <span class="text-warn" title="<?= e(__('cal.sync_failed')) ?>">⚠</span><?php endif; ?>
        </span>
    </div>
</a>
