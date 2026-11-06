<?php

use Modules\Calendar\CalendarController;

$router->get('/calendar', [CalendarController::class, 'index'], ['can' => 'calendar.view']);
$router->get('/calendar/new', [CalendarController::class, 'create'], ['can' => 'calendar.edit']);
$router->post('/calendar', [CalendarController::class, 'store'], ['can' => 'calendar.edit']);
$router->get('/calendar/{id}', [CalendarController::class, 'show'], ['can' => 'calendar.view']);
$router->get('/calendar/{id}/edit', [CalendarController::class, 'edit'], ['can' => 'calendar.edit']);
$router->post('/calendar/{id}', [CalendarController::class, 'update'], ['can' => 'calendar.edit']);
$router->post('/calendar/{id}/delete', [CalendarController::class, 'delete'], ['can' => 'calendar.edit']);
$router->post('/calendar/{id}/done', [CalendarController::class, 'done'], ['can' => 'calendar.edit']);
$router->post('/calendar/{id}/resync', [CalendarController::class, 'resync'], ['can' => 'calendar.edit']);
