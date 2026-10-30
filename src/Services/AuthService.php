<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Korisnik;
use PDOException;

/**
 * Poslovna logika registracije i prijave: validacija, hashiranje, pravila.
 * Kontroler samo prima zahtjev i prikazuje rezultat, a model samo izvršava SQL.
 */
final class AuthService
{
    /** Registracijom se UVIJEK dobiva samo ova uloga. Unos iz obrasca se ignorira. */
    private const OSNOVNA_ULOGA = 'korisnik';

    /**
     * Hash nasumičnog niza. Koristi se kad e-mail ne postoji, kako bi prijava
     * trajala jednako dugo kao kod postojećeg e-maila. Inače bi napadač po
     * vremenu odgovora mogao otkriti koji su e-mailovi registrirani.
     */
    private const LAZNI_HASH = '$2y$10$Nmsb6AKRjkZFbH0TX43MMuZAnpPladKqo/VqC7oIV8Ky52N9F76eG';

    private Korisnik $korisnici;

    public function __construct()
    {
        $this->korisnici = new Korisnik();
    }

    /**
     * @param array{ime:string, prezime:string, email:string, lozinka:string, lozinka_potvrda:string} $input
     * @return array<string, string> pogreške po poljima; prazno = uspjeh
     */
    public function register(array $input): array
    {
        $ime     = trim($input['ime']);
        $prezime = trim($input['prezime']);
        $email   = self::normalizeEmail($input['email']);
        $lozinka = $input['lozinka'];
        $potvrda = $input['lozinka_potvrda'];

        $errors = [];

        foreach (['ime' => $ime, 'prezime' => $prezime] as $polje => $vrijednost) {
            if ($greska = self::validateName($vrijednost)) {
                $errors[$polje] = $greska;
            }
        }

        if ($email === '') {
            $errors['email'] = 'Polje je obavezno.';
        } elseif (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Unesite ispravnu e-mail adresu.';
        } elseif ($this->korisnici->emailExists($email)) {
            $errors['email'] = 'Račun s ovom e-mail adresom već postoji.';
        }

        if ($greska = self::validatePassword($lozinka)) {
            $errors['lozinka'] = $greska;
        }
        if ($lozinka !== $potvrda) {
            $errors['lozinka_potvrda'] = 'Lozinke se ne podudaraju.';
        }

        if ($errors !== []) {
            return $errors;
        }

        try {
            $this->korisnici->create($ime, $prezime, $email, password_hash($lozinka, PASSWORD_DEFAULT), self::OSNOVNA_ULOGA);
        } catch (PDOException $e) {
            // 23000 = povreda UNIQUE ograničenja: dva istovremena zahtjeva s istim
            // e-mailom prošla su provjeru emailExists(), ali baza je pustila samo jedan
            if ($e->getCode() === '23000') {
                return ['email' => 'Račun s ovom e-mail adresom već postoji.'];
            }
            throw $e;
        }

        return [];
    }

    /**
     * @return array{ok: bool, korisnik?: array, greska?: string}
     */
    public function attemptLogin(string $email, string $lozinka): array
    {
        $email = self::normalizeEmail($email);
        $korisnik = $email !== '' ? $this->korisnici->findForLogin($email) : null;

        // password_verify se izvodi i kad korisnik ne postoji (vidi LAZNI_HASH)
        $ispravna = password_verify($lozinka, $korisnik['lozinka_hash'] ?? self::LAZNI_HASH);

        if ($korisnik === null || !$ispravna) {
            // Ista poruka za nepostojeći e-mail i pogrešnu lozinku
            return ['ok' => false, 'greska' => 'Neispravan e-mail ili lozinka.'];
        }

        if ((int) $korisnik['aktivan'] !== 1) {
            return ['ok' => false, 'greska' => 'Vaš račun je deaktiviran. Obratite se administratoru.'];
        }

        // Ako se u budućnosti promijeni zadani algoritam ili cijena, hash se tiho obnovi
        if (password_needs_rehash($korisnik['lozinka_hash'], PASSWORD_DEFAULT)) {
            $this->korisnici->updatePasswordHash((int) $korisnik['id'], password_hash($lozinka, PASSWORD_DEFAULT));
        }
        $this->korisnici->updateLastLogin((int) $korisnik['id']);

        return ['ok' => true, 'korisnik' => $korisnik];
    }

    /** Ime ili prezime: 2–50 znakova, slova (i č, ć, đ, š, ž), razmak, crtica, apostrof. */
    public static function validateName(string $vrijednost): ?string
    {
        if ($vrijednost === '') {
            return 'Polje je obavezno.';
        }
        if (mb_strlen($vrijednost) < 2 || mb_strlen($vrijednost) > 50) {
            return 'Unesite između 2 i 50 znakova.';
        }
        if (!preg_match("/^[\p{L}][\p{L} '\-]*$/u", $vrijednost)) {
            return 'Dopuštena su samo slova, razmak, crtica i apostrof.';
        }
        return null;
    }

    /** Vraća poruku pogreške ili null ako je lozinka prihvatljiva. */
    public static function validatePassword(string $lozinka): ?string
    {
        if (mb_strlen($lozinka) < 8) {
            return 'Lozinka mora imati najmanje 8 znakova.';
        }
        // bcrypt koristi samo prva 72 BAJTA – sve iza bi se tiho ignoriralo
        if (strlen($lozinka) > 72) {
            return 'Lozinka smije imati najviše 72 bajta (oko 70 znakova).';
        }
        if (!preg_match('/\p{L}/u', $lozinka) || !preg_match('/\d/', $lozinka)) {
            return 'Lozinka mora sadržavati barem jedno slovo i jednu znamenku.';
        }
        return null;
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
