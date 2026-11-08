<?php

use Modules\Admin\MailController;
use Modules\Admin\MicrosoftController;
use Modules\Admin\OrganizationController;
use Modules\Admin\RoleController;
use Modules\Admin\UserController;

$router->get('/settings', fn () => redirect(can('users.manage') ? '/settings/users' : '/settings/organization'));

$router->get('/settings/users', [UserController::class, 'index'], ['can' => 'users.manage']);
$router->get('/settings/users/new', [UserController::class, 'create'], ['can' => 'users.manage']);
$router->post('/settings/users', [UserController::class, 'store'], ['can' => 'users.manage']);
$router->get('/settings/users/{id}', [UserController::class, 'edit'], ['can' => 'users.manage']);
$router->post('/settings/users/{id}', [UserController::class, 'update'], ['can' => 'users.manage']);
$router->post('/settings/users/{id}/active', [UserController::class, 'toggle'], ['can' => 'users.manage']);

$router->get('/settings/roles', [RoleController::class, 'index'], ['can' => 'roles.manage']);
$router->get('/settings/roles/new', [RoleController::class, 'edit'], ['can' => 'roles.manage']);
$router->post('/settings/roles', [RoleController::class, 'save'], ['can' => 'roles.manage']);
$router->get('/settings/roles/{id}', [RoleController::class, 'edit'], ['can' => 'roles.manage']);
$router->post('/settings/roles/{id}', [RoleController::class, 'save'], ['can' => 'roles.manage']);
$router->post('/settings/roles/{id}/delete', [RoleController::class, 'delete'], ['can' => 'roles.manage']);

$router->get('/settings/organization', [OrganizationController::class, 'edit'], ['can' => 'settings.manage']);
$router->post('/settings/organization', [OrganizationController::class, 'update'], ['can' => 'settings.manage']);
$router->get('/logo', [OrganizationController::class, 'logo'], ['auth' => false]);

$router->get('/settings/microsoft', [MicrosoftController::class, 'edit'], ['can' => 'settings.manage']);
$router->post('/settings/microsoft', [MicrosoftController::class, 'update'], ['can' => 'settings.manage']);
$router->post('/settings/microsoft/test', [MicrosoftController::class, 'test'], ['can' => 'settings.manage']);
$router->post('/settings/microsoft/documents', [MicrosoftController::class, 'documents'], ['can' => 'settings.manage']);
$router->post('/settings/microsoft/transfers', [MicrosoftController::class, 'transfers'], ['can' => 'settings.manage']);
$router->post('/settings/microsoft/calendar', [MicrosoftController::class, 'calendar'], ['can' => 'settings.manage']);
$router->post('/settings/microsoft/reset', [MicrosoftController::class, 'reset'], ['can' => 'settings.manage']);

$router->get('/settings/system', [\Modules\Admin\SystemController::class, 'index'], ['can' => 'settings.manage']);
$router->post('/settings/system/update', [\Modules\Admin\SystemController::class, 'update'], ['can' => 'settings.manage']);
$router->get('/settings/setup', [\Modules\Admin\SystemController::class, 'guide'], ['can' => 'settings.manage']);

$router->get('/settings/mail', [MailController::class, 'edit'], ['can' => 'settings.manage']);
$router->post('/settings/mail', [MailController::class, 'update'], ['can' => 'settings.manage']);
$router->post('/settings/mail/test', [MailController::class, 'test'], ['can' => 'settings.manage']);
