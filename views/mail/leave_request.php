<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.leave_request_body', ['employee' => $employee])) ?></p>
<?= \App\Core\View::partial('mail/_leave', get_defined_vars()) ?>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.leave_request_button')]) ?>
