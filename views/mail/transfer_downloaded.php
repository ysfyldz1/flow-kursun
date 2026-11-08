<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.transfer_downloaded_body', ['title' => $title])) ?></p>
<p style="margin:12px 0 0;color:#64748b;font-size:13px">
    <?= e($file_name !== '' ? $file_name : __('transfers.all_files')) ?><br>
    <?= e($when) ?><?php if ($recipient !== ''): ?><br><?= e($recipient) ?><?php endif; ?>
</p>
