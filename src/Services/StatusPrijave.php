<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Statusi i hitnosti prijave: nazivi i boje za prikaz.
 * U fazi 6 ovdje dolaze i dopušteni prijelazi (state machine).
 */
final class StatusPrijave
{
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
