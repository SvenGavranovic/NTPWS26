<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\View;
use App\Models\Rad;
use App\Models\Sifrarnik;
use App\Services\RadService;

/** Radovi na infrastrukturi (admin). */
final class RadAdminController
{
    private const PO_STRANICI = 15;
    private const POLJA = ['naslov', 'opis', 'vrsta', 'utjecaj_na_promet', 'cetvrt_id', 'adresa', 'lat', 'lng', 'pocetak', 'kraj'];

    public function index(): void
    {
        $f = ['aktivni' => get('aktivni') === '1', 'cetvrt' => null, 'vrsta' => null];
        $model = new Rad();
        $p = new Paginator(
            total: $model->count($f),
            perPage: self::PO_STRANICI,
            requestedPage: get_int('stranica'),
            path: '/admin/radovi',
            query: ['aktivni' => $f['aktivni'] ? 1 : null],
        );

        View::render('admin/radovi/index', [
            'title'     => 'Radovi',
            'radovi'    => $model->find($f, $p->perPage, $p->offset),
            'paginator' => $p,
            'aktivni'   => $f['aktivni'],
        ]);
    }

    public function create(): void
    {
        $this->form(['utjecaj_na_promet' => 'nema']);
    }

    public function store(): void
    {
        $result = (new RadService())->save(null, $this->input(), (int) Auth::user()['id']);
        if ($result['errors'] !== []) {
            $this->form($this->input(), $result['errors'], 422);
            return;
        }
        Session::flash('success', 'Radovi su spremljeni.');
        redirect('/admin/radovi');
    }

    public function edit(int $id): void
    {
        $rad = (new Rad())->findById($id);
        if ($rad === null) {
            View::error(404);
            return;
        }
        // datetime-local očekuje "2026-11-03T08:00"
        foreach (['pocetak', 'kraj'] as $k) {
            $rad[$k] = $rad[$k] ? substr(str_replace(' ', 'T', $rad[$k]), 0, 16) : '';
        }
        $this->form($rad);
    }

    public function update(int $id): void
    {
        if ((new Rad())->findById($id) === null) {
            View::error(404);
            return;
        }
        $result = (new RadService())->save($id, $this->input(), (int) Auth::user()['id']);
        if ($result['errors'] !== []) {
            $this->form(['id' => $id] + $this->input(), $result['errors'], 422);
            return;
        }
        Session::flash('success', 'Promjene su spremljene.');
        redirect('/admin/radovi');
    }

    public function destroy(int $id): void
    {
        $rad = (new Rad())->findById($id);
        if ($rad === null) {
            View::error(404);
            return;
        }
        (new Rad())->delete($id);
        Session::flash('success', 'Radovi „' . $rad['naslov'] . '” su obrisani.');
        redirect('/admin/radovi');
    }

    private function input(): array
    {
        $input = [];
        foreach (self::POLJA as $polje) {
            $input[$polje] = post($polje);
        }
        return $input;
    }

    private function form(array $rad, array $errors = [], int $status = 200): void
    {
        View::render('admin/radovi/form', [
            'title'   => isset($rad['id']) ? 'Uređivanje radova' : 'Novi radovi',
            'rad'     => $rad,
            'errors'  => $errors,
            'cetvrti' => (new Sifrarnik())->cetvrti(),
            'karta'   => true,
        ], $status);
    }
}
