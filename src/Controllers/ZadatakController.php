<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\Prijava;
use App\Models\Zadatak;
use App\Services\ZadatakService;

/** Kanban radnika i radnje nad vlastitim zadacima. */
final class ZadatakController
{
    /** status => naslov stupca */
    private const STUPCI = [
        'DODIJELJENO'   => 'Novi zadaci',
        'VRACENO'       => 'Vraćeno na doradu',
        'U_RADU'        => 'U radu',
        'CEKA_PROVJERU' => 'Čeka provjeru',
        'ZAVRSENO'      => 'Završeno (14 dana)',
    ];

    public function index(): void
    {
        $stupci = array_fill_keys(array_keys(self::STUPCI), []);
        foreach ((new Zadatak())->forWorker((int) Auth::user()['id']) as $z) {
            $stupci[$z['status']][] = $z;
        }

        View::render('radnik/kanban', [
            'title'   => 'Moji zadaci',
            'stupci'  => $stupci,
            'nazivi'  => self::STUPCI,
            'kanban'  => true, // layout učitava SortableJS samo ovdje
        ]);
    }

    /** DODIJELJENO/VRACENO → U_RADU */
    public function start(int $id): void
    {
        $prijava = $this->find($id);
        if ($prijava !== null) {
            $this->respond((new ZadatakService())->start(Auth::user(), $prijava), 'Rad na zadatku je započet.', $id);
        }
    }

    /** U_RADU → CEKA_PROVJERU (slika "poslije" i napomena). */
    public function submit(int $id): void
    {
        $prijava = $this->find($id);
        if ($prijava !== null) {
            $greska = (new ZadatakService())->submit(Auth::user(), $prijava, post('napomena'), $_FILES['slika'] ?? null);
            $this->respond($greska, 'Posao je poslan na provjeru.', $id);
        }
    }

    private function find(int $id): ?array
    {
        $prijava = (new Prijava())->findById($id);
        if ($prijava === null) {
            wants_json() ? json_response(['ok' => false, 'poruka' => 'Prijava ne postoji.'], 404) : View::error(404);
        }
        return $prijava;
    }

    /** JSON za fetch iz kanbana, inače flash poruka i preusmjeravanje. */
    private function respond(?string $greska, string $uspjeh, int $id): void
    {
        if (wants_json()) {
            $greska === null
                ? json_response(['ok' => true, 'poruka' => $uspjeh])
                : json_response(['ok' => false, 'poruka' => $greska], 409);
            return;
        }
        Session::flash($greska === null ? 'success' : 'danger', $greska ?? $uspjeh);
        redirect('/prijave/' . $id . ($greska === null ? '' : '#radnje'));
    }
}
