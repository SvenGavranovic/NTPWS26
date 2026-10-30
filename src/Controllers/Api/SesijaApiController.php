<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Core\Auth;
use App\Core\Csrf;
use App\Services\AuthService;

/**
 * /api/v1/sesija – prijava i odjava za API klijente.
 * GET daje CSRF token, koji se šalje u zaglavlju X-CSRF-Token uz svaki POST/PATCH/DELETE.
 */
final class SesijaApiController
{
    public function show(): void
    {
        $korisnik = Auth::user();
        Api::ok([
            'prijavljen' => $korisnik !== null,
            'korisnik'   => $korisnik ? self::korisnik($korisnik) : null,
            'csrf'       => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        $body = Api::body();
        if ($body === null || !Api::validate($body, 'sesija')) {
            return;
        }

        $result = (new AuthService())->attemptLogin($body->email, $body->lozinka, $_SERVER['REMOTE_ADDR'] ?? '');
        if (!$result['ok']) {
            if (!empty($result['blokirano'])) {
                header('Retry-After: ' . (AuthService::PROZOR_MINUTA * 60));
            }
            Api::error(empty($result['blokirano']) ? 401 : 429, $result['greska']);
            return;
        }

        Auth::login($result['korisnik']);
        Api::ok(['korisnik' => self::korisnik(Auth::user()), 'csrf' => Csrf::token()]);
    }

    public function destroy(): void
    {
        Auth::logout();
        Api::noContent();
    }

    private static function korisnik(array $k): array
    {
        return [
            'id'      => (int) $k['id'],
            'ime'     => $k['ime'],
            'prezime' => $k['prezime'],
            'email'   => $k['email'],
            'uloga'   => $k['uloga'],
        ];
    }
}
