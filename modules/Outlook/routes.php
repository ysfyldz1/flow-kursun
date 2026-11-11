<?php

use Modules\Outlook\OutlookController;

// Panel ve simgeler Outlook'tan oturumsuz açılır; API çerez değil Authorization başlığı kullanır (csrf yok)
$api = ['auth' => false, 'csrf' => false];
$router->get('/outlook/pane', [OutlookController::class, 'pane'], ['auth' => false]);
$router->get('/outlook/icon-{size}.png', [OutlookController::class, 'icon'], ['auth' => false]);
$router->get('/outlook/connect', [OutlookController::class, 'connect']);
$router->get('/outlook/api/me', [OutlookController::class, 'me'], $api);
$router->post('/outlook/api/context', [OutlookController::class, 'context'], $api);
$router->get('/outlook/api/projects', [OutlookController::class, 'projects'], $api);
$router->post('/outlook/api/save', [OutlookController::class, 'save'], $api);
$router->post('/outlook/api/queue', [OutlookController::class, 'queue'], $api);
$router->post('/outlook/api/unqueue', [OutlookController::class, 'unqueue'], $api);
$router->post('/outlook/api/logout', [OutlookController::class, 'logout'], $api);
$router->get('/settings/microsoft/outlook-manifest.xml', [OutlookController::class, 'manifest'], ['can' => 'settings.manage']);
