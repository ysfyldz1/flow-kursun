<?php

use Modules\Projects\ProjectController;

$router->get('/projects', [ProjectController::class, 'index'], ['can' => 'projects.view']);
$router->get('/projects/new', [ProjectController::class, 'create'], ['can' => 'projects.edit']);
$router->post('/projects', [ProjectController::class, 'store'], ['can' => 'projects.edit']);
$router->get('/projects/{id}', [ProjectController::class, 'show'], ['can' => 'projects.view']);
$router->get('/projects/{id}/edit', [ProjectController::class, 'edit'], ['can' => 'projects.edit']);
$router->post('/projects/{id}', [ProjectController::class, 'update'], ['can' => 'projects.edit']);
