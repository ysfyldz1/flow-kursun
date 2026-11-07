<?php

use Modules\Projects\DocumentController;
use Modules\Projects\ProjectController;

$router->get('/projects', [ProjectController::class, 'index'], ['can' => 'projects.view']);
$router->get('/projects/new', [ProjectController::class, 'create'], ['can' => 'projects.edit']);
$router->post('/projects', [ProjectController::class, 'store'], ['can' => 'projects.edit']);
$router->get('/projects/{id}', [ProjectController::class, 'show'], ['can' => 'projects.view']);
$router->get('/projects/{id}/edit', [ProjectController::class, 'edit'], ['can' => 'projects.edit']);
$router->post('/projects/{id}', [ProjectController::class, 'update'], ['can' => 'projects.edit']);

$router->get('/projects/{id}/documents', [DocumentController::class, 'index'], ['can' => 'projects.view']);
$router->post('/projects/{id}/documents/folder', [DocumentController::class, 'createFolder'], ['can' => 'documents.manage']);
$router->post('/projects/{id}/documents/upload', [DocumentController::class, 'upload'], ['can' => 'documents.manage']);
$router->get('/projects/{id}/documents/{item}/download', [DocumentController::class, 'download'], ['can' => 'projects.view']);
$router->get('/projects/{id}/documents/{item}/open', [DocumentController::class, 'open'], ['can' => 'projects.view']);
$router->post('/projects/{id}/documents/{item}/share', [DocumentController::class, 'share'], ['can' => 'documents.manage']);
