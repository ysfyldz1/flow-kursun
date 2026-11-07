<?php
// Takvimde izin satırı. Türü (ör. hastalık) yalnız kişinin kendisi ve izin yöneticisi görür.
$detail = (int) $l['user_id'] === (int) auth()['id'] || can('leaves.manage');
?>
<a href="<?= $detail ? '/leaves/' . $l['id'] : '#' ?>" class="widget-row event-row event-leave">
    <span class="event-bar"></span>
    <div class="cell-main">
        <strong><?= e($l['user_name']) ?></strong>
        <span><?= e($detail ? __('leaves.type_' . $l['type']) : __('leaves.on_leave')) ?></span>
    </div>
    <div class="cell-main align-right nowrap">
        <strong><?= e(__('cal.all_day')) ?></strong>
    </div>
</a>
