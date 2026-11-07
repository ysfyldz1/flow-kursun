<?php

use Modules\Meetings\MeetingController;
use Modules\Meetings\PublicMeetingController;

$can = ['can' => 'meetings.manage'];
$router->get('/meetings', [MeetingController::class, 'index'], $can);
$router->get('/meetings/new', [MeetingController::class, 'create'], $can);
$router->post('/meetings', [MeetingController::class, 'store'], $can);
$router->post('/meetings/import', [MeetingController::class, 'import'], $can);
$router->get('/meetings/{id}', [MeetingController::class, 'show'], $can);
$router->get('/meetings/{id}/edit', [MeetingController::class, 'edit'], $can);
$router->post('/meetings/{id}', [MeetingController::class, 'update'], $can);
$router->post('/meetings/{id}/toggle', [MeetingController::class, 'toggle'], $can);
$router->post('/meetings/{id}/delete', [MeetingController::class, 'delete'], $can);
$router->post('/meetings/{id}/reset/{shareholder}', [MeetingController::class, 'reset'], $can);
$router->get('/meetings/{id}/csv', [MeetingController::class, 'csv'], $can);
$router->get('/meetings/{id}/doc', [MeetingController::class, 'doc'], $can);
$router->get('/meetings/{id}/proxy/{shareholder}', [MeetingController::class, 'proxy'], $can);

// Pay sahiplerinin oturumsuz doldurduğu form
$guest = ['auth' => false];
$router->get('/gk/{token}', [PublicMeetingController::class, 'show'], $guest);
$router->post('/gk/{token}', [PublicMeetingController::class, 'submit'], $guest);
$router->get('/gk/{token}/doc', [PublicMeetingController::class, 'doc'], $guest);
$router->get('/gk/{token}/proxy/{doc}', [PublicMeetingController::class, 'proxy'], $guest);
