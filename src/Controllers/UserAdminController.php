<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\View;
use App\Models\Korisnik;

/**
 * Korisnici i uloge (/admin/korisnici), samo administrator.
 * Administrator mijenja ulogu i aktivnost računa. Deaktivacija odmah prekida
 * i otvorene sesije tog korisnika, jer Auth::user() pri svakom zahtjevu
 * provjerava stupac `aktivan` u bazi.
 */
final class UserAdminController
{
    private const PO_STRANICI = 10;

    public function index(): void
    {
        $model = new Korisnik();
        $uloge = $model->roles();

        $q = mb_substr(get('q'), 0, 100);
        $uloga = array_key_exists(get('uloga'), $uloge) ? get('uloga') : null;
        $aktivan = match (get('aktivan')) {
            '1' => 1,
            '0' => 0,
            default => null,
        };

        $paginator = new Paginator(
            total: $model->countForAdmin($q, $uloga, $aktivan),
            perPage: self::PO_STRANICI,
            requestedPage: get_int('stranica'),
            path: '/admin/korisnici',
            query: ['q' => $q, 'uloga' => $uloga, 'aktivan' => $aktivan],
        );

        View::render('admin/korisnici', [
            'title'     => 'Korisnici',
            'korisnici' => $model->findForAdmin($q, $uloga, $aktivan, $paginator->perPage, $paginator->offset),
            'uloge'     => $uloge,
            'paginator' => $paginator,
            'filtri'    => ['q' => $q, 'uloga' => $uloga, 'aktivan' => $aktivan],
            'mojId'     => (int) Auth::user()['id'],
        ]);
    }

    public function update(int $id): void
    {
        $model = new Korisnik();
        $korisnik = $model->findById($id);
        if ($korisnik === null) {
            View::error(404);
            return;
        }

        // Administrator ne može sebi oduzeti ulogu ni deaktivirati se.
        // Tako sustav nikad ne ostane bez ijednog administratora.
        if ($id === (int) Auth::user()['id']) {
            Session::flash('danger', 'Ne možete mijenjati ulogu ni status vlastitog računa.');
            redirect('/admin/korisnici');
        }

        $uloga = post('uloga');
        if (!array_key_exists($uloga, $model->roles())) {
            Session::flash('danger', 'Odabrana uloga ne postoji.');
            redirect('/admin/korisnici');
        }
        $aktivan = post('aktivan') === '1';

        $model->updateRoleAndStatus($id, $uloga, $aktivan);

        Session::flash('success', sprintf(
            'Korisnik %s %s: uloga „%s”, račun %s.',
            $korisnik['ime'], $korisnik['prezime'], $model->roles()[$uloga], $aktivan ? 'aktivan' : 'deaktiviran'
        ));
        // Povratak na istu stranicu popisa (s filtrima). Dopuštena je SAMO ta putanja
        // bez razmaka i novih redaka – nema preusmjeravanja na tuđe stranice (open
        // redirect) ni podmetanja zaglavlja (CRLF injection).
        $povratak = post('povratak');
        redirect(preg_match('#^/admin/korisnici(\?[^\s]*)?$#D', $povratak) ? $povratak : '/admin/korisnici');
    }
}
