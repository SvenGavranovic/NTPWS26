<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rad;
use App\Models\Sifrarnik;
use DateTimeImmutable;

/** Radovi na infrastrukturi: nazivi, validacija i spremanje. */
final class RadService
{
    public const VRSTE = [
        'cesta'              => 'Cesta',
        'vodovod'            => 'Vodovod',
        'kanalizacija'       => 'Kanalizacija',
        'plinovod'           => 'Plinovod',
        'toplovod'           => 'Toplovod',
        'elektroinstalacije' => 'Elektroinstalacije',
        'tramvajska_pruga'   => 'Tramvajska pruga',
        'ostalo'             => 'Ostalo',
    ];

    public const UTJECAJI = [
        'nema'                   => 'Bez utjecaja na promet',
        'djelomicna_zatvorenost' => 'Djelomična zatvorenost',
        'potpuna_zatvorenost'    => 'Potpuna zatvorenost',
    ];

    /** @return array{id?: int, errors: array<string, string>} */
    public function save(?int $id, array $input, int $adminId): array
    {
        [$data, $errors] = $this->validate($input);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $model = new Rad();
        if ($id === null) {
            $id = $model->create($data, $adminId);
        } else {
            $model->update($id, $data);
        }
        return ['id' => $id, 'errors' => []];
    }

    /** Status radova u trenutku $sada: 'planirano', 'u_tijeku' ili 'zavrseno'. */
    public static function stanje(array $rad, ?DateTimeImmutable $sada = null): string
    {
        $sada ??= new DateTimeImmutable();
        if (new DateTimeImmutable($rad['pocetak']) > $sada) {
            return 'planirano';
        }
        return $rad['kraj'] !== null && new DateTimeImmutable($rad['kraj']) < $sada ? 'zavrseno' : 'u_tijeku';
    }

    /** @return array{0: array, 1: array<string, string>} */
    private function validate(array $in): array
    {
        $naslov = trim($in['naslov'] ?? '');
        $opis = trim(str_replace("\r\n", "\n", $in['opis'] ?? ''));
        $adresa = trim($in['adresa'] ?? '');
        $vrsta = $in['vrsta'] ?? '';
        $utjecaj = $in['utjecaj_na_promet'] ?? '';
        $cetvrtId = ($in['cetvrt_id'] ?? '') === '' ? null
            : filter_var($in['cetvrt_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $errors = [];
        $duljina = mb_strlen($naslov);
        if ($duljina < 3 || $duljina > 150) {
            $errors['naslov'] = 'Naslov mora imati između 3 i 150 znakova.';
        }
        if (mb_strlen($opis) > 2000) {
            $errors['opis'] = 'Opis smije imati najviše 2000 znakova.';
        }
        if (mb_strlen($adresa) > 255) {
            $errors['adresa'] = 'Adresa smije imati najviše 255 znakova.';
        }
        if (!isset(self::VRSTE[$vrsta])) {
            $errors['vrsta'] = 'Odaberite vrstu radova.';
        }
        if (!isset(self::UTJECAJI[$utjecaj])) {
            $errors['utjecaj_na_promet'] = 'Odaberite utjecaj na promet.';
        }
        if ($cetvrtId === false || ($cetvrtId !== null && !array_key_exists($cetvrtId, (new Sifrarnik())->cetvrti()))) {
            $errors['cetvrt_id'] = 'Odabrana četvrt ne postoji.';
        }

        [$lat, $lng, $greska] = Geo::parse($in['lat'] ?? '', $in['lng'] ?? '', obavezno: true);
        if ($greska !== null) {
            $errors['lokacija'] = $greska;
        }

        $pocetak = self::datetime($in['pocetak'] ?? '');
        $kraj = ($in['kraj'] ?? '') === '' ? null : self::datetime($in['kraj']);
        if ($pocetak === null || $pocetak === false) {
            $errors['pocetak'] = 'Unesite ispravan početak radova.';
        }
        if ($kraj === false) {
            $errors['kraj'] = 'Kraj radova nije ispravan datum.';
        } elseif (is_string($pocetak) && $kraj !== null && $kraj < $pocetak) {
            $errors['kraj'] = 'Kraj ne može biti prije početka.';
        }

        return [[
            'naslov' => $naslov, 'opis' => $opis !== '' ? $opis : null, 'vrsta' => $vrsta,
            'utjecaj_na_promet' => $utjecaj, 'cetvrt_id' => $cetvrtId ?: null,
            'adresa' => $adresa !== '' ? $adresa : null, 'lat' => $lat, 'lng' => $lng,
            'pocetak' => $pocetak, 'kraj' => $kraj ?: null,
        ], $errors];
    }

    /** "2026-11-03T08:00" (datetime-local) → "2026-11-03 08:00:00"; null ako je prazno, false ako nije ispravno. */
    private static function datetime(string $value): string|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $d === false ? false : $d->format('Y-m-d H:i:s');
    }
}
