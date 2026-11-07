<?php if (\App\Services\MeetingService::accepting($m)): ?>
    <span class="badge badge-green"><?= e(__('meetings.open')) ?></span>
<?php elseif (strtotime($m['meeting_at']) <= time()): ?>
    <span class="badge badge-gray"><?= e(__('meetings.held')) ?></span>
<?php else: ?>
    <span class="badge badge-amber"><?= e(__('meetings.closed')) ?></span>
<?php endif; ?>
