<?php

use Modules\Dashboard\DashboardController;

$router->get('/', [DashboardController::class, 'index']);
