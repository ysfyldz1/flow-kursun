<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.deadline_alert_body_' . $kind, ['date' => format_date($date)])) ?></p>
<p style="margin:16px 0 4px;font-weight:600;font-size:15px"><span style="color:#dc2626"><?= e(__('cal.type_deadline')) ?> · </span><?= e($title) ?></p>
<?php if ($project || $court): ?>
    <p style="margin:0;color:#64748b;font-size:13px"><?= e(implode(' · ', array_filter([$project, $court]))) ?></p>
<?php endif; ?>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.deadline_alert_button')]) ?>
<p style="margin:0;color:#64748b;font-size:12px"><?= e(__('mail.deadline_alert_footer')) ?></p>
