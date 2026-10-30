<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Kategorija;
use PDOException;

/** Kategorije vijesti (samo administrator). */
final class CategoryService
{
    private Kategorija $kategorije;

    public function __construct()
    {
        $this->kategorije = new Kategorija();
    }

    /** @return array<string, string> pogreške; prazno = uspjeh */
    public function save(?int $id, string $naziv, string $opis): array
    {
        $naziv = trim($naziv);
        $opis = trim($opis);

        $errors = [];
        if (mb_strlen($naziv) < 2 || mb_strlen($naziv) > 80) {
            $errors['naziv'] = 'Naziv mora imati između 2 i 80 znakova.';
        }
        if (mb_strlen($opis) > 255) {
            $errors['opis'] = 'Opis smije imati najviše 255 znakova.';
        }
        if ($errors !== []) {
            return $errors;
        }

        try {
            if ($id === null) {
                $this->kategorije->create($naziv, $opis !== '' ? $opis : null);
            } else {
                $this->kategorije->update($id, $naziv, $opis !== '' ? $opis : null);
            }
        } catch (PDOException $e) {
            // UNIQUE na nazivu: baza je jedini pouzdani čuvar (i kod istovremenih zahtjeva)
            if ($e->getCode() === '23000') {
                return ['naziv' => 'Kategorija s tim nazivom već postoji.'];
            }
            throw $e;
        }
        return [];
    }

    /** @return string|null poruka pogreške; null = obrisano */
    public function delete(int $id): ?string
    {
        // Pravilo brisanja: kategorija koja ima vijesti (i nacrte) se NE briše.
        // Provjera ovdje daje razumljivu poruku, a FK ON DELETE RESTRICT u bazi
        // je zadnja linija obrane ako bi se vijest dodala u međuvremenu.
        $broj = $this->kategorije->countNews($id);
        if ($broj > 0) {
            return "Kategorija ima {$broj} " . ($broj === 1 ? 'vijest' : 'vijesti') . ' i ne može se obrisati. Najprije premjestite ili obrišite vijesti.';
        }

        try {
            $this->kategorije->delete($id);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return 'Kategorija ima vijesti i ne može se obrisati.';
            }
            throw $e;
        }
        return null;
    }
}
