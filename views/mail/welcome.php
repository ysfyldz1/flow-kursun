<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.welcome_body', ['org' => $org])) ?></p>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.welcome_button')]) ?>
<p style="margin:0;font-size:13px;color:#6b7280">
    <?php if ($microsoft): ?>
        <?= e(__('mail.welcome_microsoft', ['email' => $email])) ?>
    <?php else: ?>
        <?= e(__('mail.welcome_password', ['email' => $email])) ?>
    <?php endif; ?>
</p>
