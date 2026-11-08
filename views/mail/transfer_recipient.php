<p style="margin:0 0 16px"><?= e(__('mail.transfer_recipient_body', ['sender' => $sender])) ?></p>
<p style="margin:0;font-weight:600"><?= e($title) ?></p>
<p style="margin:4px 0 0;color:#64748b;font-size:13px"><?= e(__('transfers.summary', ['count' => $count, 'size' => $size])) ?></p>
<?php if ($note !== ''): ?>
    <p style="margin:14px 0 0;white-space:pre-line"><?= e($note) ?></p>
<?php endif; ?>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.transfer_recipient_button')]) ?>
<?php if ($until !== ''): ?>
    <p style="margin:0;color:#64748b;font-size:12px"><?= e(__('mail.transfer_recipient_until', ['until' => $until])) ?></p>
<?php endif; ?>
<?php if ($protected !== ''): ?>
    <p style="margin:6px 0 0;color:#64748b;font-size:12px"><?= e(__('mail.transfer_recipient_protected')) ?></p>
<?php endif; ?>
