<?php

use Modules\Auth\AuthController;

$router->get('/login', [AuthController::class, 'showLogin'], ['auth' => false, 'guest' => true]);
$router->post('/login', [AuthController::class, 'login'], ['auth' => false, 'guest' => true]);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/locale', [AuthController::class, 'locale'], ['auth' => false]);
$router->get('/auth/microsoft', [AuthController::class, 'microsoft'], ['auth' => false, 'guest' => true]);
$router->get('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback'], ['auth' => false, 'guest' => true]);
