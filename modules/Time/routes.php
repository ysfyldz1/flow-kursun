<?php

use Modules\Time\TimeController;

$router->get('/time', [TimeController::class, 'index']);
$router->post('/time', [TimeController::class, 'store']);
$router->get('/time/{id}/edit', [TimeController::class, 'edit']);
$router->post('/time/{id}', [TimeController::class, 'update']);
$router->post('/time/{id}/delete', [TimeController::class, 'delete']);
$router->post('/timer/start', [TimeController::class, 'startTimer']);
$router->post('/timer/stop', [TimeController::class, 'stopTimer']);
$router->post('/timer/discard', [TimeController::class, 'discardTimer']);
