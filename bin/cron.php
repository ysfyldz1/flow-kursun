<?php

// Zamanlanmış işler. cPanel → Cron Jobs'a SAATTE BİR eklenir (dakika 0, saat *):
//   /usr/local/bin/php /home/KULLANICI/public_html/SITE/bin/cron.php >/dev/null 2>&1
// Hangi işin ne zaman çalışacağı App\Services\Scheduler'da; yeni iş için cron satırı değişmez.

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit;
}

foreach (\App\Services\Scheduler::run() as $job => $result) {
    echo "$job: $result\n";
}
