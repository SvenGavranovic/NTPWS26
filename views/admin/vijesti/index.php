<?php
/**
 * @var array $vijesti
 * @var \App\Core\Paginator $paginator
 * @var string $q
 * @var string|null $status
 * @var bool $sveVijesti  admin vidi sve vijesti (i stupac autora)
 * @var string $title
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><?= e($title) ?></h1>
    <a href="<?= e(url('/admin/vijesti/nova')) ?>" class="btn btn-primary">+ Nova vijest</a>
</div>
<?php require __DIR__ . '/../_nav.php'; ?>

<form method="get" action="<?= e(url('/admin/vijesti')) ?>" class="row g-2 mb-3" role="search">
    <div class="col-sm-6 col-md-5">
        <label for="q" class="visually-hidden">Pretraga po naslovu</label>
        <input type="search" class="form-control" id="q" name="q" maxlength="100" placeholder="Pretraga po naslovu"
               value="<?= e($q) ?>">
    </div>
    <div class="col-sm-4 col-md-3">
        <label for="status" class="visually-hidden">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="">Svi statusi</option>
            <option value="objavljeno"<?= $status === 'objavljeno' ? ' selected' : '' ?>>Objavljeno</option>
            <option value="nacrt"<?= $status === 'nacrt' ? ' selected' : '' ?>>Nacrt</option>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary">Filtriraj</button>
    </div>
</form>

<?php if ($vijesti === []): ?>
    <div class="text-center text-body-secondary py-5 border rounded-3">
        <?= $q !== '' || $status !== null ? 'Nema vijesti koje odgovaraju filtru.' : 'Još nema vijesti. Napišite prvu!' ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th scope="col">Naslov</th>
                <th scope="col">Kategorija</th>
                <?php if ($sveVijesti): ?><th scope="col">Autor</th><?php endif; ?>
                <th scope="col">Status</th>
                <th scope="col">Zadnja izmjena</th>
                <th scope="col" class="text-end">Radnje</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($vijesti as $v): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/vijesti/' . (int) $v['id'] . '/uredi')) ?>"><?= e($v['naslov']) ?></a></td>
                    <td class="text-nowrap"><?= e($v['kategorija']) ?></td>
                    <?php if ($sveVijesti): ?><td class="text-nowrap"><?= e($v['autor']) ?></td><?php endif; ?>
                    <td>
                        <?php if ($v['status'] === 'objavljeno'): ?>
                            <span class="badge text-bg-success">Objavljeno</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Nacrt</span>
                            <?php if ($v['datum_objave']): ?><span class="small text-body-secondary d-block">povučeno</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap small"><?= e(datum($v['azurirano_u'], true)) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($v['status'] === 'objavljeno'): ?>
                            <a href="<?= e(url('/vijesti/' . (int) $v['id'])) ?>" class="btn btn-sm btn-outline-secondary">Prikaži</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/admin/vijesti/' . (int) $v['id'] . '/uredi')) ?>" class="btn btn-sm btn-outline-primary">Uredi</a>
                        <form method="post" action="<?= e(url('/admin/vijesti/' . (int) $v['id'] . '/obrisi')) ?>" class="d-inline"
                              data-potvrda="Obrisati vijest „<?= e($v['naslov']) ?>”? Radnja se ne može poništiti.">
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
