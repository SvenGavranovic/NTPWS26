<?php

declare(strict_types=1);

namespace App\Core;

/** Straničenje: stranice, OFFSET i poveznice koje čuvaju filtre. */
final class Paginator
{
    public readonly int $pages;
    public readonly int $page;
    public readonly int $offset;

    /**
     * @param array<string, scalar> $query  aktivni filtri
     */
    public function __construct(
        public readonly int $total,
        public readonly int $perPage,
        ?int $requestedPage,
        private readonly string $path,
        private readonly array $query = [],
    ) {
        $this->pages = max(1, (int) ceil($total / $perPage));
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
