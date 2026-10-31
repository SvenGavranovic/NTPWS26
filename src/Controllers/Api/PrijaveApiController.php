<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Core\Auth;
use App\Core\Paginator;
use App\Models\PovijestStatusa;
use App\Models\Prijava;
use App\Models\PrijavaSlika;
use App\Services\PrijavaService;
use App\Services\StatusPrijave;
use App\Services\ZadatakService;

/**
 * /api/v1/prijave
 * Popis je javni sažetak bez osobnih podataka (karta); detalj i izmjene traže prijavu.
 */
final class PrijaveApiController
{
    /** Ciljni status → uloga koja ga smije postaviti preko API-ja. */
    private const STATUS_ULOGA = ['U_RADU' => 'radnik', 'ZAVRSENO' => 'admin', 'VRACENO' => 'admin'];

    public function index(): void
    {
        $moje = get('moje') === '1';
        if ($moje && !Auth::check()) {
            Api::error(401, 'Za vlastite prijave potrebna je prijava.');
            return;
        }

        $f = [
            'status'   => StatusPrijave::exists(get('status')) ? get('status') : null,
            'cetvrt'   => get_int('cetvrt'),
            'vrsta'    => get_int('vrsta'),
            'korisnik' => $moje ? (int) Auth::user()['id'] : null,
        ];

        $model = new Prijava();
        $p = new Paginator(
            total: $model->countSummary($f),
            perPage: Api::queryInt('po_stranici', 20, 1, 100),
            requestedPage: get_int('stranica'),
            path: '/api/v1/prijave',
            query: ['status' => $f['status'], 'cetvrt' => $f['cetvrt'], 'vrsta' => $f['vrsta'], 'moje' => $moje ? 1 : null],
        );

        Api::ok(array_map([$this, 'sazetak'], $model->findSummary($f, $p->perPage, $p->offset)), Api::meta($p));
    }

    public function show(int $id): void
    {
        $prijava = $this->findVisible($id);
        if ($prijava !== null) {
            Api::ok($this->detalj($prijava));
        }
    }

    /** Nova prijava: multipart/form-data (slika + polja), kao web obrazac. */
    public function store(): void
    {
        if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data')) {
            Api::error(400, 'Prijava se šalje kao multipart/form-data (polja i datoteka "slika").');
            return;
        }
        $input = [];
        foreach (['cetvrt_id', 'vrsta_id', 'naslov', 'opis', 'adresa', 'lat', 'lng'] as $polje) {
            $input[$polje] = post($polje);
        }

        $result = (new PrijavaService())->create((int) Auth::user()['id'], $input, $_FILES['slika'] ?? null);
        if ($result['errors'] !== []) {
            !empty($result['previse'])
                ? Api::error(429, $result['errors']['opce'])
                : Api::error(400, 'Prijava nije spremljena.', $result['errors']);
            return;
        }
        Api::created($this->detalj((new Prijava())->findById($result['id'])), '/api/v1/prijave/' . $result['id']);
    }

    /** Jedna radnja po zahtjevu: radnik_id, hitnost ili status (vidi schemas/prijava-izmjena.json). */
    public function update(int $id): void
    {
        $body = Api::body();
        if ($body === null || !Api::validate($body, 'prijava-izmjena')) {
            return;
        }
        $prijava = (new Prijava())->findById($id);
        if ($prijava === null) {
            Api::error(404, 'Prijava ne postoji.');
            return;
        }

        $korisnik = Auth::user();
        $servis = new ZadatakService();

        if (isset($body->radnik_id) || isset($body->hitnost)) {
            if ($korisnik['uloga'] !== 'admin') {
                Api::error(403, 'Dodjelu i hitnost mijenja samo administrator.');
                return;
            }
            if (isset($body->radnik_id) && !StatusPrijave::canTransition($prijava['status'], 'DODIJELJENO', 'admin')) {
                Api::error(409, 'Prijava u ovom statusu ne može se dodijeliti.');
                return;
            }
            $greska = isset($body->radnik_id)
                ? $servis->assign($korisnik, $prijava, $body->radnik_id)
                : $servis->setHitnost($korisnik, $prijava, $body->hitnost);
        } else {
            $u = $body->status;
            if ($u === 'VRACENO' && !isset($body->komentar)) {
                Api::error(400, 'Uz status VRACENO obavezan je komentar.', ['komentar' => 'The property komentar is required']);
                return;
            }
            if ($korisnik['uloga'] !== self::STATUS_ULOGA[$u]
                || ($korisnik['uloga'] === 'radnik' && (int) $prijava['radnik_id'] !== (int) $korisnik['id'])) {
                Api::error(403, 'Nemate ovlasti za ovu promjenu statusa.');
                return;
            }
            if (!StatusPrijave::canTransition($prijava['status'], $u, $korisnik['uloga'])) {
                Api::error(409, "Prijelaz {$prijava['status']} → {$u} nije dopušten.");
                return;
            }
            $greska = match ($u) {
                'U_RADU'   => $servis->start($korisnik, $prijava),
                'ZAVRSENO' => $servis->approve($korisnik, $prijava),
                'VRACENO'  => $servis->reject($korisnik, $prijava, $body->komentar),
            };
        }

        if ($greska !== null) {
            Api::error($greska === ZadatakService::KONFLIKT ? 409 : 400, $greska);
            return;
        }
        Api::ok($this->detalj((new Prijava())->findById($id)));
    }

    private function findVisible(int $id): ?array
    {
        $prijava = (new Prijava())->findById($id);
        if ($prijava === null) {
            Api::error(404, 'Prijava ne postoji.');
            return null;
        }
        if (!PrijavaService::canView(Auth::user(), $prijava)) {
            Api::error(403, 'Nemate pristup ovoj prijavi.');
            return null;
        }
        return $prijava;
    }

    private function sazetak(array $p): array
    {
        return [
            'id'         => (int) $p['id'],
            'naslov'     => $p['naslov'],
            'status'     => $p['status'],
            'hitnost'    => $p['hitnost'],
            'vrsta'      => ['id' => (int) $p['vrsta_id'], 'naziv' => $p['vrsta']],
            'cetvrt'     => ['id' => (int) $p['cetvrt_id'], 'naziv' => $p['cetvrt']],
            'lokacija'   => $p['lat'] === null ? null : ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']],
            'kreirano_u' => Api::date($p['kreirano_u']),
        ];
    }

    /** Detalj s istim pravilima vidljivosti kao web stranica prijave. */
    private function detalj(array $p): array
    {
        $korisnik = Auth::user();
        $sluzba = PrijavaService::isStaff($korisnik, $p);
        $id = (int) $p['id'];

        $slike = PrijavaService::visibleImages($korisnik, $p, (new PrijavaSlika())->forReport($id));
        $povijest = (new PovijestStatusa())->forReport($id);

        return [
            'id'          => $id,
            'naslov'      => $p['naslov'],
            'opis'        => $p['opis'],
            'adresa'      => $p['adresa'],
            'status'      => $p['status'],
            'hitnost'     => $p['hitnost'],
            'vrsta'       => ['id' => (int) $p['vrsta_id'], 'naziv' => $p['vrsta']],
            'cetvrt'      => ['id' => (int) $p['cetvrt_id'], 'naziv' => $p['cetvrt']],
            'lokacija'    => $p['lat'] === null ? null : ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']],
            'kreirano_u'  => Api::date($p['kreirano_u']),
            'azurirano_u' => Api::date($p['azurirano_u']),
            'prijavitelj' => $sluzba ? $p['prijavitelj'] : null,
            'radnik'      => $sluzba && $p['radnik_id'] ? ['id' => (int) $p['radnik_id'], 'ime' => $p['radnik']] : null,
            'napomena_radnika' => $sluzba ? $p['napomena_radnika'] : null,
            'slike' => array_map(static fn (array $s): array => [
                'id'  => (int) $s['id'],
                'tip' => $s['tip'],
                'url' => url('/prijave/' . $id . '/slike/' . (int) $s['id']),
            ], $slike),
            'povijest' => array_map(static fn (array $h): array => [
                'iz'       => $h['iz_statusa'],
                'u'        => $h['u_status'],
                'vrijeme'  => Api::date($h['kreirano_u']),
                'uloga'    => $h['uloga'],
                'osoba'    => $sluzba || (int) $h['korisnik_id'] === (int) $korisnik['id'] ? $h['osoba'] : null,
                'komentar' => $sluzba ? $h['komentar'] : null,
            ], $povijest),
        ];
    }
}
