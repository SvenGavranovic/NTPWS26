<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Adresa za koordinate (OpenStreetMap Nominatim, reverse geocoding).
 * Pravila Nominatima: najviše 1 zahtjev u sekundi, identificirajući User-Agent, cache rezultata.
 */
final class AdresaService
{
    private const URL = 'https://nominatim.openstreetmap.org/reverse';
    private const USER_AGENT = 'ZagrebPrijave/1.0 (studentski projekt)';
    private const TIMEOUT = 5;
    private const CACHE_DANA = 30;

    private string $mapa;

    public function __construct()
    {
        $this->mapa = BASE_PATH . '/storage/cache/adrese';
        if (!is_dir($this->mapa)) {
            mkdir($this->mapa, 0775, true);
        }
    }

    /**
     * Koordinate se zaokružuju na 4 decimale (~10 m): bolji pogodak u cacheu, a Nominatimu ne ide točna lokacija.
     *
     * @return array{adresa: ?string, cetvrt: ?string}|null  null = servis nedostupan ili neočekivan odgovor
     */
    public function reverse(float $lat, float $lng): ?array
    {
        $lat4 = sprintf('%.4f', $lat);
        $lng4 = sprintf('%.4f', $lng);
        $datoteka = $this->mapa . '/' . sha1($lat4 . ',' . $lng4) . '.json';

        if (is_file($datoteka) && filemtime($datoteka) > time() - self::CACHE_DANA * 86400) {
            $c = json_decode((string) file_get_contents($datoteka), true);
            if (is_array($c) && array_key_exists('adresa', $c)) {
                return $c;
            }
        }

        $this->pricekaj();
        $url = self::URL . '?' . http_build_query([
            'format' => 'jsonv2', 'lat' => $lat4, 'lon' => $lng4,
            'zoom' => 18, 'addressdetails' => 1, 'accept-language' => 'hr',
        ]);
        $kontekst = stream_context_create(['http' => [
            'method'          => 'GET',
            'timeout'         => self::TIMEOUT,
            'ignore_errors'   => true,
            'follow_location' => 0,
            'user_agent'      => self::USER_AGENT,
            'header'          => "Accept: application/json\r\n",
        ]]);
        $tijelo = @file_get_contents($url, false, $kontekst);
        if ($tijelo === false || !isset($http_response_header[0])
            || !preg_match('#^HTTP/\S+\s+200#', $http_response_header[0])) {
            return null;
        }

        $j = json_decode($tijelo, true);
        if (!is_array($j)) {
            return null;
        }
        $a = is_array($j['address'] ?? null) ? $j['address'] : [];
        $ulica = $a['road'] ?? $a['pedestrian'] ?? $a['square'] ?? $a['footway']
            ?? (($j['name'] ?? '') !== '' ? $j['name'] : null) ?? $a['quarter'] ?? null;
        $broj = $a['house_number'] ?? '';
        $cetvrt = isset($a['suburb']) ? preg_replace('/^Gradska četvrt\s+/u', '', (string) $a['suburb']) : null;

        $rezultat = [
            'adresa' => is_string($ulica) ? mb_substr(trim($ulica . ' ' . $broj), 0, 255) : null,
            'cetvrt' => $cetvrt,
        ];
        file_put_contents($datoteka, json_encode($rezultat, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $rezultat;
    }

    /** ID gradske četvrti iz naziva (Nominatim piše "-", šifrarnik "–"). */
    public static function cetvrtId(?string $naziv, array $cetvrti): ?int
    {
        if ($naziv === null) {
            return null;
        }
        $norm = static fn (string $s): string => mb_strtolower(preg_replace('/\s*[-–—]\s*/u', '-', trim($s)));
        $trazi = $norm($naziv);
        foreach ($cetvrti as $id => $n) {
            if ($norm((string) $n) === $trazi) {
                return (int) $id;
            }
        }
        return null;
    }

    /** Razmak od najmanje 1 s između zahtjeva svih korisnika. */
    private function pricekaj(): void
    {
        $f = fopen($this->mapa . '/zadnji-zahtjev.txt', 'c+');
        if ($f === false) {
            return;
        }
        flock($f, LOCK_EX);
        $cekaj = (float) stream_get_contents($f) + 1.0 - microtime(true);
        if ($cekaj > 0) {
            usleep((int) (min($cekaj, 1.0) * 1_000_000));
        }
        ftruncate($f, 0);
        rewind($f);
        fwrite($f, (string) microtime(true));
        flock($f, LOCK_UN);
        fclose($f);
    }
}
