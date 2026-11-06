<?php

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
