<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Kategorija;
use App\Models\Korisnik;
use App\Models\VijestCms;

/**
 * Početna stranica administracije. Zaštitu (samo uloga 'admin') provodi
 * router prije poziva, vidi config/routes.php. Dodjela zadataka dolazi u fazi 6.
 */
final class AdminController
{
    public function index(): void
    {
        $vijesti = new VijestCms();
        $korisnici = new Korisnik();

        View::render('admin/index', [
            'title' => 'Administracija',
            'broj'  => [
                'objavljeno' => $vijesti->count(null, 'objavljeno', ''),
                'nacrt'      => $vijesti->count(null, 'nacrt', ''),
                'kategorije' => count((new Kategorija())->options()),
                'aktivni'    => $korisnici->countForAdmin('', null, 1),
                'neaktivni'  => $korisnici->countForAdmin('', null, 0),
            ],
        ]);
    }
}
