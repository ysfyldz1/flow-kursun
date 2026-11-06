<?php

use Modules\Reports\ReportController;

$r = ['can' => 'reports.view'];
$router->get('/reports', fn () => redirect('/reports/time'), $r);
$router->get('/reports/time', [ReportController::class, 'time'], $r);
$router->get('/reports/unbilled', [ReportController::class, 'unbilled'], $r);
$router->get('/reports/aging', [ReportController::class, 'aging'], $r);
$router->get('/reports/collections', [ReportController::class, 'collections'], $r);
