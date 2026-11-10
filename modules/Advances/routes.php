<?php

use Modules\Advances\AdvanceController as A;

$c = ['can' => 'payments.manage'];

$router->get('/advances', [A::class, 'index'], $c);
$router->get('/clients/{id}/advance', [A::class, 'show'], $c);
$router->get('/clients/{id}/advance/pdf', [A::class, 'pdf'], $c);
$router->post('/clients/{id}/advance', [A::class, 'store'], $c);
$router->post('/clients/{id}/advance/toggle', [A::class, 'toggle'], $c);
$router->post('/clients/{id}/advance/{entry}/delete', [A::class, 'delete'], $c);

$router->get('/portal/advance', [A::class, 'portal'], ['portal' => true]);
$router->get('/portal/advance/pdf', [A::class, 'portalPdf'], ['portal' => true]);
