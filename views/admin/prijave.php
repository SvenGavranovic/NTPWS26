<?php
/**
 * @var array $prijave
 * @var \App\Core\Paginator $paginator
 * @var array $filtri     ['status' => ?string, 'hitnost' => ?string, 'cetvrt' => ?int, 'q' => string]
 * @var array $cetvrti    id => naziv
 * @var array $poStatusu  status => broj
 */

use App\Services\StatusPrijave;
?>
<h1 class="h2 mb-3">Prijave</h1>
<?php require __DIR__ . '/_nav.php'; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (StatusPrijave::STATUSI as $kod => [$naziv, $boja]): ?>
        <a href="<?= e(url('/admin/prijave') . '?status=' . $kod) ?>"
           class="btn btn-sm <?= $filtri['status'] === $kod ? 'btn-' . e($boja) : 'btn-outline-' . e($boja) ?>">
            <?= e($naziv) ?> <span class="badge text-bg-light"><?= (int) ($poStatusu[$kod] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('/admin/prijave')) ?>" class="row g-2 mb-3" role="search">
    <div class="col-md-4">
        <label for="q" class="visually-hidden">Naslov</label>
        <input type="search" class="form-control" id="q" name="q" maxlength="100" placeholder="Pretraga po naslovu" value="<?= e($filtri['q']) ?>">
    </div>
    <div class="col-sm-4 col-md-2">
        <label for="status" class="visually-hidden">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="">Svi statusi</option>
            <?php foreach (StatusPrijave::STATUSI as $kod => [$naziv]): ?>
                <option value="<?= e($kod) ?>"<?= $filtri['status'] === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-4 col-md-2">
        <label for="hitnost" class="visually-hidden">Hitnost</label>
        <select class="form-select" id="hitnost" name="hitnost">
            <option value="">Sve hitnosti</option>
            <?php foreach (StatusPrijave::HITNOSTI as $kod => [$naziv]): ?>
                <option value="<?= e($kod) ?>"<?= $filtri['hitnost'] === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-4 col-md-2">
        <label for="cetvrt" class="visually-hidden">Četvrt</label>
        <select class="form-select" id="cetvrt" name="cetvrt">
            <option value="">Sve četvrti</option>
            <?php foreach ($cetvrti as $id => $naziv): ?>
                <option value="<?= (int) $id ?>"<?= $filtri['cetvrt'] === (int) $id ? ' selected' : '' ?>><?= e($naziv) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary">Filtriraj</button>
        <a href="<?= e(url('/admin/prijave')) ?>" class="btn btn-link">Poništi</a>
    </div>
</form>

<p class="small text-body-secondary"><?= $paginator->total ?> prijava. Na vrhu su prijave koje čekaju provjeru, zatim neriješene po hitnosti.</p>

<?php if ($prijave === []): ?>
    <div class="text-center text-body-secondary py-5 border rounded-3">Nema prijava koje odgovaraju filtru.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Naslov</th>
                <th scope="col">Četvrt</th>
                <th scope="col">Hitnost</th>
                <th scope="col">Status</th>
                <th scope="col">Radnik</th>
                <th scope="col">Prijavljeno</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($prijave as $p): ?>
                <tr>
                    <td class="text-body-secondary"><?= (int) $p['id'] ?></td>
                    <td>
                        <a href="<?= e(url('/prijave/' . (int) $p['id'])) ?>"><?= e($p['naslov']) ?></a>
                        <div class="small text-body-secondary"><?= e($p['vrsta']) ?></div>
                    </td>
                    <td class="small"><?= e($p['cetvrt']) ?></td>
                    <td><?= StatusPrijave::hitnostBadge($p['hitnost']) ?></td>
                    <td><?= StatusPrijave::badge($p['status']) ?></td>
                    <td class="small"><?= $p['radnik'] ? e($p['radnik']) : '<span class="text-body-secondary">—</span>' ?></td>
                    <td class="small text-nowrap"><?= e(datum($p['kreirano_u'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/paginacija.php'; ?>
<?php endif; ?>
