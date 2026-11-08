<?php

use Modules\Auth\AuthController;

$router->get('/login', [AuthController::class, 'showLogin'], ['auth' => false, 'guest' => true]);
$router->post('/login', [AuthController::class, 'login'], ['auth' => false, 'guest' => true]);
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['auth' => false, 'guest' => true]);
$router->post('/forgot-password', [AuthController::class, 'forgot'], ['auth' => false, 'guest' => true]);
$router->get('/reset-password/{token}', [AuthController::class, 'showReset'], ['auth' => false, 'guest' => true]);
$router->post('/reset-password/{token}', [AuthController::class, 'reset'], ['auth' => false, 'guest' => true]);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/locale', [AuthController::class, 'locale'], ['auth' => false]);
$router->get('/auth/microsoft', [AuthController::class, 'microsoft'], ['auth' => false, 'guest' => true]);
// Geri dönüş adresi hem giriş hem hesap bağlama için ortak; giriş yapmış kullanıcı da gelebilir
$router->get('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback'], ['auth' => false]);
$router->get('/account', [AuthController::class, 'account']);
$router->post('/account/password', [AuthController::class, 'changePassword']);
$router->get('/account/microsoft/connect', [AuthController::class, 'microsoftConnect']);
$router->post('/account/microsoft/disconnect', [AuthController::class, 'microsoftDisconnect']);
