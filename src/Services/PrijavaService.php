<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\PovijestStatusa;
use App\Models\Prijava;
use App\Models\PrijavaSlika;
use App\Models\Sifrarnik;
use Throwable;

/** Prijave problema: nova prijava i pravila pristupa. */
final class PrijavaService
{
    public const MAPA_SLIKA = 'prijave';
    public const MAX_SLIKA = 5 * 1024 * 1024;

    public const MAX_PRIJAVA = 10;
    public const PROZOR_MINUTA = 60;

    /**
     * @return array{id?: int, errors: array<string, string>, previse?: bool}
     */
    public function create(int $korisnikId, array $input, ?array $slika): array
    {
        if ((new Prijava())->countRecentByUser($korisnikId, self::PROZOR_MINUTA) >= self::MAX_PRIJAVA) {
            return ['errors' => ['opce' => 'Poslali ste previše prijava u kratkom vremenu. Pokušajte ponovno kasnije.'], 'previse' => true];
        }

        [$data, $errors] = $this->validate($input);

        if ($errors === []) {
            $upload = ImageUpload::save($slika, self::MAPA_SLIKA, self::MAX_SLIKA);
            if ($upload === []) {
                $errors['slika'] = 'Fotografija problema je obavezna.';
            } elseif (isset($upload['greska'])) {
                $errors['slika'] = $upload['greska'];
            }
        } elseif (!isset($slika['error']) || $slika['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['slika'] = 'Fotografija problema je obavezna.';
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $id = (new Prijava())->create($korisnikId, $data);
            (new PrijavaSlika())->add($id, $korisnikId, 'prije', $upload['ime'], $upload['mime'], $upload['velicina']);
            (new PovijestStatusa())->add($id, $korisnikId, null, 'PRIJAVLJENO');
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            ImageUpload::delete($upload['ime'], self::MAPA_SLIKA);
            throw $e;
        }

        return ['id' => $id, 'errors' => []];
    }

    /** Podnositelj, admin i dodijeljeni radnik. */
    public static function canView(array $korisnik, array $prijava): bool
    {
        return $korisnik['uloga'] === 'admin'
            || (int) $prijava['korisnik_id'] === (int) $korisnik['id']
            || ($korisnik['uloga'] === 'radnik' && (int) ($prijava['radnik_id'] ?? 0) === (int) $korisnik['id']);
    }

    /** Admin ili dodijeljeni radnik. */
    public static function isStaff(array $korisnik, array $prijava): bool
    {
        return $korisnik['uloga'] === 'admin'
            || ($korisnik['uloga'] === 'radnik' && (int) ($prijava['radnik_id'] ?? 0) === (int) $korisnik['id']);
    }

    /** Slike prijave koje korisnik smije vidjeti; građaninu samo zadnja slika "poslije". */
    public static function visibleImages(array $korisnik, array $prijava, array $slike): array
    {
        $slike = array_values(array_filter($slike, static fn (array $s): bool => self::canSeeImage($korisnik, $prijava, $s)));
        if (!self::isStaff($korisnik, $prijava)) {
            $poslije = array_keys(array_filter($slike, static fn (array $s): bool => $s['tip'] === 'poslije'));
            foreach (array_slice($poslije, 0, -1) as $i) {
                unset($slike[$i]);
            }
        }
        return array_values($slike);
    }

    /** Građanin vidi sliku "poslije" tek kad je prijava završena. */
    public static function canSeeImage(array $korisnik, array $prijava, array $slika): bool
    {
        return $slika['tip'] === 'prije'
            || self::isStaff($korisnik, $prijava)
            || $prijava['status'] === 'ZAVRSENO';
    }

    /**
     * @return array{0: array, 1: array<string, string>}
     */
    private function validate(array $input): array
    {
        $naslov = trim($input['naslov'] ?? '');
        $opis   = trim(str_replace("\r\n", "\n", $input['opis'] ?? ''));
        $adresa = trim($input['adresa'] ?? '');
        $cetvrtId = filter_var($input['cetvrt_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $vrstaId  = filter_var($input['vrsta_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $sifrarnik = new Sifrarnik();
        $errors = [];

        if ($cetvrtId === false || !array_key_exists($cetvrtId, $sifrarnik->cetvrti())) {
            $errors['cetvrt_id'] = 'Odaberite gradsku četvrt.';
        }
        if ($vrstaId === false || !array_key_exists($vrstaId, $sifrarnik->vrsteProblema())) {
            $errors['vrsta_id'] = 'Odaberite vrstu problema.';
        }
        $duljina = mb_strlen($naslov);
        if ($duljina < 5 || $duljina > 150) {
            $errors['naslov'] = 'Naslov mora imati između 5 i 150 znakova.';
        }
        $duljina = mb_strlen($opis);
        if ($duljina < 10 || $duljina > 2000) {
            $errors['opis'] = 'Opis mora imati između 10 i 2000 znakova.';
        }
        if (mb_strlen($adresa) > 255) {
            $errors['adresa'] = 'Adresa smije imati najviše 255 znakova.';
        }
        [$lat, $lng, $greska] = Geo::parse($input['lat'] ?? '', $input['lng'] ?? '');
        if ($greska !== null) {
            $errors['lokacija'] = $greska;
        }

        return [
            [
                'cetvrt_id' => $cetvrtId, 'vrsta_id' => $vrstaId, 'naslov' => $naslov,
                'opis' => $opis, 'adresa' => $adresa !== '' ? $adresa : null, 'lat' => $lat, 'lng' => $lng,
            ],
            $errors,
        ];
    }
}
