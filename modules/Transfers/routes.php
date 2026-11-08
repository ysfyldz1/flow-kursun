<?php

use Modules\Transfers\PublicTransferController;
use Modules\Transfers\TransferController;

$router->get('/transfers', [TransferController::class, 'index']);
$router->get('/transfers/new', [TransferController::class, 'create']);
$router->post('/transfers', [TransferController::class, 'store']);
$router->get('/transfers/{id}', [TransferController::class, 'show']);
$router->post('/transfers/{id}/session', [TransferController::class, 'session']);
$router->post('/transfers/{id}/file-done', [TransferController::class, 'fileDone']);
$router->post('/transfers/{id}/finalize', [TransferController::class, 'finalize']);
$router->post('/transfers/{id}/expiry', [TransferController::class, 'expiry']);
$router->post('/transfers/{id}/resend', [TransferController::class, 'resend']);
$router->post('/transfers/{id}/delete', [TransferController::class, 'delete']);

$guest = ['auth' => false];
$router->get('/t/{token}', [PublicTransferController::class, 'show'], $guest);
$router->post('/t/{token}', [PublicTransferController::class, 'unlock'], $guest);
$router->get('/t/{token}/file/{id}', [PublicTransferController::class, 'file'], $guest);
$router->get('/t/{token}/all', [PublicTransferController::class, 'all'], $guest);
