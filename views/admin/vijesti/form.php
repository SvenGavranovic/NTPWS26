<?php
/**
 * Obrazac za novu vijest i uređivanje postojeće.
 * @var array $vijest      podaci vijesti (iz baze ili iz poslanog obrasca)
 * @var array $kategorije  id => naziv
 * @var array $errors
 * @var string $title
 */
$errors ??= [];
$uredjivanje = isset($vijest['id']);
$akcija = $uredjivanje ? '/admin/vijesti/' . (int) $vijest['id'] : '/admin/vijesti';

$invalid = static fn (string $polje): string => isset($errors[$polje]) ? ' is-invalid' : '';
$poruka = static fn (string $polje): string => isset($errors[$polje])
    ? '<div class="invalid-feedback d-block">' . e($errors[$polje]) . '</div>'
    : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><?= e($title) ?></h1>
    <?php if ($uredjivanje && ($vijest['status'] ?? '') === 'objavljeno'): ?>
        <a href="<?= e(url('/vijesti/' . (int) $vijest['id'])) ?>" class="btn btn-outline-secondary">Prikaži na stranici</a>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../_nav.php'; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">Vijest nije spremljena. Provjerite označena polja.</div>
<?php endif; ?>

<form method="post" action="<?= e(url($akcija)) ?>" enctype="multipart/form-data" novalidate id="obrazac-vijesti">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="mb-3">
                <label for="naslov" class="form-label">Naslov</label>
                <input type="text" class="form-control form-control-lg<?= $invalid('naslov') ?>" id="naslov" name="naslov"
                       maxlength="200" required value="<?= e($vijest['naslov'] ?? '') ?>">
                <?= $poruka('naslov') ?>
            </div>

            <div class="mb-3">
                <label for="sazetak" class="form-label">Sažetak</label>
                <textarea class="form-control<?= $invalid('sazetak') ?>" id="sazetak" name="sazetak" rows="2"
                          maxlength="500" required aria-describedby="sazetak-pomoc"><?= e($vijest['sazetak'] ?? '') ?></textarea>
                <div id="sazetak-pomoc" class="form-text">Kratki opis za popis vijesti (10–500 znakova).</div>
                <?= $poruka('sazetak') ?>
            </div>

            <div class="mb-3">
                <label for="sadrzaj" class="form-label">Sadržaj</label>
                <div id="uredjivac" class="bg-body d-none<?= $invalid('sadrzaj') ?>"></div>
                <textarea class="form-control font-monospace<?= $invalid('sadrzaj') ?>" id="sadrzaj" name="sadrzaj"
                          rows="12"><?= e($vijest['sadrzaj'] ?? '') ?></textarea>
                <?= $poruka('sadrzaj') ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select<?= $invalid('status') ?>" id="status" name="status">
                            <option value="nacrt"<?= ($vijest['status'] ?? '') === 'nacrt' ? ' selected' : '' ?>>Nacrt (nije javno)</option>
                            <option value="objavljeno"<?= ($vijest['status'] ?? '') === 'objavljeno' ? ' selected' : '' ?>>Objavljeno</option>
                        </select>
                        <?= $poruka('status') ?>
                        <?php if (!empty($vijest['datum_objave'])): ?>
                            <div class="form-text">Prvi put objavljeno <?= e(datum($vijest['datum_objave'], true)) ?>.
                                Vraćanjem u nacrt vijest se povlači s javnih stranica.</div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="kategorija_id" class="form-label">Kategorija</label>
                        <select class="form-select<?= $invalid('kategorija_id') ?>" id="kategorija_id" name="kategorija_id">
                            <option value="">Odaberite…</option>
                            <?php foreach ($kategorije as $id => $naziv): ?>
                                <option value="<?= (int) $id ?>"<?= (int) ($vijest['kategorija_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= e($naziv) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $poruka('kategorija_id') ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <label for="slika" class="form-label">Naslovna slika</label>
                    <?php if (!empty($vijest['slika'])): ?>
                        <img src="<?= e(url('uploads/vijesti/' . $vijest['slika'])) ?>" alt="Trenutna slika"
                             class="img-fluid rounded mb-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="1" id="ukloni_sliku" name="ukloni_sliku">
                            <label class="form-check-label" for="ukloni_sliku">Ukloni sliku</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control<?= $invalid('slika') ?>" id="slika" name="slika"
                           accept="image/jpeg,image/png,image/webp" aria-describedby="slika-pomoc">
                    <div id="slika-pomoc" class="form-text">JPG, PNG ili WebP, najviše 2 MB.
                        <?= !empty($vijest['slika']) ? 'Nova slika zamjenjuje postojeću.' : '' ?></div>
                    <?= $poruka('slika') ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Spremi</button>
                <a href="<?= e(url('/admin/vijesti')) ?>" class="btn btn-outline-secondary">Natrag na popis</a>
            </div>
        </div>
    </div>
</form>

<?php if ($uredjivanje): ?>
    <form method="post" action="<?= e(url('/admin/vijesti/' . (int) $vijest['id'] . '/obrisi')) ?>" class="mt-4 pt-3 border-top"
          data-potvrda="Obrisati ovu vijest? Radnja se ne može poništiti.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">Obriši vijest</button>
    </form>
<?php endif; ?>
