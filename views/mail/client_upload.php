<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.client_upload_body', ['uploader' => $uploader, 'client' => $client, 'project' => $project])) ?></p>
<p style="margin:12px 0 0;font-weight:600"><?= e($file) ?></p>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.client_upload_button')]) ?>
