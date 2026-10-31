<?php

declare(strict_types=1);

namespace App\Core;

/** Sesija i flash poruke. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('ZGPRIJAVE');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path_url() . '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /** Briše podatke i kolačić sesije te pokreće novu sesiju. */
    public static function destroy(): void
    {
        $_SESSION = [];

        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);

        session_destroy();

        self::start();
        session_regenerate_id(true);
    }

    /** $type: success, danger, warning, info */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Vrati i obriši sve flash poruke. */
    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    private static function isHttps(): bool
    {
        // iza lokalnog tunela veza do preglednika je HTTPS iako Apache prima HTTP
        $tunel = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || $tunel;
    }
}
