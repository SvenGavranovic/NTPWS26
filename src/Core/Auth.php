<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Korisnik;

/**
 * Prijavljeni korisnik. U sesiji je samo ID; uloga i status čitaju se iz baze
 * pri svakom zahtjevu.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = $_SESSION['korisnik_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }

        $korisnik = (new Korisnik())->findById($id);

        if ($korisnik === null || (int) $korisnik['aktivan'] !== 1) {
            // obrisan ili deaktiviran račun
            Session::destroy();
            Session::flash('warning', 'Vaš račun nije aktivan. Obratite se administratoru.');
            return null;
        }

        return self::$user = $korisnik;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Ima li korisnik jednu od uloga (po kodu). */
    public static function hasRole(string ...$uloge): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['uloga'], $uloge, true);
    }

    public static function login(array $korisnik): void
    {
        session_regenerate_id(true);
        $_SESSION['korisnik_id'] = (int) $korisnik['id'];
        Csrf::regenerate();

        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }
}
