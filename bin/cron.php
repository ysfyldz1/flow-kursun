<?php

// Günlük zamanlanmış işler. cPanel → Cron Jobs'a günde bir kez eklenir:
//   /usr/local/bin/php /home/KULLANICI/flow/bin/cron.php
// Yeni günlük iş olursa buraya eklenir; kurulumda tek cron yeterli kalır.

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Settings;
use App\Services\TransferService;

if (PHP_SAPI !== 'cli') {
    exit;
}

$transfers = TransferService::cleanup();
echo sprintf("Transfer: %d süresi dolan, %d yarım kalan silindi, %d hata\n", $transfers['expired'], $transfers['abandoned'], $transfers['failed']);

// Ayarlar ekranı cron'un gerçekten çalıştığını buradan gösterir
Settings::set(['cron.last_run' => date('Y-m-d H:i:s')]);
