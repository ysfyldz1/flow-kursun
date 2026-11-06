<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Lang;
use App\Core\Router;

session_name(config('session.name', 'flow_session'));
session_set_cookie_params([
    'lifetime' => (int) config('session.lifetime', 28800),
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

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
