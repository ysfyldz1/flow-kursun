<?php

use Modules\Clients\ClientController;

$router->get('/clients', [ClientController::class, 'index'], ['can' => 'clients.view']);
$router->get('/clients/new', [ClientController::class, 'create'], ['can' => 'clients.edit']);
$router->post('/clients', [ClientController::class, 'store'], ['can' => 'clients.edit']);
$router->get('/clients/{id}', [ClientController::class, 'show'], ['can' => 'clients.view']);
$router->get('/clients/{id}/edit', [ClientController::class, 'edit'], ['can' => 'clients.edit']);
$router->post('/clients/{id}', [ClientController::class, 'update'], ['can' => 'clients.edit']);
$router->post('/clients/{id}/active', [ClientController::class, 'toggle'], ['can' => 'clients.edit']);
