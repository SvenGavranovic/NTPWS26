<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Izračun straničenja: koja je stranica, koliko ih ima i od kojeg retka
 * počinje upit (OFFSET). Gradi i poveznice koje čuvaju aktivne filtre.
 */
final class Paginator
{
    public readonly int $pages;
    public readonly int $page;
    public readonly int $offset;

    /**
     * @param array<string, scalar> $query  aktivni filtri koje poveznice moraju zadržati
     */
    public function __construct(
        public readonly int $total,
        public readonly int $perPage,
        ?int $requestedPage,
        private readonly string $path,
        private readonly array $query = [],
    ) {
        $this->pages = max(1, (int) ceil($total / $perPage));
        // ?stranica=999 ili ?stranica=-5 → najbliža postojeća stranica
        $this->page = min(max(1, $requestedPage ?? 1), $this->pages);
        $this->offset = ($this->page - 1) * $perPage;
    }

    public function url(int $page): string
    {
        $query = array_filter($this->query, static fn ($v) => $v !== null && $v !== '');
        if ($page > 1) {
            $query['stranica'] = $page;
        }
        return url($this->path) . ($query ? '?' . http_build_query($query) : '');
    }

    public function hasPages(): bool
    {
        return $this->pages > 1;
    }
}
