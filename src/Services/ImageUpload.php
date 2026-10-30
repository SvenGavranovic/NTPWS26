<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Sigurno spremanje slike poslane obrascem (vijesti i prijave problema).
 *
 * Ne vjeruje se ničemu što šalje preglednik:
 *  - naziv datoteke i "Content-Type" iz zahtjeva se IGNORIRAJU,
 *  - stvarna vrsta se čita iz sadržaja datoteke (finfo, "magični bajtovi"),
 *  - getimagesize() mora prepoznati ispravnu sliku s razumnim dimenzijama,
 *  - ekstenzija se određuje iz provjerenog MIME tipa, a ime je nasumično.
 *
 * Gdje se slike spremaju (MAPE):
 *  - vijesti: public/uploads/ – javne slike; .htaccess zabranjuje izvršavanje skripti,
 *  - prijave: storage/uploads/ – IZVAN webroota; preglednik do njih dolazi samo
 *    preko PHP-a koji prije provjeri smije li korisnik vidjeti prijavu.
 */
final class ImageUpload
{
    public const MAX_BAJTOVA = 2 * 1024 * 1024; // 2 MB (zadano)
    private const MAX_DIMENZIJA = 6000;           // px, štiti od "decompression bomb" slika

    private const MAPE = [
        'vijesti' => '/public/uploads/vijesti',
        'prijave' => '/storage/uploads/prijave',
    ];

    /** Dopušteni MIME tipovi → ekstenzija pod kojom se sprema. SVG namjerno nije dopušten (može sadržavati JavaScript). */
    private const TIPOVI = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param array|null $file  element iz $_FILES
     * @return array{ime?: string, mime?: string, velicina?: int, greska?: string}  prazan niz = datoteka nije poslana
     */
    public static function save(?array $file, string $mapa, int $maxBajtova = self::MAX_BAJTOVA): array
    {
        $poruka = 'Slika smije imati najviše ' . intdiv($maxBajtova, 1024 * 1024) . ' MB.';
        if ($file === null || !isset($file['error']) || is_array($file['error'])) {
            return [];  // nije poslano ili je poslano više datoteka pod istim imenom (slika[]=)
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return ['greska' => $poruka];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['greska' => 'Slika nije ispravno poslana. Pokušajte ponovno.'];
        }

        // Datoteka mora stvarno biti upravo poslana kroz HTTP (ne npr. /etc/passwd)
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['greska' => 'Slika nije ispravno poslana. Pokušajte ponovno.'];
        }
        if ($file['size'] > $maxBajtova) {
            return ['greska' => $poruka];
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TIPOVI[$mime])) {
            return ['greska' => 'Dopuštene su samo slike JPG, PNG i WebP.'];
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || $info['mime'] !== $mime
            || $info[0] < 1 || $info[1] < 1
            || $info[0] > self::MAX_DIMENZIJA || $info[1] > self::MAX_DIMENZIJA) {
            return ['greska' => 'Datoteka nije ispravna slika ili je prevelikih dimenzija.'];
        }

        $dir = self::dir($mapa);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // 32 heksadekadska znaka iz kriptografski sigurnog generatora: ime se ne može pogoditi
        $ime = bin2hex(random_bytes(16)) . '.' . self::TIPOVI[$mime];

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $ime)) {
            throw new RuntimeException('Spremanje slike nije uspjelo.');
        }
        return ['ime' => $ime, 'mime' => $mime, 'velicina' => (int) $file['size']];
    }

    /** Briše prethodno spremljenu sliku. Ime se provjerava, pa se ne može podmetnuti "../". */
    public static function delete(?string $ime, string $mapa): void
    {
        if ($ime === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $ime)) {
            return;
        }
        $putanja = self::dir($mapa) . '/' . $ime;
        if (is_file($putanja)) {
            unlink($putanja);
        }
    }

    /** Puna putanja do spremljene datoteke (za posluživanje slika izvan webroota). */
    public static function path(string $ime, string $mapa): string
    {
        return self::dir($mapa) . '/' . basename($ime);
    }

    private static function dir(string $mapa): string
    {
        return BASE_PATH . (self::MAPE[$mapa] ?? throw new RuntimeException("Nepoznata mapa za upload: {$mapa}"));
    }
}
