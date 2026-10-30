<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Statusi i hitnosti prijave: nazivi, boje i DOPUŠTENI PRIJELAZI (state machine).
 */
final class StatusPrijave
{
    /**
     * Jedino mjesto gdje je zapisano koji su prijelazi dopušteni i tko ih smije napraviti:
     * iz statusa => [u status => uloga]. Sve što nije u tablici je zabranjeno.
     *
     *   PRIJAVLJENO → DODIJELJENO → U_RADU → CEKA_PROVJERU → ZAVRSENO
     *                                  ↑            ↓
     *                                  └── VRACENO ←┘
     *
     * Prijelaz "→ DODIJELJENO" iz DODIJELJENO/U_RADU/VRACENO je preraspodjela drugom radniku.
     * Radnik NIKAD ne može postaviti ZAVRSENO – to smije samo administrator.
     */
    public const PRIJELAZI = [
        'PRIJAVLJENO'   => ['DODIJELJENO' => 'admin'],
        'DODIJELJENO'   => ['U_RADU' => 'radnik', 'DODIJELJENO' => 'admin'],
        'U_RADU'        => ['CEKA_PROVJERU' => 'radnik', 'DODIJELJENO' => 'admin'],
        'CEKA_PROVJERU' => ['ZAVRSENO' => 'admin', 'VRACENO' => 'admin'],
        'VRACENO'       => ['U_RADU' => 'radnik', 'DODIJELJENO' => 'admin'],
        'ZAVRSENO'      => [],
    ];

    /** Smije li uloga prebaciti prijavu iz jednog statusa u drugi. */
    public static function canTransition(string $iz, string $u, string $uloga): bool
    {
        return (self::PRIJELAZI[$iz][$u] ?? null) === $uloga;
    }

    /** kod => [naziv, Bootstrap boja oznake, objašnjenje za građanina] */
    public const STATUSI = [
        'PRIJAVLJENO'   => ['Prijavljeno', 'secondary', 'Prijava je zaprimljena i čeka obradu.'],
        'DODIJELJENO'   => ['Dodijeljeno', 'info', 'Prijava je dodijeljena radniku.'],
        'U_RADU'        => ['U radu', 'primary', 'Radnik rješava problem.'],
        'CEKA_PROVJERU' => ['Čeka provjeru', 'warning', 'Radnik je završio, služba provjerava obavljeni posao.'],
        'VRACENO'       => ['Vraćeno na doradu', 'danger', 'Služba je vratila posao radniku na doradu.'],
        'ZAVRSENO'      => ['Završeno', 'success', 'Problem je riješen.'],
    ];

    public const HITNOSTI = [
        'niska'    => ['Niska', 'secondary'],
        'srednja'  => ['Srednja', 'info'],
        'visoka'   => ['Visoka', 'warning'],
        'kriticna' => ['Kritična', 'danger'],
    ];

    public static function exists(string $status): bool
    {
        return isset(self::STATUSI[$status]);
    }

    /** HTML oznaka statusa (Bootstrap badge). Vrijednosti su fiksne iz konstante, ali ih ipak escapeamo. */
    public static function badge(string $status): string
    {
        [$naziv, $boja] = self::STATUSI[$status] ?? [$status, 'secondary'];
        return '<span class="badge text-bg-' . e($boja) . '">' . e($naziv) . '</span>';
    }

    public static function hitnostBadge(string $hitnost): string
    {
        [$naziv, $boja] = self::HITNOSTI[$hitnost] ?? [$hitnost, 'secondary'];
        return '<span class="badge text-bg-' . e($boja) . '">' . e($naziv) . '</span>';
    }
}
