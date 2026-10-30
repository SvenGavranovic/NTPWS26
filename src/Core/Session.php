<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sigurno pokretanje i završavanje sesije te "flash" poruke
 * (poruka koja se prikaže jednom, nakon preusmjeravanja).
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Odbij ID sesije koji poslužitelj nije sam izdao (session fixation)
        ini_set('session.use_strict_mode', '1');
        // ID sesije samo u kolačiću, nikad u URL-u
        ini_set('session.use_only_cookies', '1');

        session_name('ZGPRIJAVE');
        session_set_cookie_params([
            'lifetime' => 0,                                    // traje dok je preglednik otvoren
            'path'     => base_path_url() . '/',
            'secure'   => self::isHttps(),                      // na HTTPS-u samo preko HTTPS-a
            'httponly' => true,                                 // JavaScript ne može pročitati kolačić (XSS)
            'samesite' => 'Lax',                                // ne šalje se uz POST s tuđih stranica (CSRF)
        ]);

        session_start();
    }

    /**
     * Potpuno uništava sesiju (podatke na poslužitelju i kolačić),
     * a zatim pokreće novu, praznu sesiju s novim ID-jem.
     */
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

    /** Spremi poruku za sljedeći prikaz stranice. $type: success, danger, warning, info */
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
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
