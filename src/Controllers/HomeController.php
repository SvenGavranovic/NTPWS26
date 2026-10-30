<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Prijava;
use App\Models\Vijest;

final class HomeController
{
    public function index(): void
    {
        View::render('home', [
            'title'      => 'Početna',
            'punaSirina' => true, // hero preko cijele širine ekrana
            'vijesti'    => (new Vijest())->latestPublished(3),
            'statistika' => (new Prijava())->statistika(),
        ]);
    }

    public function about(): void
    {
        View::render('o_projektu', ['title' => 'O projektu']);
    }
}
