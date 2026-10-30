<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Kategorija;
use App\Services\CategoryService;

/** Kategorije vijesti (/admin/kategorije), samo administrator (config/routes.php). */
final class CategoryController
{
    public function index(): void
    {
        // ?uredi=3 → obrazac na vrhu stranice popunjen podacima kategorije 3
        $uredi = get_int('uredi');
        $kategorija = $uredi !== null ? (new Kategorija())->findById($uredi) : null;

        $this->render($kategorija ?? []);
    }

    public function store(): void
    {
        $errors = (new CategoryService())->save(null, post('naziv'), post('opis'));
        if ($errors !== []) {
            $this->render(['naziv' => post('naziv'), 'opis' => post('opis')], $errors, 422);
            return;
        }
        Session::flash('success', 'Kategorija je dodana.');
        redirect('/admin/kategorije');
    }

    public function update(int $id): void
    {
        if ((new Kategorija())->findById($id) === null) {
            View::error(404);
            return;
        }
        $errors = (new CategoryService())->save($id, post('naziv'), post('opis'));
        if ($errors !== []) {
            $this->render(['id' => $id, 'naziv' => post('naziv'), 'opis' => post('opis')], $errors, 422);
            return;
        }
        Session::flash('success', 'Kategorija je spremljena.');
        redirect('/admin/kategorije');
    }

    public function destroy(int $id): void
    {
        $kategorija = (new Kategorija())->findById($id);
        if ($kategorija === null) {
            View::error(404);
            return;
        }

        $greska = (new CategoryService())->delete($id);
        if ($greska !== null) {
            Session::flash('danger', $greska);
        } else {
            Session::flash('success', 'Kategorija „' . $kategorija['naziv'] . '” je obrisana.');
        }
        redirect('/admin/kategorije');
    }

    private function render(array $kategorija, array $errors = [], int $status = 200): void
    {
        View::render('admin/kategorije', [
            'title'      => 'Kategorije',
            'kategorija' => $kategorija,
            'kategorije' => (new Kategorija())->allWithTotalCount(),
            'errors'     => $errors,
        ], $status);
    }
}
