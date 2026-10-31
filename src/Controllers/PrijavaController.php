<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\View;
use App\Models\Korisnik;
use App\Models\PovijestStatusa;
use App\Models\Prijava;
use App\Models\PrijavaSlika;
use App\Models\Sifrarnik;
use App\Services\ImageUpload;
use App\Services\PrijavaService;
use App\Services\StatusPrijave;

/** Prijave problema: nova prijava, vlastiti popis, detalj i slike. */
final class PrijavaController
{
    private const PO_STRANICI = 10;

    public function create(): void
    {
        $this->form([]);
    }

    public function store(): void
    {
        $input = [
            'cetvrt_id' => post('cetvrt_id'),
            'vrsta_id'  => post('vrsta_id'),
            'naslov'    => post('naslov'),
            'opis'      => post('opis'),
            'adresa'    => post('adresa'),
            'lat'       => post('lat'),
            'lng'       => post('lng'),
        ];
        $result = (new PrijavaService())->create((int) Auth::user()['id'], $input, $_FILES['slika'] ?? null);

        if ($result['errors'] !== []) {
            $this->form($input, $result['errors'], !empty($result['previse']) ? 429 : 422);
            return;
        }
        Session::flash('success', 'Hvala! Prijava je zaprimljena. Status možete pratiti na ovoj stranici.');
        redirect('/prijave/' . $result['id']);
    }

    /** Vlastite prijave (ID iz sesije). */
    public function index(): void
    {
        $korisnikId = (int) Auth::user()['id'];
        $status = StatusPrijave::exists(get('status')) ? get('status') : null;

        $model = new Prijava();
        $paginator = new Paginator(
            total: $model->countByUser($korisnikId, $status),
            perPage: self::PO_STRANICI,
            requestedPage: get_int('stranica'),
            path: '/prijave/moje',
            query: ['status' => $status],
        );

        View::render('prijave/index', [
            'title'     => 'Moje prijave',
            'prijave'   => $model->findByUser($korisnikId, $status, $paginator->perPage, $paginator->offset),
            'paginator' => $paginator,
            'status'    => $status,
            'ukupno'    => $model->countByUser($korisnikId),
        ]);
    }

    public function show(int $id): void
    {
        $prijava = $this->findVisible($id);
        if ($prijava === null) {
            return;
        }
        $korisnik = Auth::user();

        $sluzba = PrijavaService::isStaff($korisnik, $prijava);

        $slike = PrijavaService::visibleImages($korisnik, $prijava, (new PrijavaSlika())->forReport($id));

        View::render('prijave/show', [
            'title'     => $prijava['naslov'],
            'prijava'   => $prijava,
            'slike'     => $slike,
            'povijest'  => (new PovijestStatusa())->forReport($id),
            'sluzba'    => $sluzba,
            'mojId'     => (int) $korisnik['id'],
            'jeAdmin'   => $korisnik['uloga'] === 'admin',
            'jeRadnik'  => $korisnik['uloga'] === 'radnik' && (int) $prijava['radnik_id'] === (int) $korisnik['id'],
            'radnici'   => $korisnik['uloga'] === 'admin' ? (new Korisnik())->activeWorkers() : [],
            'maxMb'     => intdiv(PrijavaService::MAX_SLIKA, 1024 * 1024),
            'karta'     => $prijava['lat'] !== null,
        ]);
    }

    /** Slike prijava su izvan webroota; šalju se tek nakon provjere pristupa. */
    public function image(int $id, int $slikaId): void
    {
        $prijava = $this->findVisible($id);
        if ($prijava === null) {
            return;
        }
        $slika = (new PrijavaSlika())->find($id, $slikaId);
        $vidljive = $slika === null ? [] : array_column(
            PrijavaService::visibleImages(Auth::user(), $prijava, (new PrijavaSlika())->forReport($id)), 'id'
        );
        if ($slika === null || !in_array($slika['id'], $vidljive, true)) {
            View::error(404, 'Slika ne postoji.');
            return;
        }

        $putanja = ImageUpload::path($slika['datoteka'], PrijavaService::MAPA_SLIKA);
        if (!is_file($putanja)) {
            View::error(404, 'Slika ne postoji.');
            return;
        }

        header('Content-Type: ' . $slika['mime_tip']);
        header('Content-Length: ' . filesize($putanja));
        header('Cache-Control: private, max-age=3600');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        readfile($putanja);
    }

    /** Prijava koju korisnik smije vidjeti; inače šalje 404/403 i vraća null. */
    private function findVisible(int $id): ?array
    {
        $prijava = (new Prijava())->findById($id);
        if ($prijava === null) {
            View::error(404, 'Prijava ne postoji.');
            return null;
        }
        if (!PrijavaService::canView(Auth::user(), $prijava)) {
            View::error(403, 'Možete vidjeti samo vlastite prijave.');
            return null;
        }
        return $prijava;
    }

    private function form(array $old, array $errors = [], int $status = 200): void
    {
        $sifrarnik = new Sifrarnik();
        View::render('prijave/form', [
            'title'   => 'Prijava problema',
            'old'     => $old,
            'errors'  => $errors,
            'cetvrti' => $sifrarnik->cetvrti(),
            'vrste'   => $sifrarnik->vrsteProblema(),
            'maxMb'   => intdiv(PrijavaService::MAX_SLIKA, 1024 * 1024),
            'karta'   => true,
        ], $status);
    }
}
