<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Models\Sifrarnik;
use App\Services\AdresaService;
use App\Services\Geo;

/** GET /api/v1/lokacija?lat=&lng= – adresa i gradska četvrt za točku (prijavljeni korisnici). */
final class LokacijaApiController
{
    private const MAX_U_MINUTI = 10;

    public function show(): void
    {
        [$lat, $lng, $greska] = Geo::parse(get('lat'), get('lng'), obavezno: true);
        if ($greska !== null) {
            Api::error(400, $greska, ['lokacija' => $greska]);
            return;
        }

        // ograničenje po sesiji: Nominatim dopušta 1 zahtjev/s za cijelu aplikaciju
        $sada = time();
        $zahtjevi = array_filter($_SESSION['lokacija_zahtjevi'] ?? [], static fn (int $t): bool => $t > $sada - 60);
        if (count($zahtjevi) >= self::MAX_U_MINUTI) {
            header('Retry-After: 60');
            Api::error(429, 'Previše upita za adresu. Pokušajte ponovno za minutu.');
            return;
        }
        $zahtjevi[] = $sada;
        $_SESSION['lokacija_zahtjevi'] = array_values($zahtjevi);

        $r = (new AdresaService())->reverse($lat, $lng);
        if ($r === null) {
            header('Retry-After: 60');
            Api::error(503, 'Servis za adrese trenutačno nije dostupan. Adresu možete upisati ručno.');
            return;
        }

        $cetvrtId = AdresaService::cetvrtId($r['cetvrt'], (new Sifrarnik())->cetvrti());
        Api::ok([
            'lat'       => $lat,
            'lng'       => $lng,
            'adresa'    => $r['adresa'],
            'cetvrt'    => $cetvrtId !== null ? $r['cetvrt'] : null,
            'cetvrt_id' => $cetvrtId,
            'izvor'     => '© OpenStreetMap suradnici (Nominatim)',
        ]);
    }
}
