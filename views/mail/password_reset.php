<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.password_reset_body', ['org' => $org])) ?></p>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.password_reset_button')]) ?>
<p style="margin:0;font-size:13px;color:#6b7280"><?= e(__('mail.password_reset_note', ['minutes' => $minutes])) ?></p>
