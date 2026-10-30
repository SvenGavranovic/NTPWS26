<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Core\Auth;
use App\Core\Paginator;
use App\Models\Vijest;
use App\Models\VijestCms;
use App\Services\NewsAdminService;

/** /api/v1/vijesti – čitanje javno (samo objavljene), izmjene urednik i admin. */
final class VijestiApiController
{
    public function index(): void
    {
        $q = mb_substr(get('q'), 0, 100);
        $kategorija = get_int('kategorija');

        $model = new Vijest();
        $p = new Paginator(
            total: $model->countPublished($kategorija, $q),
            perPage: Api::queryInt('po_stranici', 10, 1, 50),
            requestedPage: get_int('stranica'),
            path: '/api/v1/vijesti',
            query: ['q' => $q, 'kategorija' => $kategorija, 'po_stranici' => get('po_stranici')],
        );

        $vijesti = array_map([$this, 'javna'], $model->findPublished($kategorija, $q, $p->perPage, $p->offset));

        Api::ok($vijesti, Api::meta($p) + ['linkovi' => [
            'prethodna' => $p->page > 1 ? $p->url($p->page - 1) : null,
            'sljedeca'  => $p->page < $p->pages ? $p->url($p->page + 1) : null,
        ]]);
    }

    public function show(int $id): void
    {
        $vijest = (new Vijest())->findPublishedById($id);
        if ($vijest === null) {
            Api::error(404, 'Vijest ne postoji ili nije objavljena.');
            return;
        }
        Api::ok($this->javna($vijest));
    }

    public function store(): void
    {
        $body = Api::body();
        if ($body === null || !Api::validate($body, 'vijest-nova')) {
            return;
        }

        $result = (new NewsAdminService())->create(
            ['status' => 'nacrt'] + (array) $body,
            null,
            (int) Auth::user()['id'],
        );
        if ($result['errors'] !== []) {
            Api::error(400, 'Vijest nije spremljena.', $result['errors']);
            return;
        }
        Api::created($this->cms((new VijestCms())->findById($result['id'])), '/api/v1/vijesti/' . $result['id']);
    }

    public function update(int $id): void
    {
        $vijest = $this->findEditable($id);
        $body = $vijest === null ? null : Api::body();
        if ($body === null || !Api::validate($body, 'vijest-izmjena')) {
            return;
        }

        // šalju se samo polja koja se mijenjaju; ostala ostaju iz baze
        $input = (array) $body + array_intersect_key($vijest, array_flip(['naslov', 'sazetak', 'sadrzaj', 'kategorija_id', 'status']));
        $errors = (new NewsAdminService())->update($vijest, $input, null);
        if ($errors !== []) {
            Api::error(400, 'Vijest nije spremljena.', $errors);
            return;
        }
        Api::ok($this->cms((new VijestCms())->findById($id)));
    }

    public function destroy(int $id): void
    {
        $vijest = $this->findEditable($id);
        if ($vijest !== null) {
            (new NewsAdminService())->delete($vijest);
            Api::noContent();
        }
    }

    private function findEditable(int $id): ?array
    {
        $vijest = (new VijestCms())->findById($id);
        if ($vijest === null) {
            Api::error(404, 'Vijest ne postoji.');
            return null;
        }
        if (!NewsAdminService::canEdit(Auth::user(), $vijest)) {
            Api::error(403, 'Možete mijenjati samo vlastite vijesti.');
            return null;
        }
        return $vijest;
    }

    private function javna(array $v): array
    {
        $data = [
            'id'           => (int) $v['id'],
            'naslov'       => $v['naslov'],
            'sazetak'      => $v['sazetak'],
            'kategorija'   => ['id' => (int) $v['kategorija_id'], 'naziv' => $v['kategorija']],
            'autor'        => $v['autor'],
            'datum_objave' => Api::date($v['datum_objave']),
            'slika'        => $v['slika'] ? url('uploads/vijesti/' . $v['slika']) : null,
            'url'          => url('/vijesti/' . (int) $v['id']),
        ];
        if (isset($v['sadrzaj'])) {
            $data['sadrzaj'] = $v['sadrzaj'];
        }
        return $data;
    }

    private function cms(array $v): array
    {
        return [
            'id'            => (int) $v['id'],
            'naslov'        => $v['naslov'],
            'sazetak'       => $v['sazetak'],
            'sadrzaj'       => $v['sadrzaj'],
            'kategorija_id' => (int) $v['kategorija_id'],
            'autor_id'      => (int) $v['autor_id'],
            'status'        => $v['status'],
            'datum_objave'  => Api::date($v['datum_objave']),
            'slika'         => $v['slika'] ? url('uploads/vijesti/' . $v['slika']) : null,
        ];
    }
}
