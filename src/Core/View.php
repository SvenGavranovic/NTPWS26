<?php

declare(strict_types=1);

namespace App\Core;

/** Prikaz predložaka iz views/ unutar layout.php. */
final class View
{
    public static function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);

        $content = self::capture($view, $data);
        echo self::capture('layout', $data + ['content' => $content]);
    }

    /** Stranica pogreške bez tehničkih detalja. */
    public static function error(int $status, ?string $message = null): void
    {
        $messages = [
            403 => 'Nemate ovlasti za pristup ovoj stranici.',
            404 => 'Tražena stranica ne postoji.',
            405 => 'Ova radnja nije dopuštena.',
            500 => 'Došlo je do pogreške. Pokušajte ponovno kasnije.',
        ];

        self::render('error', [
            'title'   => 'Pogreška ' . $status,
            'status'  => $status,
            'message' => $message ?? $messages[$status] ?? 'Došlo je do pogreške.',
        ], $status);
    }

    private static function capture(string $view, array $data): string
    {
        $file = BASE_PATH . '/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Pogled ne postoji: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
