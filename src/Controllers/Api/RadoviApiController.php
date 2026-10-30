<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Core\Paginator;
use App\Models\Rad;

/** /api/v1/radovi – javno. */
final class RadoviApiController
{
    private const VRSTE = ['cesta', 'vodovod', 'kanalizacija', 'plinovod', 'toplovod', 'elektroinstalacije', 'tramvajska_pruga', 'ostalo'];

    public function index(): void
    {
        $f = [
            'aktivni' => get('aktivni') === '1',
            'cetvrt'  => get_int('cetvrt'),
            'vrsta'   => in_array(get('vrsta'), self::VRSTE, true) ? get('vrsta') : null,
        ];

        $model = new Rad();
        $p = new Paginator(
            total: $model->count($f),
            perPage: Api::queryInt('po_stranici', 50, 1, 100),
            requestedPage: get_int('stranica'),
            path: '/api/v1/radovi',
            query: ['aktivni' => $f['aktivni'] ? 1 : null, 'cetvrt' => $f['cetvrt'], 'vrsta' => $f['vrsta']],
        );

        Api::ok(array_map(static fn (array $r): array => [
            'id'                => (int) $r['id'],
            'naslov'            => $r['naslov'],
            'opis'              => $r['opis'],
            'vrsta'             => $r['vrsta'],
            'utjecaj_na_promet' => $r['utjecaj_na_promet'],
            'cetvrt'            => $r['cetvrt_id'] ? ['id' => (int) $r['cetvrt_id'], 'naziv' => $r['cetvrt']] : null,
            'adresa'            => $r['adresa'],
            'lokacija'          => ['lat' => (float) $r['lat'], 'lng' => (float) $r['lng']],
            'pocetak'           => Api::date($r['pocetak']),
            'kraj'              => Api::date($r['kraj']),
        ], $model->find($f, $p->perPage, $p->offset)), Api::meta($p));
    }
}
