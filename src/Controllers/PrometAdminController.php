<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\SkriveniIncident;
use App\Services\PrometService;

/** Pregled TomTom incidenata i skrivanje zastarjelih ili pogrešnih (admin). */
final class PrometAdminController
{
    public function index(): void
    {
        $rezultat = (new PrometService())->get(null, sakrij: false);
        $skriveni = array_flip((new SkriveniIncident())->hashes());

        $incidenti = $rezultat['data']['incidenti'] ?? [];
        // najstariji prvi: dugotrajni zapisi bez kraja najčešće su zastarjeli
        usort($incidenti, static fn (array $a, array $b): int => strcmp($a['pocetak'] ?? '9999', $b['pocetak'] ?? '9999'));

        View::render('admin/promet', [
            'title'     => 'Promet',
            'promet'    => $rezultat['data'] ?? null,
            'greska'    => $rezultat['poruka'] ?? null,
            'incidenti' => $incidenti,
            'skriveniHash' => $skriveni,
            'skriveni'  => (new SkriveniIncident())->all(),
        ]);
    }

    public function hide(): void
    {
        $id = trim(post('incident_id'));
        $razlog = trim(post('razlog'));

        if (mb_strlen($razlog) < 3 || mb_strlen($razlog) > 255) {
            Session::flash('danger', 'Upišite razlog skrivanja (3–255 znakova).');
            redirect('/admin/promet');
        }

        // opis se uzima iz trenutnih podataka, ne iz obrasca
        $incident = null;
        foreach ((new PrometService())->get(null, sakrij: false)['data']['incidenti'] ?? [] as $i) {
            if ($i['id'] !== null && $i['id'] === $id) {
                $incident = $i;
            }
        }
        if ($incident === null) {
            Session::flash('danger', 'Incident više nije u podacima TomToma.');
            redirect('/admin/promet');
        }

        $opis = $incident['kategorija_naziv'] . ': ' . trim(($incident['od'] ?? '') . ' → ' . ($incident['do'] ?? ''), ' →');
        (new SkriveniIncident())->hide($id, $opis, $razlog, (int) Auth::user()['id']);
        Session::flash('success', 'Incident je skriven: ' . $opis);
        redirect('/admin/promet');
    }

    public function unhide(int $id): void
    {
        if (!(new SkriveniIncident())->unhide($id)) {
            View::error(404);
            return;
        }
        Session::flash('success', 'Incident se ponovno prikazuje (ako je još u podacima TomToma).');
        redirect('/admin/promet');
    }
}
