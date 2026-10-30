<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router: povezuje HTTP metodu + putanju s metodom kontrolera.
 *
 * Opcije rute (provjeravaju se PRIJE poziva kontrolera):
 *   'auth'  => true              samo za prijavljene
 *   'uloge' => ['admin', ...]    samo za navedene uloge (podrazumijeva 'auth')
 *   'gost'  => true              samo za neprijavljene (npr. obrazac za prijavu)
 *
 * Svaki zahtjev koji mijenja podatke (POST/PUT/PATCH/DELETE) mora imati
 * ispravan CSRF token – bez iznimke, pa ga je nemoguće zaboraviti na pojedinoj ruti.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:array, options:array}> */
    private array $routes = [];

    public function get(string $path, array $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, array $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    private function add(string $method, string $path, array $handler, array $options): void
    {
        // "/vijesti/{id}" → regex "#^/vijesti/(?P<id>\d+)$#"
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $path);

        $this->routes[] = [
            'method'  => $method,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'options' => $options,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            $allowed[] = $route['method'];

            if ($route['method'] !== $method) {
                continue;
            }

            // Parametri iz putanje ({id}) kao cijeli brojevi
            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            if (!$this->passesGuards($method, $route['options'])) {
                return;
            }

            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }

        // Putanja postoji, ali ne za ovu metodu → 405, inače 404
        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            View::error(405);
            return;
        }
        View::error(404);
    }

    /** Vraća false ako zahtjev ne smije proći (i već je poslao odgovor). */
    private function passesGuards(string $method, array $options): bool
    {
        if ($method !== 'GET' && !Csrf::isValid()) {
            View::error(403, 'Sigurnosni token nije ispravan ili je istekao. Osvježite stranicu i pokušajte ponovno.');
            return false;
        }

        if (!empty($options['gost']) && Auth::check()) {
            redirect('/');
        }

        $uloge = $options['uloge'] ?? [];

        if ((!empty($options['auth']) || $uloge !== []) && !Auth::check()) {
            Session::flash('warning', 'Za pristup ovoj stranici morate se prijaviti.');
            redirect('/racun/prijava');
        }

        if ($uloge !== [] && !Auth::hasRole(...$uloge)) {
            View::error(403);
            return false;
        }

        return true;
    }
}
