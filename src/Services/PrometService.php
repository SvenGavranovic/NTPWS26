<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SkriveniIncident;
use DateTimeImmutable;
use RuntimeException;

/**
 * Stanje prometa iz TomTom Traffic API-ja (incidenti + protok na glavnim prometnicama).
 * Ključ je samo u .env; klijent nikad ne razgovara s TomTomom izravno.
 */
final class PrometService
{
    private const INCIDENTI_URL = 'https://api.tomtom.com/traffic/services/5/incidentDetails';
    private const PROTOK_URL = 'https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json';
    private const BBOX = '15.80,45.70,16.25,45.95'; // minLon,minLat,maxLon,maxLat (cijeli Zagreb, ~970 km²)
    private const POLJA = '{incidents{type,geometry{type,coordinates},properties{id,iconCategory,magnitudeOfDelay,'
        . 'events{description,code,iconCategory},startTime,endTime,from,to,length,delay,roadNumbers,lastReportTime,numberOfReports}}}';

    /** Zatvorenost bez kraja starija od ovoga označava se kao moguće zastarjela. */
    public const DUGOTRAJNO_DANA = 30;

    /** Besplatni plan: 2.500 zahtjeva za incidente mjesečno ≈ 80 osvježavanja dnevno. */
    public const MAX_OSVJEZAVANJA_DNEVNO = 80;
    private const MAX_INCIDENATA = 300;

    /** Točke na glavnim prometnicama za protok: [naziv, lat, lng]. */
    public const TOCKE = [
        ['Slavonska avenija (Heinzelova)', 45.7993, 16.0050],
        ['Zagrebačka avenija (Savska)', 45.7943, 15.9560],
        ['Ulica grada Vukovara (Lisinski)', 45.8005, 15.9795],
        ['Savska cesta (Studentski centar)', 45.8010, 15.9660],
        ['Avenija Dubrovnik (Siget)', 45.7760, 15.9690],
        ['Ljubljanska avenija (Jankomir)', 45.7975, 15.8790],
    ];

    public const KATEGORIJE = [
        0 => 'Nepoznato', 1 => 'Prometna nesreća', 2 => 'Magla', 3 => 'Opasni uvjeti', 4 => 'Kiša',
        5 => 'Poledica', 6 => 'Gužva', 7 => 'Zatvoren prometni trak', 8 => 'Zatvorena cesta',
        9 => 'Radovi na cesti', 10 => 'Vjetar', 11 => 'Poplava', 14 => 'Pokvareno vozilo',
    ];

    public const INTENZITETI = [0 => 'Nepoznato', 1 => 'Malo zastoja', 2 => 'Umjereni zastoji', 3 => 'Veliki zastoji', 4 => 'Neodređeno (zatvoreno)'];

    public const SIMULACIJE = ['testni', 'nedostupan', 'neispravno'];

    private string $kljuc;
    private int $timeout;
    private int $ttl;
    private string $mapa;

    public function __construct()
    {
        $this->kljuc = trim($_ENV['TOMTOM_API_KEY'] ?? '');
        $this->timeout = max(1, min(15, (int) ($_ENV['TOMTOM_TIMEOUT'] ?? 5)));
        $this->ttl = max(60, min(120, (int) ($_ENV['TOMTOM_CACHE_TTL'] ?? 90)));
        $this->mapa = BASE_PATH . '/storage/cache';
    }

    /**
     * @param string|null $simulacija 'testni' (testni podaci), 'nedostupan' ili 'neispravno' (kvar)
     * @param bool $sakrij false = i incidenti koje je administrator sakrio (za administraciju)
     * @return array{status: int, data?: array, poruka?: string, zadnji?: ?string}
     */
    public function get(?string $simulacija = null, bool $sakrij = true): array
    {
        $rezultat = $this->podaci($simulacija);
        if (!isset($rezultat['data'])) {
            return $rezultat;
        }

        // starije kopije u cacheu nemaju nova polja
        $incidenti = array_map(static fn (array $i): array => $i + [
            'zadnja_potvrda' => null, 'broj_potvrda' => null, 'dugotrajno' => false,
        ], $rezultat['data']['incidenti']);

        $skriveno = 0;
        if ($sakrij) {
            $skriveni = array_flip((new SkriveniIncident())->hashes());
            $vidljivi = array_values(array_filter($incidenti, static fn (array $i): bool => $i['id'] === null || !isset($skriveni[SkriveniIncident::hash($i['id'])])));
            $skriveno = count($incidenti) - count($vidljivi);
            $incidenti = $vidljivi;
        }
        $rezultat['data']['incidenti'] = $incidenti;
        $rezultat['data']['skriveno_incidenata'] = $skriveno;
        return $rezultat;
    }

    private function podaci(?string $simulacija): array
    {
        if ($simulacija === 'testni' || ($simulacija === null && !$this->imaKljuc())) {
            return ['status' => 200, 'data' => $this->testni(
                $simulacija === 'testni' ? 'Zatražen je testni odgovor.' : 'TomTom API ključ nije postavljen u .env.'
            )];
        }

        $cache = $this->readCache();
        if ($simulacija === null && $cache !== null && $this->starost($cache) < $this->ttl) {
            return ['status' => 200, 'data' => $this->izCachea($cache)];
        }

        // Samo jedan zahtjev istovremeno osvježava podatke; ostali čekaju i čitaju svjež cache.
        $lock = fopen($this->mapa . '/promet.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $cache = $this->readCache();
            if ($simulacija === null && $cache !== null && $this->starost($cache) < $this->ttl) {
                return ['status' => 200, 'data' => $this->izCachea($cache)];
            }

            try {
                if ($simulacija === null && !$this->potrosiKvotu()) {
                    throw new RuntimeException('Dnevna kvota osvježavanja (' . self::MAX_OSVJEZAVANJA_DNEVNO . ') je potrošena.');
                }
                $podaci = $this->dohvati($simulacija);
            } catch (RuntimeException $e) {
                if ($simulacija === null) {
                    log_error($e);
                }
                if ($cache !== null) {
                    return ['status' => 200, 'data' => $this->izCachea($cache, $e->getMessage())];
                }
                return ['status' => 503, 'poruka' => $e->getMessage(), 'zadnji' => null];
            }

            if ($simulacija === null) {
                $this->writeCache($podaci);
            }
            return ['status' => 200, 'data' => $this->odgovor($podaci, 'tomtom', new DateTimeImmutable())];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function imaKljuc(): bool
    {
        return $this->kljuc !== '' && $this->kljuc !== 'upisati_vlastiti_kljuc';
    }

    /** @return array{incidenti: array, protok: array, upozorenje: ?string} */
    private function dohvati(?string $simulacija): array
    {
        if ($simulacija === 'nedostupan') {
            throw new RuntimeException('Servis TomTom nije dostupan (simulacija kvara).');
        }

        $urls = ['incidenti' => self::INCIDENTI_URL . '?' . http_build_query([
            'key' => $this->kljuc, 'bbox' => self::BBOX, 'fields' => self::POLJA,
            'language' => 'en-GB', 'timeValidityFilter' => 'present',
        ])];
        if ($simulacija === null) {
            foreach (self::TOCKE as $i => [, $lat, $lng]) {
                $urls['protok' . $i] = self::PROTOK_URL . '?' . http_build_query([
                    'key' => $this->kljuc, 'point' => $lat . ',' . $lng, 'unit' => 'kmph',
                ]);
            }
        }

        $odgovori = $simulacija === 'neispravno'
            ? ['incidenti' => ['kod' => 200, 'tijelo' => '{"rezultat":"neocekivano","incidents":"nije niz"}', 'greska' => null]]
            : $this->http($urls);

        $inc = $odgovori['incidenti'];
        if ($inc['greska'] !== null) {
            throw new RuntimeException('TomTom nije odgovorio na vrijeme ili nije dostupan.');
        }
        if ($inc['kod'] !== 200) {
            throw new RuntimeException(match ($inc['kod']) {
                403     => 'TomTom je odbio API ključ (HTTP 403).',
                429     => 'Premašeno ograničenje zahtjeva prema TomTomu (HTTP 429).',
                default => 'Servis TomTom nije dostupan (HTTP ' . $inc['kod'] . ').',
            });
        }

        $json = json_decode($inc['tijelo'], true);
        if (!is_array($json) || !isset($json['incidents']) || !is_array($json['incidents']) || !array_is_list($json['incidents'])) {
            throw new RuntimeException('TomTom je vratio odgovor neočekivane strukture.');
        }

        $incidenti = [];
        foreach (array_slice($json['incidents'], 0, self::MAX_INCIDENATA) as $f) {
            if (($n = $this->incident($f)) !== null) {
                $incidenti[] = $n;
            }
        }

        $protok = [];
        foreach (self::TOCKE as $i => [$naziv, $lat, $lng]) {
            $o = $odgovori['protok' . $i] ?? null;
            $f = $o && $o['kod'] === 200 ? (json_decode($o['tijelo'], true)['flowSegmentData'] ?? null) : null;
            if (is_array($f) && is_numeric($f['currentSpeed'] ?? null) && is_numeric($f['freeFlowSpeed'] ?? null) && $f['freeFlowSpeed'] > 0) {
                $protok[] = [
                    'naziv'           => $naziv,
                    'lat'             => $lat,
                    'lng'             => $lng,
                    'brzina'          => (int) $f['currentSpeed'],
                    'slobodna_brzina' => (int) $f['freeFlowSpeed'],
                    'omjer'           => round($f['currentSpeed'] / $f['freeFlowSpeed'], 2),
                    'zatvoreno'       => (bool) ($f['roadClosure'] ?? false),
                    'pouzdanost'      => is_numeric($f['confidence'] ?? null) ? round((float) $f['confidence'], 2) : null,
                ];
            }
        }

        $upozorenje = null;
        if ($simulacija === null && count($protok) < count(self::TOCKE)) {
            $upozorenje = 'Protok nije dohvaćen za ' . (count(self::TOCKE) - count($protok)) . ' od ' . count(self::TOCKE) . ' točaka.';
        }
        return ['incidenti' => $incidenti, 'protok' => $protok, 'upozorenje' => $upozorenje];
    }

    /** Jedan incident iz GeoJSON-a u naš oblik; null ako struktura nije ispravna. Koordinate [lon, lat] → [lat, lng]. */
    private function incident(mixed $f): ?array
    {
        $g = $f['geometry'] ?? null;
        $p = $f['properties'] ?? null;
        if (!is_array($g) || !is_array($p) || !in_array($g['type'] ?? null, ['Point', 'LineString'], true)
            || !is_array($g['coordinates'] ?? null) || !is_int($p['iconCategory'] ?? null)) {
            return null;
        }

        $tocke = $g['type'] === 'Point' ? [$g['coordinates']] : $g['coordinates'];
        $koordinate = [];
        foreach ($tocke as $t) {
            if (!is_array($t) || !is_numeric($t[0] ?? null) || !is_numeric($t[1] ?? null)) {
                return null;
            }
            $koordinate[] = [round((float) $t[1], 6), round((float) $t[0], 6)];
        }
        if ($koordinate === []) {
            return null;
        }

        $opisi = [];
        foreach (is_array($p['events'] ?? null) ? $p['events'] : [] as $ev) {
            if (is_string($ev['description'] ?? null)) {
                $opisi[] = $ev['description'];
            }
        }
        $intenzitet = is_int($p['magnitudeOfDelay'] ?? null) ? $p['magnitudeOfDelay'] : 0;

        return [
            'id'               => is_string($p['id'] ?? null) ? $p['id'] : null,
            'kategorija'       => $p['iconCategory'],
            'kategorija_naziv' => self::KATEGORIJE[$p['iconCategory']] ?? 'Nepoznato',
            'intenzitet'       => $intenzitet,
            'intenzitet_naziv' => self::INTENZITETI[$intenzitet] ?? 'Nepoznato',
            'opis'             => implode('; ', $opisi),
            'od'               => is_string($p['from'] ?? null) ? $p['from'] : null,
            'do'               => is_string($p['to'] ?? null) ? $p['to'] : null,
            'ceste'            => array_values(array_filter($p['roadNumbers'] ?? [], 'is_string')),
            'kasnjenje_s'      => is_numeric($p['delay'] ?? null) ? (int) $p['delay'] : null,
            'duljina_m'        => is_numeric($p['length'] ?? null) ? (int) round((float) $p['length']) : null,
            'pocetak'          => is_string($p['startTime'] ?? null) ? $p['startTime'] : null,
            'kraj'             => is_string($p['endTime'] ?? null) ? $p['endTime'] : null,
            'zadnja_potvrda'   => is_string($p['lastReportTime'] ?? null) ? $p['lastReportTime'] : null,
            'broj_potvrda'     => is_int($p['numberOfReports'] ?? null) ? $p['numberOfReports'] : null,
            'dugotrajno'       => !is_string($p['endTime'] ?? null) && is_string($p['startTime'] ?? null)
                && strtotime($p['startTime']) < time() - self::DUGOTRAJNO_DANA * 86400,
            'geometrija'       => ['tip' => $g['type'], 'koordinate' => $koordinate],
        ];
    }

    /**
     * Paralelni GET zahtjevi (curl_multi), svaki s istim timeoutom.
     * @param array<string, string> $urls
     * @return array<string, array{kod: int, tijelo: string, greska: ?string}>
     */
    private function http(array $urls): array
    {
        if (!function_exists('curl_multi_init')) {
            return $this->httpStream($urls);
        }
        $multi = curl_multi_init();
        $rucke = [];
        foreach ($urls as $k => $url) {
            $h = curl_init($url);
            curl_setopt_array($h, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_ENCODING       => '',       // prihvati gzip
                CURLOPT_USERAGENT      => 'ZagrebPrijave/1.0 (studentski projekt)',
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            curl_multi_add_handle($multi, $h);
            $rucke[$k] = $h;
        }

        do {
            $stanje = curl_multi_exec($multi, $aktivno);
            if ($aktivno) {
                curl_multi_select($multi, 1.0);
            }
        } while ($aktivno && $stanje === CURLM_OK);

        $rezultat = [];
        foreach ($rucke as $k => $h) {
            $greska = curl_errno($h) !== 0 ? curl_strerror(curl_errno($h)) : null;
            $rezultat[$k] = [
                'kod'    => (int) curl_getinfo($h, CURLINFO_HTTP_CODE),
                'tijelo' => (string) curl_multi_getcontent($h),
                'greska' => $greska,
            ];
            curl_multi_remove_handle($multi, $h);
            curl_close($h);
        }
        curl_multi_close($multi);
        return $rezultat;
    }

    /**
     * Bez ekstenzije curl (npr. XAMPP pod Apacheom bez njezinih DLL-ova): zahtjevi redom
     * preko https stream wrappera. Kad istekne ukupno vrijeme, preostali zahtjevi se preskaču.
     */
    private function httpStream(array $urls): array
    {
        $rok = microtime(true) + $this->timeout * 2;
        $rezultat = [];
        foreach ($urls as $k => $url) {
            $preostalo = $rok - microtime(true);
            if ($preostalo <= 0.5) {
                $rezultat[$k] = ['kod' => 0, 'tijelo' => '', 'greska' => 'preskočeno (isteklo vrijeme)'];
                continue;
            }
            $kontekst = stream_context_create(['http' => [
                'method'          => 'GET',
                'timeout'         => min($this->timeout, $preostalo),
                'ignore_errors'   => true,   // tijelo i kod i za 4xx/5xx
                'follow_location' => 0,
                'user_agent'      => 'ZagrebPrijave/1.0 (studentski projekt)',
            ]]);
            $tijelo = @file_get_contents($url, false, $kontekst);
            $kod = 0;
            if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
                $kod = (int) $m[1];
            }
            $rezultat[$k] = [
                'kod'    => $kod,
                'tijelo' => $tijelo === false ? '' : $tijelo,
                'greska' => $tijelo === false ? 'nema odgovora' : null,
            ];
        }
        return $rezultat;
    }

    private function potrosiKvotu(): bool
    {
        $datoteka = $this->mapa . '/promet-kvota.json';
        $danas = date('Y-m-d');
        $k = is_file($datoteka) ? json_decode((string) file_get_contents($datoteka), true) : null;
        $broj = is_array($k) && ($k['datum'] ?? '') === $danas ? (int) $k['broj'] : 0;
        if ($broj >= self::MAX_OSVJEZAVANJA_DNEVNO) {
            return false;
        }
        file_put_contents($datoteka, json_encode(['datum' => $danas, 'broj' => $broj + 1]));
        return true;
    }

    private function readCache(): ?array
    {
        $datoteka = $this->mapa . '/promet.json';
        if (!is_file($datoteka)) {
            return null;
        }
        $c = json_decode((string) file_get_contents($datoteka), true);
        return is_array($c) && isset($c['dohvaceno_u'], $c['podaci']) ? $c : null;
    }

    private function writeCache(array $podaci): void
    {
        $privremena = $this->mapa . '/promet.json.tmp';
        file_put_contents($privremena, json_encode(['dohvaceno_u' => date(DATE_ATOM), 'podaci' => $podaci], JSON_UNESCAPED_UNICODE));
        rename($privremena, $this->mapa . '/promet.json'); // zamjena u jednom koraku
    }

    private function starost(array $cache): int
    {
        return time() - (new DateTimeImmutable($cache['dohvaceno_u']))->getTimestamp();
    }

    private function izCachea(array $cache, ?string $greska = null): array
    {
        $data = $this->odgovor($cache['podaci'], 'cache', new DateTimeImmutable($cache['dohvaceno_u']));
        if ($greska !== null) {
            $data['zastarjelo'] = true;
            $data['upozorenje'] = $greska . ' Prikazani su zadnji uspješno dohvaćeni podaci.';
        }
        return $data;
    }

    private function odgovor(array $podaci, string $izvor, DateTimeImmutable $dohvaceno): array
    {
        return [
            'izvor'                  => $izvor,
            'testni'                 => false,
            'zastarjelo'             => false,
            'upozorenje'             => $podaci['upozorenje'] ?? null,
            'zadnji_uspjesni_dohvat' => $dohvaceno->format(DATE_ATOM),
            'osvjezavanje_s'         => $this->ttl,
            'incidenti'              => $podaci['incidenti'],
            'protok'                 => $podaci['protok'],
            'atribucija'             => 'Podaci o prometu © TomTom',
        ];
    }

    /** Jasno označeni testni podaci (bez ključa ili na zahtjev ?simulacija=testni). */
    private function testni(string $razlog): array
    {
        $podaci = [
            'incidenti' => [
                $this->incident(['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[15.9690, 45.7990], [15.9750, 45.7993], [15.9800, 45.7996]]],
                    'properties' => ['id' => 'TEST-1', 'iconCategory' => 9, 'magnitudeOfDelay' => 2, 'events' => [['description' => 'TEST: roadworks']],
                        'from' => 'Savska cesta', 'to' => 'Lisinski', 'length' => 820.0, 'delay' => 240]]),
                $this->incident(['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [16.0050, 45.7993]],
                    'properties' => ['id' => 'TEST-2', 'iconCategory' => 1, 'magnitudeOfDelay' => 3, 'events' => [['description' => 'TEST: accident']],
                        'from' => 'Slavonska avenija', 'to' => 'Heinzelova', 'length' => 300.0, 'delay' => 600]]),
            ],
            'protok' => array_map(static fn (array $t, int $i): array => [
                'naziv' => $t[0], 'lat' => $t[1], 'lng' => $t[2],
                'brzina' => [35, 52, 18, 41, 60, 47][$i], 'slobodna_brzina' => [60, 70, 50, 50, 70, 80][$i],
                'omjer' => round([35, 52, 18, 41, 60, 47][$i] / [60, 70, 50, 50, 70, 80][$i], 2),
                'zatvoreno' => false, 'pouzdanost' => 1.0,
            ], self::TOCKE, array_keys(self::TOCKE)),
        ];
        $data = $this->odgovor($podaci, 'testni', new DateTimeImmutable());
        $data['testni'] = true;
        $data['zadnji_uspjesni_dohvat'] = null;
        $data['upozorenje'] = 'TESTNI PODACI – ne prikazuju stvarno stanje prometa. ' . $razlog;
        return $data;
    }
}
