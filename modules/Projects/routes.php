<?php

use Modules\Projects\DocumentController;
use Modules\Projects\EmailController;
use Modules\Projects\PhaseController;
use Modules\Projects\ProjectController;

$router->get('/projects', [ProjectController::class, 'index'], ['can' => 'projects.view']);
$router->get('/projects/new', [ProjectController::class, 'create'], ['can' => 'projects.edit']);
$router->post('/projects', [ProjectController::class, 'store'], ['can' => 'projects.edit']);
$router->get('/projects/{id}', [ProjectController::class, 'show'], ['can' => 'projects.view']);
$router->get('/projects/{id}/edit', [ProjectController::class, 'edit'], ['can' => 'projects.edit']);
$router->post('/projects/{id}', [ProjectController::class, 'update'], ['can' => 'projects.edit']);

$router->get('/projects/{id}/documents', [DocumentController::class, 'index'], ['can' => 'projects.view']);
$router->post('/projects/{id}/documents/folder', [DocumentController::class, 'createFolder'], ['can' => 'documents.manage']);
$router->post('/projects/{id}/documents/from-onedrive', [DocumentController::class, 'fromOneDrive'], ['can' => 'documents.manage']);
$router->post('/projects/{id}/documents/upload', [DocumentController::class, 'upload'], ['can' => 'documents.manage']);
$router->get('/projects/{id}/documents/{item}/download', [DocumentController::class, 'download'], ['can' => 'projects.view']);
$router->get('/projects/{id}/documents/{item}/open', [DocumentController::class, 'open'], ['can' => 'projects.view']);
$router->post('/projects/{id}/documents/{item}/share', [DocumentController::class, 'share'], ['can' => 'documents.manage']);

// Yazışmalar: kişinin kendi posta kutusundan projeye e-posta kaydetme
$router->get('/projects/{id}/emails/search', [EmailController::class, 'search'], ['can' => 'documents.manage']);
$router->post('/projects/{id}/emails', [EmailController::class, 'store'], ['can' => 'documents.manage']);
$router->post('/projects/{id}/emails/{email}/delete', [EmailController::class, 'delete'], ['can' => 'documents.manage']);

// Kurşun: aşamalar

$router->get('/projects/{id}/phases', [PhaseController::class, 'index'], ['can' => 'projects.view']);
$router->post('/projects/{id}/phases', [PhaseController::class, 'store'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/import', [PhaseController::class, 'importUpload'], ['can' => 'projects.edit']);
$router->get('/projects/{id}/phases/import', [PhaseController::class, 'importPreview'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/import/save', [PhaseController::class, 'importSave'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/import/cancel', [PhaseController::class, 'importCancel'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/settings', [PhaseController::class, 'settings'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/{phase}', [PhaseController::class, 'update'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/{phase}/delete', [PhaseController::class, 'delete'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/{phase}/move', [PhaseController::class, 'move'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/{phase}/approve', [PhaseController::class, 'approve'], ['can' => 'projects.edit']);
$router->post('/projects/{id}/phases/{phase}/complete', [PhaseController::class, 'complete'], ['can' => 'projects.edit']);
