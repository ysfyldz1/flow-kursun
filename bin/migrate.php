<?php

// Kullanım: php bin/migrate.php
// database/migrations içindeki henüz çalışmamış .sql dosyalarını sırayla uygular.

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
    name VARCHAR(190) PRIMARY KEY,
    ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$done = array_column(DB::all('SELECT name FROM migrations'), 'name');
$files = glob(BASE_PATH . '/database/migrations/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    echo "→ $name\n";
    $pdo->exec(file_get_contents($file));
    DB::insert('migrations', ['name' => $name]);
    $count++;
}

echo $count ? "$count migration uygulandı.\n" : "Yeni migration yok.\n";
