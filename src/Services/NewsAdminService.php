<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Kategorija;
use App\Models\VijestCms;
use Throwable;

/**
 * Pravila za uređivanje vijesti u CMS-u: tko smije što, validacija i spremanje.
 */
final class NewsAdminService
{
    public const MAPA_SLIKA = 'vijesti';
    private const STATUSI = ['nacrt', 'objavljeno'];
    private const MAX_SADRZAJ = 100_000; // znakova HTML-a nakon čišćenja

    private VijestCms $vijesti;

    public function __construct()
    {
        $this->vijesti = new VijestCms();
    }

    /**
     * Administrator smije uređivati SVE vijesti, urednik SAMO svoje.
     * Poziva se u kontroleru za svaku radnju nad postojećom vijesti.
     */
    public static function canEdit(array $korisnik, array $vijest): bool
    {
        return $korisnik['uloga'] === 'admin'
            || ($korisnik['uloga'] === 'urednik' && (int) $vijest['autor_id'] === (int) $korisnik['id']);
    }

    /**
     * Nova vijest. Autor je UVIJEK prijavljeni korisnik (ne čita se iz obrasca).
     * @return array{id?: int, errors: array<string, string>}
     */
    public function create(array $input, ?array $slika, int $autorId): array
    {
        [$data, $errors] = $this->validate($input);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        // Slika se sprema tek kad su ostala polja ispravna (nema "napuštenih" datoteka)
        $upload = ImageUpload::save($slika, self::MAPA_SLIKA);
        if (isset($upload['greska'])) {
            return ['errors' => ['slika' => $upload['greska']]];
        }
        $data['slika'] = $upload['ime'] ?? null;

        try {
            $id = $this->vijesti->create($data, $autorId);
        } catch (Throwable $e) {
            ImageUpload::delete($data['slika'], self::MAPA_SLIKA); // baza nije uspjela → makni i datoteku
            throw $e;
        }
        return ['id' => $id, 'errors' => []];
    }

    /** @return array<string, string> pogreške; prazno = uspjeh */
    public function update(array $vijest, array $input, ?array $slika): array
    {
        [$data, $errors] = $this->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        $upload = ImageUpload::save($slika, self::MAPA_SLIKA);
        if (isset($upload['greska'])) {
            return ['slika' => $upload['greska']];
        }

        $staraSlika = $vijest['slika'];
        $data['slika'] = match (true) {
            isset($upload['ime'])            => $upload['ime'],  // nova slika zamjenjuje staru
            ($input['ukloni_sliku'] ?? '') === '1' => null,
            default                          => $staraSlika,
        };

        try {
            $this->vijesti->update((int) $vijest['id'], $data);
        } catch (Throwable $e) {
            ImageUpload::delete($upload['ime'] ?? null, self::MAPA_SLIKA);
            throw $e;
        }

        // Stara datoteka se briše tek NAKON uspješnog spremanja u bazu
        if ($staraSlika !== $data['slika']) {
            ImageUpload::delete($staraSlika, self::MAPA_SLIKA);
        }
        return [];
    }

    public function delete(array $vijest): void
    {
        $this->vijesti->delete((int) $vijest['id']);
        ImageUpload::delete($vijest['slika'], self::MAPA_SLIKA);
    }

    /**
     * Validacija na poslužitelju. Uzimaju se SAMO poznata polja; autor_id,
     * datum_objave i sl. iz obrasca se ignoriraju (zaštita od "mass assignment").
     *
     * @return array{0: array, 1: array<string, string>}
     */
    private function validate(array $input): array
    {
        $naslov  = trim($input['naslov'] ?? '');
        $sazetak = trim($input['sazetak'] ?? '');
        $status  = $input['status'] ?? '';
        $kategorijaId = filter_var($input['kategorija_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        // HTML iz uređivača čisti se PRIJE validacije i spremanja
        $sadrzaj = HtmlSanitizer::clean($input['sadrzaj'] ?? '');

        $errors = [];

        $duljina = mb_strlen($naslov);
        if ($duljina < 3 || $duljina > 200) {
            $errors['naslov'] = 'Naslov mora imati između 3 i 200 znakova.';
        }
        $duljina = mb_strlen($sazetak);
        if ($duljina < 10 || $duljina > 500) {
            $errors['sazetak'] = 'Sažetak mora imati između 10 i 500 znakova.';
        }
        if (HtmlSanitizer::isEmpty($sadrzaj)) {
            $errors['sadrzaj'] = 'Sadržaj je obavezan.';
        } elseif (mb_strlen($sadrzaj) > self::MAX_SADRZAJ) {
            $errors['sadrzaj'] = 'Sadržaj je predug.';
        }
        if ($kategorijaId === false || !array_key_exists($kategorijaId, (new Kategorija())->options())) {
            $errors['kategorija_id'] = 'Odaberite kategoriju.';
        }
        if (!in_array($status, self::STATUSI, true)) {
            $errors['status'] = 'Odaberite status.';
        }

        return [
            ['naslov' => $naslov, 'sazetak' => $sazetak, 'sadrzaj' => $sadrzaj, 'kategorija_id' => $kategorijaId, 'status' => $status],
            $errors,
        ];
    }
}
