<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Kategorija;
use App\Models\Korisnik;
use App\Models\Prijava;
use App\Models\VijestCms;

/**
 * Početna stranica administracije. Zaštitu (samo uloga 'admin') provodi
 * router prije poziva, vidi config/routes.php.
 */
final class AdminController
{
    public function index(): void
    {
        $vijesti = new VijestCms();
        $korisnici = new Korisnik();
        $poStatusu = (new Prijava())->countByStatus();

        View::render('admin/index', [
            'title' => 'Administracija',
            'broj'  => [
                'objavljeno' => $vijesti->count(null, 'objavljeno', ''),
                'nacrt'      => $vijesti->count(null, 'nacrt', ''),
                'kategorije' => count((new Kategorija())->options()),
                'aktivni'    => $korisnici->countForAdmin('', null, 1),
                'neaktivni'  => $korisnici->countForAdmin('', null, 0),
                'nove'       => (int) ($poStatusu['PRIJAVLJENO'] ?? 0),
                'provjera'   => (int) ($poStatusu['CEKA_PROVJERU'] ?? 0),
            ],
        ]);
    }
}
