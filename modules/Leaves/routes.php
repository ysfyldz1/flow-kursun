<?php

use Modules\Leaves\HolidayController;
use Modules\Leaves\LeaveController;

$router->get('/leaves', [LeaveController::class, 'index']);
$router->post('/leaves', [LeaveController::class, 'store']);
$router->get('/leaves/days', [LeaveController::class, 'days']);
$router->get('/leaves/balances', [LeaveController::class, 'balances'], ['can' => 'leaves.manage']);
$router->get('/leaves/{id}', [LeaveController::class, 'show']);
$router->post('/leaves/{id}/review', [LeaveController::class, 'review']);
$router->post('/leaves/{id}/cancel', [LeaveController::class, 'cancel']);
$router->post('/leaves/{id}/signed', [LeaveController::class, 'signed']);
$router->get('/leaves/{id}/print', [LeaveController::class, 'print']);
$router->get('/leaves/{id}/file/{kind}', [LeaveController::class, 'file']);

$router->get('/settings/holidays', [HolidayController::class, 'index'], ['can' => 'settings.manage']);
$router->post('/settings/holidays', [HolidayController::class, 'store'], ['can' => 'settings.manage']);
$router->post('/settings/holidays/fixed', [HolidayController::class, 'fixed'], ['can' => 'settings.manage']);
$router->post('/settings/holidays/{date}/delete', [HolidayController::class, 'delete'], ['can' => 'settings.manage']);
