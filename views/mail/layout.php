<?php
// E-posta istemcileri harici CSS'i desteklemez; stiller satır içi, düzen tablo ile.
use App\Services\Settings;

$orgName = (string) Settings::get('org.name', config('app.name'));
$color = (string) Settings::get('org.primary_color', '#1e3a8a');
?>
<!doctype html>
<html>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1f2937">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 16px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:10px;overflow:hidden">
            <tr><td style="background:<?= e($color) ?>;padding:18px 28px;color:#ffffff;font-size:17px;font-weight:600"><?= e($orgName) ?></td></tr>
            <tr><td style="padding:28px;font-size:15px;line-height:1.6">
                <?= $content ?>
            </td></tr>
        </table>
        <p style="font-size:12px;color:#9ca3af;margin:16px 0 0"><?= e(__('mail.footer', ['org' => $orgName])) ?></p>
    </td></tr>
</table>
</body>
</html>
