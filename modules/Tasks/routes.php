<?php

use Modules\Tasks\TaskController;

$router->get('/tasks', [TaskController::class, 'index']);
$router->post('/tasks', [TaskController::class, 'store']);
$router->get('/tasks/{id}', [TaskController::class, 'show']);
$router->post('/tasks/{id}', [TaskController::class, 'update']);
$router->post('/tasks/{id}/done', [TaskController::class, 'done']);
$router->post('/tasks/{id}/delete', [TaskController::class, 'delete']);
