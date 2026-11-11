<?php
$tones = ['red' => '#dc2626', 'amber' => '#d97706', 'blue' => '#2563eb'];
?>
<p style="margin:0 0 16px"><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p style="margin:0"><?= e(__('mail.digest_body', ['days' => $days])) ?></p>
<?php foreach ($groups as $g): ?>
    <p style="margin:22px 0 6px;font-weight:600;font-size:15px">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $tones[$g['tone']] ?>;margin-right:6px"></span>
        <a href="<?= e($g['url']) ?>" style="color:inherit;text-decoration:none"><?= e(__('pending.' . $g['key'])) ?> (<?= $g['count'] ?>)</a>
    </p>
    <?php foreach (array_slice($g['items'], 0, 5) as $i): ?>
        <p style="margin:0 0 6px 14px;font-size:14px">
            <a href="<?= e($i['url']) ?>" style="color:inherit"><?= e($i['title']) ?></a>
            <?php if ($i['meta']): ?><br><span style="color:#64748b;font-size:13px"><?= e($i['meta']) ?></span><?php endif; ?>
        </p>
    <?php endforeach; ?>
    <?php if ($g['count'] > 5): ?>
        <p style="margin:0 0 6px 14px;font-size:13px;color:#64748b"><?= e(__('pending.more', ['count' => $g['count'] - 5])) ?></p>
    <?php endif; ?>
<?php endforeach; ?>
<?= \App\Core\View::partial('mail/_button', ['url' => $url, 'label' => __('mail.digest_button')]) ?>
<p style="margin:0;color:#64748b;font-size:12px"><?= e(__('mail.digest_footer')) ?></p>
