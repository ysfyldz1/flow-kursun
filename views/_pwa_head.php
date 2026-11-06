<?php
use App\Services\AppIcon;
use App\Services\Settings;

$portalApp = $portalApp ?? false;
?>
<link rel="manifest" href="/manifest.webmanifest<?= $portalApp ? '?app=portal' : '' ?>">
<meta name="theme-color" content="<?= e((string) Settings::get('org.primary_color', '#1e3a8a')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(AppIcon::url('favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(AppIcon::url('apple-180.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e((string) Settings::get('org.name', config('app.name'))) ?>">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
</script>
