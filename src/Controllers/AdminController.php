<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

/**
 * Administracija. Zaštitu (samo uloga 'admin') provodi router prije poziva,
 * vidi config/routes.php. Ovdje se dodaju moduli u fazama 4 i 6.
 */
final class AdminController
{
    public function index(): void
    {
        View::render('admin/index', [
            'title' => 'Administracija',
            'korisnik' => Auth::user(),
        ]);
    }
}
