<?php

// Kullanım: php bin/migrate.php
// database/migrations içindeki henüz çalışmamış .sql dosyalarını sırayla uygular.

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Migrator;

$ran = Migrator::run();
foreach ($ran as $name) {
    echo "→ $name\n";
}
echo $ran ? count($ran) . " migration uygulandı.\n" : "Yeni migration yok.\n";
