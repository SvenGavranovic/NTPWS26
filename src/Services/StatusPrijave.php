<?php

declare(strict_types=1);

namespace App\Services;

/** Statusi, hitnosti i dopušteni prijelazi prijave. */
final class StatusPrijave
{
    /**
     * iz => [u => uloga]. Prijelaz u DODIJELJENO iz DODIJELJENO/U_RADU/VRACENO je preraspodjela.
     */
    public const PRIJELAZI = [
        'PRIJAVLJENO'   => ['DODIJELJENO' => 'admin'],
        'DODIJELJENO'   => ['U_RADU' => 'radnik', 'DODIJELJENO' => 'admin'],
        'U_RADU'        => ['CEKA_PROVJERU' => 'radnik', 'DODIJELJENO' => 'admin'],
        'CEKA_PROVJERU' => ['ZAVRSENO' => 'admin', 'VRACENO' => 'admin'],
        'VRACENO'       => ['U_RADU' => 'radnik', 'DODIJELJENO' => 'admin'],
        'ZAVRSENO'      => [],
    ];

    public static function canTransition(string $iz, string $u, string $uloga): bool
    {
        return (self::PRIJELAZI[$iz][$u] ?? null) === $uloga;
    }

    /** kod => [naziv, boja, opis] */
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
