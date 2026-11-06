<?php

use Modules\Expenses\ExpenseController;

$router->get('/expenses', [ExpenseController::class, 'index']);
$router->post('/expenses', [ExpenseController::class, 'store']);
$router->get('/expenses/{id}/edit', [ExpenseController::class, 'edit']);
$router->post('/expenses/{id}', [ExpenseController::class, 'update']);
$router->post('/expenses/{id}/delete', [ExpenseController::class, 'delete']);
$router->get('/expenses/{id}/receipt', [ExpenseController::class, 'receipt']);
