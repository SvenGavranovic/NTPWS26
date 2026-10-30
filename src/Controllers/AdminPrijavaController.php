<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\View;
use App\Models\Prijava;
use App\Models\Sifrarnik;
use App\Services\StatusPrijave;
use App\Services\ZadatakService;

/** Administracija prijava: popis, dodjela, hitnost, odobrenje i vraćanje. */
final class AdminPrijavaController
{
    private const PO_STRANICI = 15;

    public function index(): void
    {
        $filtri = [
            'status'  => StatusPrijave::exists(get('status')) ? get('status') : null,
            'hitnost' => isset(StatusPrijave::HITNOSTI[get('hitnost')]) ? get('hitnost') : null,
            'cetvrt'  => get_int('cetvrt'),
            'q'       => mb_substr(get('q'), 0, 100),
        ];

        $model = new Prijava();
        $paginator = new Paginator(
            total: $model->countForAdmin($filtri),
            perPage: self::PO_STRANICI,
            requestedPage: get_int('stranica'),
            path: '/admin/prijave',
            query: $filtri,
        );

        View::render('admin/prijave', [
            'title'     => 'Prijave',
            'prijave'   => $model->findForAdmin($filtri, $paginator->perPage, $paginator->offset),
            'paginator' => $paginator,
            'filtri'    => $filtri,
            'cetvrti'   => (new Sifrarnik())->cetvrti(),
            'poStatusu' => $model->countByStatus(),
        ]);
    }

    public function assign(int $id): void
    {
        $this->run($id, fn (array $p) => (new ZadatakService())->assign(Auth::user(), $p, (int) post('radnik_id')), 'Prijava je dodijeljena radniku.');
    }

    public function hitnost(int $id): void
    {
        $this->run($id, fn (array $p) => (new ZadatakService())->setHitnost(Auth::user(), $p, post('hitnost')), 'Hitnost je promijenjena.');
    }

    public function approve(int $id): void
    {
        $this->run($id, fn (array $p) => (new ZadatakService())->approve(Auth::user(), $p), 'Posao je odobren, prijava je završena.');
    }

    public function reject(int $id): void
    {
        $this->run($id, fn (array $p) => (new ZadatakService())->reject(Auth::user(), $p, post('komentar')), 'Posao je vraćen radniku na doradu.');
    }

    /** Učita prijavu, izvrši radnju i vrati se na detalj prijave s porukom. */
    private function run(int $id, callable $radnja, string $uspjeh): void
    {
        $prijava = (new Prijava())->findById($id);
        if ($prijava === null) {
            View::error(404);
            return;
        }
        $greska = $radnja($prijava);
        Session::flash($greska === null ? 'success' : 'danger', $greska ?? $uspjeh);
        redirect('/prijave/' . $id);
    }
}
