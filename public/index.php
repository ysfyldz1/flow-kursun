<?php

declare(strict_types=1);

// PHP'nin yerleşik geliştirme sunucusunda gerçek dosyaları (sw.js, css) doğrudan sun
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Lang;
use App\Core\Router;

\App\Core\SessionPolicy::start();

// Dil: girişte kullanıcının tercihi oturuma yazılır; yoksa kurum varsayılanı
Lang::set($_SESSION['locale'] ?? config('app.locale', 'tr'));

$router = new Router();
foreach (glob(BASE_PATH . '/modules/*/routes.php') as $routes) {
    require $routes;
}

// Önceki istekte kalan form değerleri ve hatalar yalnızca bu istekte okunur
$GLOBALS['__old'] = $_SESSION['_old'] ?? [];
$GLOBALS['__errors'] = $_SESSION['_errors'] ?? [];
unset($_SESSION['_old'], $_SESSION['_errors']);

// Geçersiz UTF-8 içeren istekler veritabanına ulaşmadan reddedilir
array_walk_recursive($_POST, function ($v) {
    if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
        abort(400);
    }
});

try {
    \App\Core\SessionPolicy::enforce();
    if (!isset($_SESSION['locale'])) {
        Lang::set((string) \App\Services\Settings::get('org.locale', config('app.locale', 'tr')));
    }
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (\PDOException $e) {
    error_log((string) $e);
    if (config('app.debug') && !str_contains($e->getMessage(), '[2002]')) {
        throw $e;
    }
    abort(503);
}
