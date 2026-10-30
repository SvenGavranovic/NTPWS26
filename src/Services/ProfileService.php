<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Korisnik;

/** Promjene vlastitog profila. */
final class ProfileService
{
    private Korisnik $korisnici;

    public function __construct()
    {
        $this->korisnici = new Korisnik();
    }

    /** @return array<string, string> pogreške po poljima; prazno = uspjeh */
    public function updateName(int $korisnikId, string $ime, string $prezime): array
    {
        $ime = trim($ime);
        $prezime = trim($prezime);

        $errors = array_filter([
            'ime'     => AuthService::validateName($ime),
            'prezime' => AuthService::validateName($prezime),
        ]);

        if ($errors === []) {
            $this->korisnici->updateName($korisnikId, $ime, $prezime);
        }
        return $errors;
    }

    /** @return array<string, string> pogreške po poljima; prazno = uspjeh */
    public function changePassword(int $korisnikId, string $trenutna, string $nova, string $potvrda): array
    {
        $hash = $this->korisnici->findPasswordHash($korisnikId);

        if ($hash === null || !password_verify($trenutna, $hash)) {
            return ['trenutna_lozinka' => 'Trenutna lozinka nije ispravna.'];
        }

        $errors = [];
        if ($greska = AuthService::validatePassword($nova)) {
            $errors['nova_lozinka'] = $greska;
        } elseif (password_verify($nova, $hash)) {
            $errors['nova_lozinka'] = 'Nova lozinka mora biti različita od trenutne.';
        }
        if ($nova !== $potvrda) {
            $errors['nova_lozinka_potvrda'] = 'Lozinke se ne podudaraju.';
        }

        if ($errors === []) {
            $this->korisnici->updatePasswordHash($korisnikId, password_hash($nova, PASSWORD_DEFAULT));
        }
        return $errors;
    }
}
