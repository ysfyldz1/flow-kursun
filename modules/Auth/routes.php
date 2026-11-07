<?php

use Modules\Auth\AuthController;

$router->get('/login', [AuthController::class, 'showLogin'], ['auth' => false, 'guest' => true]);
$router->post('/login', [AuthController::class, 'login'], ['auth' => false, 'guest' => true]);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/locale', [AuthController::class, 'locale'], ['auth' => false]);
$router->get('/auth/microsoft', [AuthController::class, 'microsoft'], ['auth' => false, 'guest' => true]);
// Geri dönüş adresi hem giriş hem hesap bağlama için ortak; giriş yapmış kullanıcı da gelebilir
$router->get('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback'], ['auth' => false]);
$router->get('/account', [AuthController::class, 'account']);
$router->get('/account/microsoft/connect', [AuthController::class, 'microsoftConnect']);
$router->post('/account/microsoft/disconnect', [AuthController::class, 'microsoftDisconnect']);
