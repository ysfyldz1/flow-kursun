<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $map = [
        'App\\'     => BASE_PATH . '/app/',
        'Modules\\' => BASE_PATH . '/modules/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';   // yalnızca dompdf (PDF üretimi)
}
require BASE_PATH . '/app/helpers.php';

$configFile = BASE_PATH . '/config.php';
if (!is_file($configFile)) {
    exit('config.php bulunamadı. config.example.php dosyasını kopyalayın.');
}
App\Core\Config::load(require $configFile);

date_default_timezone_set(config('app.timezone', 'Europe/Istanbul'));

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
}
