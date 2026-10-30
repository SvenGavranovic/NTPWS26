<?php

declare(strict_types=1);

/*
 * Front controller.
 */

use App\Core\Api;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// --- 1. Konfiguracija -------------------------------------------------------
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();
$dotenv->required(['APP_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Zagreb');

// --- 2. Pogreške ------------------------------------------------------------
// Detalji pogreške samo uz APP_DEBUG=true.
ini_set('display_errors', is_debug() ? '1' : '0');
error_reporting(E_ALL);

// Upozorenja pretvaramo u iznimke.
set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) {
        return false; // pogreška je namjerno utišana operatorom @
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    log_error($e);
    if (Api::isApiPath(request_path())) {
        Api::error(500, 'Došlo je do pogreške na poslužitelju.', is_debug() ? ['debug' => $e->getMessage()] : []);
        return;
    }
    if (is_debug()) {
        http_response_code(500);
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    try {
        View::error(500);
    } catch (Throwable) {
        // npr. baza nije dostupna
        http_response_code(500);
        echo 'Došlo je do pogreške. Pokušajte ponovno kasnije.';
    }
});

// Sigurnosna zaglavlja
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header_remove('X-Powered-By');

// --- 3. Sesija --------------------------------------------------------------
Session::start();

// --- 4. Usmjeravanje --------------------------------------------------------
$router = new Router();
require BASE_PATH . '/config/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], request_path());
