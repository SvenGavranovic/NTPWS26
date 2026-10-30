<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Opcije rute: 'auth' (prijavljeni), 'uloge' => [...], 'gost' (neprijavljeni).
 * Zahtjevi koji nisu GET moraju imati ispravan CSRF token.
 * Za putanje /api/... pogreške su JSON, a neprijavljeni dobiva 401 umjesto preusmjeravanja.
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

    public function patch(string $path, array $handler, array $options = []): void
    {
        $this->add('PATCH', $path, $handler, $options);
    }

    public function delete(string $path, array $handler, array $options = []): void
    {
        $this->add('DELETE', $path, $handler, $options);
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
        $api = Api::isApiPath($path);
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            $allowed[] = $route['method'];

            if ($route['method'] !== $method) {
                continue;
            }

            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            if (!$this->passesGuards($method, $route['options'], $api)) {
                return;
            }

            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }

        // 405 ako putanja postoji za drugu metodu, inače 404
        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            $api ? Api::error(405, 'Metoda ' . $method . ' nije dopuštena za ovaj resurs.') : View::error(405);
            return;
        }
        $api ? Api::error(404, 'Resurs ne postoji.') : View::error(404);
    }

    /** false = zahtjev je odbijen (odgovor je već poslan). */
    private function passesGuards(string $method, array $options, bool $api): bool
    {
        if ($method !== 'GET' && !Csrf::isValid()) {
            $poruka = 'Sigurnosni token nije ispravan ili je istekao. Osvježite stranicu i pokušajte ponovno.';
            $api ? Api::error(403, 'CSRF token nedostaje ili nije ispravan (zaglavlje X-CSRF-Token).') : View::error(403, $poruka);
            return false;
        }

        if (!empty($options['gost']) && Auth::check()) {
            redirect('/');
        }

        $uloge = $options['uloge'] ?? [];

        if ((!empty($options['auth']) || $uloge !== []) && !Auth::check()) {
            if ($api) {
                Api::error(401, 'Potrebna je prijava.');
                return false;
            }
            Session::flash('warning', 'Za pristup ovoj stranici morate se prijaviti.');
            redirect('/racun/prijava');
        }

        if ($uloge !== [] && !Auth::hasRole(...$uloge)) {
            $api ? Api::error(403, 'Nemate ovlasti za ovu radnju.') : View::error(403);
            return false;
        }

        return true;
    }
}
