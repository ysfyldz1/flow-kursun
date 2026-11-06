<?php $color = (string) \App\Services\Settings::get('org.primary_color', '#1e3a8a'); ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0"><tr>
    <td style="background:<?= e($color) ?>;border-radius:8px">
        <a href="<?= e($url) ?>" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:600"><?= e($label) ?></a>
    </td>
</tr></table>
