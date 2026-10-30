<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\View;
use App\Models\Kategorija;
use App\Models\VijestCms;
use App\Services\NewsAdminService;

/**
 * CMS za vijesti (/admin/vijesti). Router pušta samo uloge urednik i admin.
 * Za postojeću vijest se UVIJEK dodatno provjerava je li korisnik smije
 * mijenjati (urednik samo svoje) – inače bi urednik promjenom ID-a u URL-u
 * mogao urediti tuđu vijest (IDOR).
 */
final class NewsAdminController
{
    private const PO_STRANICI = 10;

    public function index(): void
    {
        $korisnik = Auth::user();
        $q = mb_substr(get('q'), 0, 100);
        $status = in_array(get('status'), ['nacrt', 'objavljeno'], true) ? get('status') : null;
        // Urednik vidi samo svoje vijesti, administrator sve
        $autorId = $korisnik['uloga'] === 'admin' ? null : (int) $korisnik['id'];

        $vijesti = new VijestCms();
        $paginator = new Paginator(
            total: $vijesti->count($autorId, $status, $q),
            perPage: self::PO_STRANICI,
            requestedPage: get_int('stranica'),
            path: '/admin/vijesti',
            query: ['q' => $q, 'status' => $status],
        );

        View::render('admin/vijesti/index', [
            'title'     => $autorId === null ? 'Vijesti' : 'Moje vijesti',
            'vijesti'   => $vijesti->find($autorId, $status, $q, $paginator->perPage, $paginator->offset),
            'paginator' => $paginator,
            'q'         => $q,
            'status'    => $status,
            'sveVijesti' => $autorId === null,
        ]);
    }

    public function create(): void
    {
        $this->form(['status' => 'nacrt']);
    }

    public function store(): void
    {
        $result = (new NewsAdminService())->create($this->input(), $_FILES['slika'] ?? null, (int) Auth::user()['id']);

        if ($result['errors'] !== []) {
            $this->form($this->input(), $result['errors'], 422);
            return;
        }
        Session::flash('success', 'Vijest je spremljena.');
        redirect('/admin/vijesti/' . $result['id'] . '/uredi');
    }

    public function edit(int $id): void
    {
        $vijest = $this->findEditable($id);
        if ($vijest !== null) {
            $this->form($vijest);
        }
    }

    public function update(int $id): void
    {
        $vijest = $this->findEditable($id);
        if ($vijest === null) {
            return;
        }

        $errors = (new NewsAdminService())->update($vijest, $this->input(), $_FILES['slika'] ?? null);
        if ($errors !== []) {
            // Polja iz obrasca, ali id i postojeća slika iz baze
            $this->form(['id' => $id, 'slika' => $vijest['slika'], 'datum_objave' => $vijest['datum_objave']] + $this->input(), $errors, 422);
            return;
        }
        Session::flash('success', 'Promjene su spremljene.');
        redirect('/admin/vijesti/' . $id . '/uredi');
    }

    public function destroy(int $id): void
    {
        $vijest = $this->findEditable($id);
        if ($vijest === null) {
            return;
        }
        (new NewsAdminService())->delete($vijest);
        Session::flash('success', 'Vijest „' . $vijest['naslov'] . '” je obrisana.');
        redirect('/admin/vijesti');
    }

    /** Vijest koju prijavljeni korisnik smije mijenjati; inače šalje 404/403 i vraća null. */
    private function findEditable(int $id): ?array
    {
        $vijest = (new VijestCms())->findById($id);
        if ($vijest === null) {
            View::error(404, 'Vijest ne postoji.');
            return null;
        }
        if (!NewsAdminService::canEdit(Auth::user(), $vijest)) {
            View::error(403, 'Možete uređivati samo vlastite vijesti.');
            return null;
        }
        return $vijest;
    }

    /** Samo poznata polja obrasca (sve ostalo, npr. autor_id, se ignorira). */
    private function input(): array
    {
        return [
            'naslov'        => post('naslov'),
            'sazetak'       => post('sazetak'),
            'sadrzaj'       => post('sadrzaj'),
            'kategorija_id' => post('kategorija_id'),
            'status'        => post('status'),
            'ukloni_sliku'  => post('ukloni_sliku'),
        ];
    }

    private function form(array $vijest, array $errors = [], int $status = 200): void
    {
        View::render('admin/vijesti/form', [
            'title'      => isset($vijest['id']) ? 'Uređivanje vijesti' : 'Nova vijest',
            'vijest'     => $vijest,
            'kategorije' => (new Kategorija())->options(),
            'errors'     => $errors,
            'urednik'    => true, // layout učitava Quill samo na ovoj stranici
        ], $status);
    }
}
