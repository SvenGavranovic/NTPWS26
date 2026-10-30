<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Paginator;
use App\Core\View;
use App\Models\Kategorija;
use App\Models\Vijest;

/** Javni popis i detalj vijesti. */
final class NewsController
{
    private const PO_STRANICI = 4;
    private const MAX_DULJINA_PRETRAGE = 100;

    public function index(): void
    {
        $q = mb_substr(get('q'), 0, self::MAX_DULJINA_PRETRAGE);
        $kategorijaId = get_int('kategorija');

        $vijesti = new Vijest();
        $paginator = new Paginator(
            total: $vijesti->countPublished($kategorijaId, $q),
            perPage: self::PO_STRANICI,
            requestedPage: get_int('stranica'),
            path: '/vijesti',
            query: ['q' => $q, 'kategorija' => $kategorijaId],
        );

        View::render('vijesti/index', [
            'title'        => 'Vijesti',
            'vijesti'      => $vijesti->findPublished($kategorijaId, $q, $paginator->perPage, $paginator->offset),
            'kategorije'   => (new Kategorija())->allWithPublishedCount(),
            'paginator'    => $paginator,
            'q'            => $q,
            'kategorijaId' => $kategorijaId,
            'vijestiUzivo' => true,
        ]);
    }

    public function show(int $id): void
    {
        $vijest = (new Vijest())->findPublishedById($id);

        // nacrt i nepostojeća vijest: isti odgovor
        if ($vijest === null) {
            View::error(404, 'Vijest ne postoji ili nije objavljena.');
            return;
        }

        View::render('vijesti/show', ['title' => $vijest['naslov'], 'vijest' => $vijest]);
    }
}
