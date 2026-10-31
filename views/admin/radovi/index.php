<?php
/**
 * @var array $radovi
 * @var \App\Core\Paginator $paginator
 * @var bool $aktivni
 */

use App\Services\RadService;

$stanja = ['u_tijeku' => ['U tijeku', 'warning'], 'planirano' => ['Planirano', 'info'], 'zavrseno' => ['Završeno', 'secondary']];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Radovi na infrastrukturi</h1>
    <a href="<?= e(url('/admin/radovi/novi')) ?>" class="btn btn-primary">+ Novi radovi</a>
</div>
<?php require __DIR__ . '/../_nav.php'; ?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="<?= e(url('/admin/radovi')) ?>" class="btn btn-sm <?= $aktivni ? 'btn-outline-primary' : 'btn-primary' ?>">Svi</a>
    <a href="<?= e(url('/admin/radovi') . '?aktivni=1') ?>" class="btn btn-sm <?= $aktivni ? 'btn-primary' : 'btn-outline-primary' ?>">Samo u tijeku</a>
    <span class="small text-body-secondary ms-2"><?= $paginator->total ?> radova · <a href="<?= e(url('/karta')) ?>">prikaz na karti</a></span>
</div>

<?php if ($radovi === []): ?>
    <div class="text-center text-body-secondary py-5 border rounded-3">Nema radova.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th scope="col">Naslov</th>
                <th scope="col">Vrsta</th>
                <th scope="col">Utjecaj na promet</th>
                <th scope="col">Razdoblje</th>
                <th scope="col">Stanje</th>
                <th scope="col" class="text-end">Radnje</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($radovi as $r): [$naziv, $boja] = $stanja[RadService::stanje($r)]; ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/radovi/' . (int) $r['id'] . '/uredi')) ?>"><?= e($r['naslov']) ?></a>
                        <div class="small text-body-secondary"><?= e($r['cetvrt'] ?? '') ?><?= $r['adresa'] ? ' · ' . e($r['adresa']) : '' ?></div>
                    </td>
                    <td class="small"><?= e(RadService::VRSTE[$r['vrsta']]) ?></td>
                    <td class="small"><?= e(RadService::UTJECAJI[$r['utjecaj_na_promet']]) ?></td>
                    <td class="small text-nowrap"><?= e(datum($r['pocetak'])) ?> – <?= $r['kraj'] ? e(datum($r['kraj'])) : '?' ?></td>
                    <td><span class="badge text-bg-<?= e($boja) ?>"><?= e($naziv) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('/admin/radovi/' . (int) $r['id'] . '/uredi')) ?>" class="btn btn-sm btn-outline-primary">Uredi</a>
                        <form method="post" action="<?= e(url('/admin/radovi/' . (int) $r['id'] . '/obrisi')) ?>" class="d-inline"
                              data-potvrda="Obrisati radove „<?= e($r['naslov']) ?>”?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Obriši</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../../partials/paginacija.php'; ?>
<?php endif; ?>
