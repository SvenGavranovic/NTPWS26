<?php

declare(strict_types=1);

/* Globalne pomoćne funkcije. */

use App\Core\Csrf;

/** HTML escaping. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** npr. "/zagreb-prijave" */
function base_path_url(): string
{
    return rtrim((string) parse_url($_ENV['APP_URL'], PHP_URL_PATH), '/');
}

/** url('/racun/prijava') → "/zagreb-prijave/racun/prijava" */
function url(string $path = '/'): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}

/** URL datoteke iz public/assets. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    // ?v=<vrijeme izmjene>: nakon promjene datoteke preglednik ne koristi staru kopiju
    $datoteka = BASE_PATH . '/public/assets/' . $path;
    return url('assets/' . $path) . (is_file($datoteka) ? '?v=' . filemtime($datoteka) : '');
}

/** Putanja zahtjeva bez osnovne putanje i query stringa. */
function request_path(): string
{
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = base_path_url();

    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (str_starts_with($path, '/public')) {
        $path = substr($path, strlen('/public'));
    }

    $path = '/' . trim($path, '/');
    return $path === '/index.php' ? '/' : $path;
}

function redirect(string $path, int $status = 303): never
{
    header('Location: ' . url($path), true, $status);
    exit;
}

/** Tekst iz POST-a; za niz (polje[]=) vraća prazan string. */
function post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Kao post(), za query string. */
function get(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Pozitivan cijeli broj iz query stringa ili null. */
function get_int(string $key): ?int
{
    $value = filter_var(get($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? null : $value;
}

/** "2026-10-05 08:00:00" → "5. 10. 2026." */
function datum(?string $value, bool $sVremenom = false): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return (new DateTimeImmutable($value))->format($sVremenom ? 'j. n. Y. \u H:i' : 'j. n. Y.');
}

function nav_link(string $path, string $label): string
{
    $current = request_path();
    $active = $current === $path || ($path !== '/' && str_starts_with($current, $path . '/'));

    return sprintf(
        '<li class="nav-item"><a class="nav-link%s" href="%s"%s>%s</a></li>',
        $active ? ' active' : '',
        e(url($path)),
        $active ? ' aria-current="page"' : '',
        e($label)
    );
}

/** IP klijenta; iza lokalnog tunela (cloudflared) iz zaglavlja CF-Connecting-IP, samo za zahtjeve s localhosta. */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (in_array($ip, ['127.0.0.1', '::1'], true) && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    return $ip;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function is_debug(): bool
{
    return filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
}

/** Zapis u storage/logs/app.log. */
function log_error(Throwable $e): void
{
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = sprintf("[%s] %s: %s u %s:%d\n", date('Y-m-d H:i:s'), $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
    error_log($line, 3, $dir . '/app.log');
}
