<?php

declare(strict_types=1);

/*
 * Front controller – JEDINA PHP datoteka dostupna iz preglednika.
 * Svaki zahtjev prolazi ovim redom:
 *   1. učitavanje klasa i konfiguracije (.env)
 *   2. obrada pogrešaka i sigurnosna zaglavlja
 *   3. pokretanje sesije
 *   4. router pronalazi rutu, provjerava CSRF/prijavu/ulogu i poziva kontroler
 */

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
// Detalji pogreške prikazuju se samo u razvoju (APP_DEBUG=true).
// Korisnik inače dobiva generičku poruku, a detalji idu u storage/logs.
ini_set('display_errors', is_debug() ? '1' : '0');
error_reporting(E_ALL);

// Upozorenja (warning/notice) pretvaramo u iznimke da se ne bi tiho ignorirala.
set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) {
        return false; // pogreška je namjerno utišana operatorom @
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    log_error($e);
    if (is_debug()) {
        http_response_code(500);
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    try {
        View::error(500);
    } catch (Throwable) {
        // Ako ni stranica pogreške ne može nastati (npr. baza ne radi), običan tekst
        http_response_code(500);
        echo 'Došlo je do pogreške. Pokušajte ponovno kasnije.';
    }
});

// Sigurnosna zaglavlja za svaki odgovor
header('X-Content-Type-Options: nosniff');           // preglednik ne pogađa vrstu sadržaja
header('X-Frame-Options: DENY');                     // zaštita od clickjackinga (iframe)
header('Referrer-Policy: same-origin');
header_remove('X-Powered-By');                       // ne otkrivamo verziju PHP-a napadaču

// --- 3. Sesija --------------------------------------------------------------
Session::start();

// --- 4. Usmjeravanje --------------------------------------------------------
$router = new Router();
require BASE_PATH . '/config/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], request_path());
