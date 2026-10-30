<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\Prijava;
use App\Services\ProfileService;

/** Profil prijavljenog korisnika. */
final class ProfileController
{
    public function show(): void
    {
        $this->render();
    }

    public function update(): void
    {
        $korisnik = Auth::user();
        $errors = (new ProfileService())->updateName((int) $korisnik['id'], post('ime'), post('prezime'));

        if ($errors !== []) {
            $this->render(['errorsProfil' => $errors, 'old' => ['ime' => post('ime'), 'prezime' => post('prezime')]], 422);
            return;
        }

        Session::flash('success', 'Podaci su spremljeni.');
        redirect('/racun/profil');
    }

    public function changePassword(): void
    {
        $korisnik = Auth::user();
        $errors = (new ProfileService())->changePassword(
            (int) $korisnik['id'],
            post('trenutna_lozinka'),
            post('nova_lozinka'),
            post('nova_lozinka_potvrda'),
        );

        if ($errors !== []) {
            $this->render(['errorsLozinka' => $errors], 422);
            return;
        }

        session_regenerate_id(true);
        Session::flash('success', 'Lozinka je promijenjena.');
        redirect('/racun/profil');
    }

    private function render(array $data = [], int $status = 200): void
    {
        View::render('profil/index', $data + [
            'title'       => 'Moj profil',
            'korisnik'    => Auth::user(),
            'brojPrijava' => (new Prijava())->countByUser((int) Auth::user()['id']),
        ], $status);
    }
}
