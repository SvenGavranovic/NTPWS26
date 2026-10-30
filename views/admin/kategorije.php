<?php
/**
 * @var array $kategorije  sve kategorije s brojem vijesti
 * @var array $kategorija  kategorija koja se uređuje ([] = nova)
 * @var array $errors
 */
$errors ??= [];
$uredjivanje = isset($kategorija['id']);
?>
<h1 class="h2 mb-3">Kategorije vijesti</h1>
<?php require __DIR__ . '/_nav.php'; ?>

<div class="row g-4">
    <div class="col-lg-4 order-lg-2">
        <div class="card">
            <div class="card-body">
                <h2 class="h5 card-title mb-3"><?= $uredjivanje ? 'Uređivanje kategorije' : 'Nova kategorija' ?></h2>
                <form method="post" novalidate
                      action="<?= e(url($uredjivanje ? '/admin/kategorije/' . (int) $kategorija['id'] : '/admin/kategorije')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="naziv" class="form-label">Naziv</label>
                        <input type="text" class="form-control<?= isset($errors['naziv']) ? ' is-invalid' : '' ?>" id="naziv"
                               name="naziv" maxlength="80" required value="<?= e($kategorija['naziv'] ?? '') ?>">
                        <?php if (isset($errors['naziv'])): ?><div class="invalid-feedback"><?= e($errors['naziv']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="opis" class="form-label">Opis <span class="text-body-secondary">(neobavezno)</span></label>
                        <input type="text" class="form-control<?= isset($errors['opis']) ? ' is-invalid' : '' ?>" id="opis"
                               name="opis" maxlength="255" value="<?= e($kategorija['opis'] ?? '') ?>">
                        <?php if (isset($errors['opis'])): ?><div class="invalid-feedback"><?= e($errors['opis']) ?></div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $uredjivanje ? 'Spremi' : 'Dodaj' ?></button>
                    <?php if ($uredjivanje): ?>
                        <a href="<?= e(url('/admin/kategorije')) ?>" class="btn btn-outline-secondary">Odustani</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th scope="col">Naziv</th>
                    <th scope="col">Opis</th>
                    <th scope="col" class="text-center">Vijesti</th>
                    <th scope="col" class="text-end">Radnje</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($kategorije as $k): $broj = (int) $k['broj_vijesti']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($k['naziv']) ?></td>
                        <td class="small text-body-secondary"><?= e($k['opis']) ?></td>
                        <td class="text-center"><?= $broj ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('/admin/kategorije') . '?uredi=' . (int) $k['id']) ?>" class="btn btn-sm btn-outline-primary">Uredi</a>
                            <?php if ($broj === 0): ?>
                                <form method="post" action="<?= e(url('/admin/kategorije/' . (int) $k['id'] . '/obrisi')) ?>" class="d-inline"
                                      data-potvrda="Obrisati kategoriju „<?= e($k['naziv']) ?>”?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Obriši</button>
                                </form>
                            <?php else: ?>
                                <!-- Gumb je onemogućen radi preglednosti; pravilo ionako provjerava poslužitelj -->
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                        title="Kategorija s vijestima ne može se obrisati">Obriši</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-body-secondary">Kategorija koja ima vijesti (i nacrte) ne može se obrisati.</p>
    </div>
</div>
