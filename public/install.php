<?php

declare(strict_types=1);

// Tarayıcıdan kurulum (SSH gerekmez). Yalnız kurulmamış sistemde açılır; bitince storage/installed.lock ile kapanır.

define('FLOW_INSTALLER', true);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Core\Lang;
use App\Core\View;
use App\Services\Migrator;
use App\Services\Secret;
use App\Services\Settings;

$configFile = BASE_PATH . '/config.php';
$pendingFile = BASE_PATH . '/storage/install.pending';
$lockFile = BASE_PATH . '/storage/installed.lock';
$keyFile = BASE_PATH . '/storage/install.key';

if (is_file($lockFile) || (is_file($configFile) && !is_file($pendingFile))) {
    http_response_code(404);
    exit('Not found');
}
if (!is_writable(BASE_PATH . '/storage')) {
    exit('storage/ klasörü yazılabilir olmalı. / The storage/ folder must be writable.');
}

session_name('flow_install');
session_set_cookie_params(['path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
session_start();

if (is_string($_GET['lang'] ?? null) && in_array($_GET['lang'], ['tr', 'en'], true)) {
    $_SESSION['install_lang'] = $_GET['lang'];
}
Config::load(is_file($configFile) ? require $configFile : ['app' => ['name' => 'Flow', 'locale' => 'tr', 'locales' => ['tr', 'en']]]);
Lang::set($_SESSION['install_lang'] ?? 'tr');

// Sunucuda tek kullanımlık kod: siteyi ilk bulan yabancı kurulumu ele geçiremesin
if (!is_file($keyFile)) {
    file_put_contents($keyFile, bin2hex(random_bytes(4)));
}
$_SESSION['install_csrf'] ??= bin2hex(random_bytes(16));

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$host = preg_replace('/[^a-z0-9.:\-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$isLocal = (bool) preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$|\.test$/', $host);

$checks = ['PHP 8.3+' => PHP_VERSION_ID >= 80300];
foreach (['pdo_mysql', 'curl', 'zip', 'mbstring', 'gd', 'fileinfo', 'sodium'] as $ext) {
    $checks[$ext] = extension_loaded($ext);
}
$checks['HTTPS'] = $isHttps || $isLocal;
$checks['vendor/'] = is_file(BASE_PATH . '/vendor/autoload.php');
$checks['storage/'] = is_writable(BASE_PATH . '/storage/uploads') && is_writable(BASE_PATH . '/storage/logs');
$checks['config.php'] = is_file($configFile) ? is_writable($configFile) : is_writable(BASE_PATH);
$checksOk = !in_array(false, $checks, true);

$error = null;
$done = null;
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['action'] ?? '') : '';
if ($action !== '' && !hash_equals($_SESSION['install_csrf'], (string) ($_POST['_csrf'] ?? ''))) {
    $action = '';
    $error = __('install.session_expired');
}

if ($action === 'code') {
    if (hash_equals(trim((string) file_get_contents($keyFile)), trim((string) ($_POST['code'] ?? '')))) {
        $_SESSION['install_ok'] = true;
    } else {
        sleep(1);
        $error = __('install.code_wrong');
    }
} elseif ($action === 'db' && !empty($_SESSION['install_ok']) && $checksOk && !is_file($configFile)) {
    $db = [
        'host' => trim((string) ($_POST['db_host'] ?? '')) ?: 'localhost',
        'port' => (int) ($_POST['db_port'] ?? 3306) ?: 3306,
        'name' => trim((string) ($_POST['db_name'] ?? '')),
        'user' => trim((string) ($_POST['db_user'] ?? '')),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    try {
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) {
            $error = __('install.db_not_empty');
        }
        unset($pdo);
    } catch (PDOException $e) {
        $error = __('install.db_failed', ['error' => $e->getMessage()]);
    }
    if (!$error) {
        $config = [
            'app' => [
                'name'     => 'Flow',
                'url'      => ($isHttps ? 'https://' : 'http://') . $host,
                'debug'    => false,
                'timezone' => 'Europe/Istanbul',
                'locale'   => 'tr',
                'locales'  => ['tr', 'en'],
            ],
            'db'      => $db,
            'session' => ['name' => 'flow_session', 'lifetime' => 60 * 60 * 8],
        ];
        file_put_contents($pendingFile, date('c'));
        file_put_contents($configFile, "<?php\n\nreturn " . var_export($config, true) . ";\n");
        Config::load($config);
        try {
            Secret::encrypt('init');   // storage/app.key bu kurulum için üretilir
            Migrator::run();
            header('Location: /install.php');
            exit;
        } catch (Throwable $e) {
            error_log((string) $e);
            @unlink($configFile);
            @unlink($pendingFile);
            $error = __('install.migrate_failed', ['error' => $e->getMessage()]);
        }
    }
} elseif ($action === 'setup' && !empty($_SESSION['install_ok']) && is_file($pendingFile)) {
    $name = trim((string) ($_POST['admin_name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['admin_email'] ?? '')));
    $password = (string) ($_POST['admin_password'] ?? '');
    $org = trim((string) ($_POST['org_name'] ?? ''));
    $locale = in_array($_POST['org_locale'] ?? '', ['tr', 'en'], true) ? $_POST['org_locale'] : 'tr';
    $color = strtolower((string) ($_POST['org_color'] ?? ''));

    $error = match (true) {
        $org === '' || $name === ''                => __('install.required'),
        !filter_var($email, FILTER_VALIDATE_EMAIL) => __('validation.email'),
        mb_strlen($password) < 8                   => __('validation.password_min'),
        default                                    => null,
    };
    if (!$error) {
        DB::transaction(function () use ($org, $locale, $color, $name, $email, $password): void {
            Settings::set([
                'org.name'          => $org,
                'org.locale'        => $locale,
                'org.primary_color' => preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#1e3a8a',
            ]);
            DB::insert('users', [
                'role_id'  => (int) DB::value("SELECT id FROM roles WHERE slug = 'admin'"),
                'name'     => $name,
                'email'    => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'locale'   => $locale,
            ]);
        });
        file_put_contents($lockFile, date('c'));
        unlink($pendingFile);
        unlink($keyFile);
        $done = ['cron' => PHP_BINDIR . '/php ' . BASE_PATH . '/bin/cron.php'];
        $_SESSION = [];
    }
}

$step = match (true) {
    $done !== null                 => 'done',
    empty($_SESSION['install_ok']) => 'code',
    !is_file($pendingFile)         => 'db',
    default                        => 'setup',
};

echo View::partial('install', [
    'step'     => $step,
    'error'    => $error,
    'csrf'     => $_SESSION['install_csrf'] ?? '',
    'checks'   => $checks,
    'checksOk' => $checksOk,
    'done'     => $done,
]);
