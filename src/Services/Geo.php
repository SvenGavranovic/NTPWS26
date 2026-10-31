<?php

declare(strict_types=1);

namespace App\Services;

/** Granice Zagreba za kartu i provjeru koordinata. */
final class Geo
{
    public const MIN_LAT = 45.70;
    public const MAX_LAT = 45.95;
    public const MIN_LNG = 15.80;
    public const MAX_LNG = 16.25;

    public static function inZagreb(float $lat, float $lng): bool
    {
        return $lat >= self::MIN_LAT && $lat <= self::MAX_LAT && $lng >= self::MIN_LNG && $lng <= self::MAX_LNG;
    }

    /**
     * Koordinate iz obrasca: oba polja prazna → [null, null]; inače oba moraju biti
     * brojevi unutar Zagreba.
     *
     * @return array{0: ?float, 1: ?float, 2: ?string} [lat, lng, pogreška]
     */
    public static function parse(string $lat, string $lng, bool $obavezno = false): array
    {
        $lat = trim($lat);
        $lng = trim($lng);
        if ($lat === '' && $lng === '') {
            return [null, null, $obavezno ? 'Odaberite lokaciju na karti.' : null];
        }
        $la = filter_var($lat, FILTER_VALIDATE_FLOAT);
        $ln = filter_var($lng, FILTER_VALIDATE_FLOAT);
        if ($la === false || $ln === false) {
            return [null, null, 'Lokacija nije ispravna.'];
        }
        if (!self::inZagreb($la, $ln)) {
            return [null, null, 'Lokacija mora biti na području Grada Zagreba.'];
        }
        return [round($la, 6), round($ln, 6), null];
    }

    /** Za JavaScript (data atributi karte). */
    public static function bounds(): array
    {
        return [[self::MIN_LAT, self::MIN_LNG], [self::MAX_LAT, self::MAX_LNG]];
    }
}
