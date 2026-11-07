<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.leave_reviewed_body_' . $status, ['reviewer' => (string) $reviewer])) ?></p>
<?= \App\Core\View::partial('mail/_leave', get_defined_vars()) ?>
<?php if ($note): ?>
    <p style="margin:12px 0 0"><strong><?= e(__('leaves.review_note')) ?>:</strong> <?= nl2br(e($note)) ?></p>
<?php endif; ?>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.leave_reviewed_button')]) ?>
