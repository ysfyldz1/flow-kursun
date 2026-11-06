<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
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

// Dil: oturumdaki kullanıcının tercihi, yoksa kurum varsayılanı
Lang::set($_SESSION['locale'] ?? Auth::user()['locale'] ?? config('app.locale', 'tr'));

$router = new Router();
foreach (glob(BASE_PATH . '/modules/*/routes.php') as $routes) {
    require $routes;
}

// Önceki istekte kalan form değerleri yalnızca bu istekte okunur
$GLOBALS['__old'] = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
