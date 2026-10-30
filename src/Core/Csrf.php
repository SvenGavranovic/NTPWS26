<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF zaštita (Cross-Site Request Forgery).
 *
 * Problem: tuđa stranica može natjerati preglednik prijavljenog korisnika da
 * pošalje POST na našu aplikaciju, a preglednik uz njega automatski šalje i kolačić sesije.
 * Rješenje: svaki obrazac nosi tajni token iz sesije. Tuđa stranica ne zna token,
 * pa njezin zahtjev odbijamo.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    /** Token sesije – stvara se jednom i vrijedi do odjave ili nove prijave. */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32)); // 256 bita kriptografski sigurne slučajnosti
        }
        return $_SESSION[self::KEY];
    }

    /** Novi token (poziva se nakon prijave). */
    public static function regenerate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    /**
     * Provjera tokena iz obrasca (_csrf) ili iz zaglavlja X-CSRF-Token
     * (zaglavlje će koristiti fetch() pozivi prema API-ju).
     */
    public static function isValid(): bool
    {
        $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $known = $_SESSION[self::KEY] ?? '';

        // hash_equals uspoređuje u konstantnom vremenu (bez curenja informacija kroz vrijeme)
        return is_string($sent) && $known !== '' && hash_equals($known, $sent);
    }
}
