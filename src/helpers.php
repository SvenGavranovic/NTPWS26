<?php

declare(strict_types=1);

/*
 * Male globalne pomoćne funkcije koje se koriste u pogledima i kontrolerima.
 * Učitava ih Composer (autoload → files).
 */

use App\Core\Csrf;

/** Escaping za HTML ispis – zaštita od XSS-a. Koristi se za SVAKI korisnički podatak u pogledu. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Putanja aplikacije unutar poslužitelja, npr. "/zagreb-prijave" (bez kose crte na kraju). */
function base_path_url(): string
{
    return rtrim((string) parse_url($_ENV['APP_URL'], PHP_URL_PATH), '/');
}

/** Gradi URL unutar aplikacije: url('/racun/prijava') → "/zagreb-prijave/racun/prijava". */
function url(string $path = '/'): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}

/** URL datoteke iz public/assets. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Putanja trenutnog zahtjeva bez osnovne putanje i query stringa.
 * "/zagreb-prijave/racun/prijava?x=1" → "/racun/prijava"
 */
function request_path(): string
{
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = base_path_url();

    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    // Ako netko otvori izravno /public/..., tretiramo isto
    if (str_starts_with($path, '/public')) {
        $path = substr($path, strlen('/public'));
    }

    $path = '/' . trim($path, '/');
    return $path === '/index.php' ? '/' : $path;
}

/** Preusmjeravanje unutar aplikacije (303 = nakon POST-a idi na GET). */
function redirect(string $path, int $status = 303): never
{
    header('Location: ' . url($path), true, $status);
    exit;
}

/**
 * Tekstualna vrijednost iz POST obrasca. Ako netko pošalje niz (npr. email[]=x)
 * umjesto teksta, vraća se prazan string umjesto da aplikacija pukne.
 */
function post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Skriveno CSRF polje za svaki obrazac koji šalje POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function is_debug(): bool
{
    return filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
}

/** Zapis pogreške u storage/logs/app.log (nikad korisniku). */
function log_error(Throwable $e): void
{
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = sprintf("[%s] %s: %s u %s:%d\n", date('Y-m-d H:i:s'), $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
    error_log($line, 3, $dir . '/app.log');
}
