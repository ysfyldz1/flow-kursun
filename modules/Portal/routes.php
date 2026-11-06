<?php

use Modules\Portal\PortalAccessController;
use Modules\Portal\PortalController;

// Müvekkil tarafı
$guest = ['auth' => false];
$router->get('/portal/login', [PortalController::class, 'showLogin'], $guest);
$router->post('/portal/login', [PortalController::class, 'login'], $guest);
$router->get('/portal/invite/{token}', [PortalController::class, 'showInvite'], $guest);
$router->post('/portal/invite/{token}', [PortalController::class, 'acceptInvite'], $guest);

$portal = ['portal' => true];
$router->post('/portal/logout', [PortalController::class, 'logout'], $portal);
$router->get('/portal', [PortalController::class, 'index'], $portal);
$router->get('/portal/projects/{id}', [PortalController::class, 'project'], $portal);
$router->get('/portal/statements/{id}',[PortalController::class, 'statement'], $portal);
$router->get('/portal/statements/{id}/pdf', [PortalController::class, 'pdf'], $portal);
$router->get('/portal/statements/{id}/invoice', [PortalController::class, 'invoice'], $portal);
$router->post('/portal/statements/{id}/notice', [PortalController::class, 'notice'], $portal);

// Ofis tarafı: erişim yönetimi ve bildirim onayı
$router->post('/clients/{id}/portal', [PortalAccessController::class, 'invite'], ['can' => 'portal.manage']);
$router->post('/clients/{id}/portal/{user}/active', [PortalAccessController::class, 'toggle'], ['can' => 'portal.manage']);
$router->post('/billing/{id}/notices/{notice}/approve', [PortalAccessController::class, 'approve'], ['can' => 'payments.manage']);
$router->post('/billing/{id}/notices/{notice}/reject', [PortalAccessController::class, 'reject'], ['can' => 'payments.manage']);
$router->get('/billing/{id}/notices/{notice}/receipt', [PortalAccessController::class, 'receipt'], ['can' => 'payments.manage']);
