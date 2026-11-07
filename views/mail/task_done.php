<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.task_done_body', ['user' => $doneBy])) ?></p>
<?= \App\Core\View::partial('mail/_task', get_defined_vars()) ?>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.task_button')]) ?>
