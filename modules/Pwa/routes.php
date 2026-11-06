<?php

use App\Services\AppIcon;
use App\Services\Settings;

// Uygulama manifest'i: kurum adı, rengi ve ikonlarından üretilir. ?app=portal müvekkil portalı içindir.
$router->get('/manifest.webmanifest', function () {
    $portal = ($_GET['app'] ?? '') === 'portal';
    $name = (string) Settings::get('org.name', config('app.name'));
    $color = (string) Settings::get('org.primary_color', '#1e3a8a');
    $icon = fn (string $file, string $size, string $purpose) => ['src' => AppIcon::url($file), 'sizes' => $size, 'type' => 'image/png', 'purpose' => $purpose];

    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode([
        'id'               => $portal ? '/portal' : '/',
        'name'             => $portal ? $name . ' · ' . __('portal.title') : $name,
        'short_name'       => mb_strimwidth($name, 0, 12, ''),
        'start_url'        => $portal ? '/portal' : '/',
        'scope'            => $portal ? '/portal' : '/',
        'display'          => 'standalone',
        'background_color' => '#f4f6f9',
        'theme_color'      => $color,
        'lang'             => \App\Core\Lang::locale(),
        'icons'            => [
            $icon('icon-192.png', '192x192', 'any'),
            $icon('icon-512.png', '512x512', 'any'),
            $icon('maskable-512.png', '512x512', 'maskable'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}, ['auth' => false]);

$router->get('/icons/{name}', function (string $name) {
    $path = AppIcon::path($name) ?? abort(404);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    readfile($path);
}, ['auth' => false]);

$router->get('/favicon.ico', fn () => redirect(AppIcon::url('favicon-32.png')), ['auth' => false]);

$router->get('/offline', function () {
    \App\Core\View::render('offline', [], null);
}, ['auth' => false]);
