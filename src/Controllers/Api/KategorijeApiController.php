<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Models\Kategorija;

/** /api/v1/kategorije */
final class KategorijeApiController
{
    public function index(): void
    {
        Api::ok(array_map(static fn (array $k): array => [
            'id'           => (int) $k['id'],
            'naziv'        => $k['naziv'],
            'broj_vijesti' => (int) $k['broj_vijesti'], // samo objavljene
        ], (new Kategorija())->allWithPublishedCount()));
    }
}
