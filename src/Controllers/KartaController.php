<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Geo;
use App\Services\StatusPrijave;

/** Javna karta: prijave, radovi i promet (podaci iz vlastitog API-ja). */
final class KartaController
{
    public function index(): void
    {
        View::render('karta', [
            'title'    => 'Karta',
            'karta'    => true,
            'granice'  => Geo::bounds(),
            'statusi'  => array_map(static fn (array $s): string => $s[0], StatusPrijave::STATUSI),
        ]);
    }
}
