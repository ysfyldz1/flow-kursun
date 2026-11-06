<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.portal_invite_body', ['org' => $org, 'client' => $client])) ?></p>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.portal_invite_button')]) ?>
<p style="margin:0;font-size:13px;color:#6b7280"><?= e(__('mail.portal_invite_expires', ['days' => $days])) ?></p>
<p style="margin:12px 0 0;font-size:12px;color:#9ca3af;word-break:break-all"><?= e($url) ?></p>
