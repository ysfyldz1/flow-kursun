<?php

use Modules\Files\FileController;

// Dosyalarım: kişinin kendi OneDrive'ı (kullanıcı adına bağlantı); dosyayı projeye gönderme
$router->get('/files', [FileController::class, 'index'], ['can' => 'documents.manage']);
$router->get('/files/browse', [FileController::class, 'browse'], ['can' => 'documents.manage']);
$router->post('/files/send', [FileController::class, 'send'], ['can' => 'documents.manage']);
