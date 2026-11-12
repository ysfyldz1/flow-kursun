<?php

use Modules\Billing\BillingController;

$s = ['can' => 'statements.manage'];
$p = ['can' => 'payments.manage'];

$router->get('/billing', [BillingController::class, 'index']);
$router->get('/billing/new', [BillingController::class, 'create'], $s);
$router->get('/billing/new/advanced', [BillingController::class, 'createAdvanced'], $s);
$router->get('/billing/new/project/{id}', [BillingController::class, 'project'], $s);
$router->get('/billing/preview', [BillingController::class, 'preview'], $s);
$router->post('/billing', [BillingController::class, 'store'], $s);
$router->get('/billing/{id}', [BillingController::class, 'show']);
$router->post('/billing/{id}', [BillingController::class, 'update'], $s);
$router->post('/billing/{id}/lines', [BillingController::class, 'addLine'], $s);
$router->post('/billing/{id}/lines/{line}/delete', [BillingController::class, 'removeLine'], $s);
$router->post('/billing/{id}/phases/{phase}/fee', [BillingController::class, 'phaseFee'], $s);
$router->post('/billing/{id}/phases/{phase}/extra', [BillingController::class, 'phaseExtra'], $s);
$router->post('/billing/{id}/lines/{line}/split', [BillingController::class, 'split'], $s);
$router->post('/billing/{id}/issue', [BillingController::class, 'issue'], $s);
$router->post('/billing/{id}/cancel', [BillingController::class, 'cancel'], $s);
$router->get('/billing/{id}/pdf', [BillingController::class, 'pdf']);
$router->post('/billing/{id}/invoice', [BillingController::class, 'uploadInvoice'], $s);
$router->get('/billing/{id}/invoice', [BillingController::class, 'downloadInvoice']);
$router->post('/billing/{id}/payments', [BillingController::class, 'addPayment'], $p);
$router->post('/billing/{id}/payments/{payment}/delete', [BillingController::class, 'deletePayment'], $p);
