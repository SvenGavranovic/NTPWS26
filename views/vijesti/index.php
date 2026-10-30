<?php
/**
 * @var array $vijesti
 * @var array $kategorije
 * @var \App\Core\Paginator $paginator
 * @var string $q
 * @var int|null $kategorijaId
 */

$filterUrl = static function (?int $kategorija, string $q): string {
    $query = array_filter(['q' => $q, 'kategorija' => $kategorija], static fn ($v) => $v !== null && $v !== '');
    return url('/vijesti') . ($query ? '?' . http_build_query($query) : '');
};

$odabrana = null;
foreach ($kategorije as $k) {
    if ((int) $k['id'] === $kategorijaId) {
        $odabrana = $k;
    }
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-baseline mb-4 gap-2">
    <h1 class="h2 mb-0">Vijesti</h1>
    <span class="text-body-secondary" id="vijesti-broj">
        <?= $paginator->total ?> <?= $paginator->total === 1 ? 'vijest' : 'vijesti' ?>
        <?php if ($paginator->hasPages()): ?>· stranica <?= $paginator->page ?> od <?= $paginator->pages ?><?php endif; ?>
    </span>
</div>

<div class="row g-4">
    <aside class="col-lg-3">
        <form method="get" action="<?= e(url('/vijesti')) ?>" class="mb-4" role="search">
            <label for="q" class="form-label fw-semibold">Pretraga</label>
            <div class="input-group">
                <input type="search" class="form-control" id="q" name="q" maxlength="100" autocomplete="off"
                       placeholder="Naslov ili sadržaj" value="<?= e($q) ?>">
                <button class="btn btn-primary" type="submit">Traži</button>
            </div>
            <?php if ($kategorijaId !== null): ?>
                <input type="hidden" name="kategorija" value="<?= (int) $kategorijaId ?>">
            <?php endif; ?>
        </form>

        <h2 class="h6 fw-semibold">Kategorije</h2>
        <div class="list-group">
            <a href="<?= e($filterUrl(null, $q)) ?>"
               class="list-group-item list-group-item-action<?= $kategorijaId === null ? ' active' : '' ?>">
                Sve kategorije
            </a>
            <?php foreach ($kategorije as $k): ?>
                <?php $aktivna = (int) $k['id'] === $kategorijaId; ?>
                <a href="<?= e($filterUrl((int) $k['id'], $q)) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= $aktivna ? ' active' : '' ?>"
                    <?= $aktivna ? 'aria-current="true"' : '' ?>>
                    <?= e($k['naziv']) ?>
                    <span class="badge rounded-pill <?= $aktivna ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= (int) $k['broj_vijesti'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="col-lg-9" id="vijesti-rezultati"
         data-api="<?= e(url('/api/v1/vijesti')) ?>" data-stranica="<?= e(url('/vijesti')) ?>"
         data-kategorija="<?= $kategorijaId ?? '' ?>" data-po-stranici="<?= (int) $paginator->perPage ?>">
        <?php if ($q !== '' || $odabrana !== null): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 small" id="vijesti-filtri">
                <span class="text-body-secondary">Filtri:</span>
                <?php if ($q !== ''): ?>
                    <a class="badge text-bg-light border text-decoration-none" href="<?= e($filterUrl($kategorijaId, '')) ?>"
                       title="Ukloni pretragu">„<?= e($q) ?>” &times;</a>
                <?php endif; ?>
                <?php if ($odabrana !== null): ?>
                    <a class="badge text-bg-light border text-decoration-none" href="<?= e($filterUrl(null, $q)) ?>"
                       title="Ukloni kategoriju"><?= e($odabrana['naziv']) ?> &times;</a>
                <?php endif; ?>
                <a href="<?= e(url('/vijesti')) ?>" class="ms-1">Očisti sve</a>
            </div>
        <?php endif; ?>

        <?php if ($vijesti === []): ?>
            <div class="text-center border rounded-3 py-5 px-3 bg-body-tertiary">
                <p class="h5">Nema pronađenih vijesti</p>
                <p class="text-body-secondary mb-3">
                    <?php if ($q !== ''): ?>
                        Nijedna vijest ne odgovara pretrazi „<?= e($q) ?>”<?= $odabrana ? ' u kategoriji ' . e($odabrana['naziv']) : '' ?>.
                    <?php else: ?>
                        U ovoj kategoriji još nema objavljenih vijesti.
                    <?php endif; ?>
                </p>
                <a href="<?= e(url('/vijesti')) ?>" class="btn btn-outline-primary">Prikaži sve vijesti</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($vijesti as $vijest): ?>
                    <div class="col-md-6">
                        <?php require __DIR__ . '/../partials/vijest_kartica.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php require __DIR__ . '/../partials/paginacija.php'; ?>
        <?php endif; ?>
    </div>
</div>
