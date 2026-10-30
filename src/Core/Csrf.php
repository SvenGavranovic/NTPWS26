<?php

declare(strict_types=1);

namespace App\Core;

/** CSRF token po sesiji. */
final class Csrf
{
    private const KEY = '_csrf_token';

    /** Token sesije (vrijedi do odjave ili nove prijave). */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Novi token (nakon prijave). */
    public static function regenerate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    /** Token iz polja _csrf ili zaglavlja X-CSRF-Token. */
    public static function isValid(): bool
    {
        $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $known = $_SESSION[self::KEY] ?? '';

        return is_string($sent) && $known !== '' && hash_equals($known, $sent);
    }
}
