<?php
/**
 * @var array $old      prethodno upisane vrijednosti
 * @var array $errors   pogreške po poljima ('opce' = pogreška cijelog obrasca)
 * @var array $cetvrti  id => naziv
 * @var array $vrste    id => naziv
 * @var int $maxMb
 */
$invalid = static fn (string $polje): string => isset($errors[$polje]) ? ' is-invalid' : '';
$poruka = static fn (string $polje): string => isset($errors[$polje])
    ? '<div class="invalid-feedback">' . e($errors[$polje]) . '</div>'
    : '';
?>
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <h1 class="h2 mb-2">Prijava problema</h1>
        <p class="text-body-secondary mb-4">
            Fotografirajte problem, odaberite gradsku četvrt i kratko ga opišite.
            Datum i vrijeme prijave bilježe se automatski.
        </p>

        <?php if (isset($errors['opce'])): ?>
            <div class="alert alert-danger" role="alert"><?= e($errors['opce']) ?></div>
        <?php elseif ($errors): ?>
            <div class="alert alert-danger" role="alert">
                Prijava nije poslana. Provjerite označena polja<?= isset($errors['slika']) ? '' : ' i ponovno odaberite fotografiju' ?>.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/prijave')) ?>" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="slika" class="form-label">Fotografija problema</label>
                <input type="file" class="form-control<?= $invalid('slika') ?>" id="slika" name="slika" required
                       accept="image/jpeg,image/png,image/webp" aria-describedby="slika-pomoc" data-pregled="slika-pregled">
                <div id="slika-pomoc" class="form-text">JPG, PNG ili WebP, najviše <?= (int) $maxMb ?> MB.</div>
                <?= $poruka('slika') ?>
                <img id="slika-pregled" alt="Pregled odabrane fotografije" class="img-fluid rounded mt-2 d-none" style="max-height: 16rem">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="cetvrt_id" class="form-label">Gradska četvrt</label>
                    <select class="form-select<?= $invalid('cetvrt_id') ?>" id="cetvrt_id" name="cetvrt_id" required>
                        <option value="">Odaberite…</option>
                        <?php foreach ($cetvrti as $id => $naziv): ?>
                            <option value="<?= (int) $id ?>"<?= (string) $id === ($old['cetvrt_id'] ?? '') ? ' selected' : '' ?>><?= e($naziv) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $poruka('cetvrt_id') ?>
                </div>
                <div class="col-sm-6">
                    <label for="vrsta_id" class="form-label">Vrsta problema</label>
                    <select class="form-select<?= $invalid('vrsta_id') ?>" id="vrsta_id" name="vrsta_id" required>
                        <option value="">Odaberite…</option>
                        <?php foreach ($vrste as $id => $naziv): ?>
                            <option value="<?= (int) $id ?>"<?= (string) $id === ($old['vrsta_id'] ?? '') ? ' selected' : '' ?>><?= e($naziv) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $poruka('vrsta_id') ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="naslov" class="form-label">Kratki naslov</label>
                <input type="text" class="form-control<?= $invalid('naslov') ?>" id="naslov" name="naslov" required
                       maxlength="150" placeholder="npr. Rupa na kolniku" value="<?= e($old['naslov'] ?? '') ?>">
                <?= $poruka('naslov') ?>
            </div>

            <div class="mb-3">
                <label for="opis" class="form-label">Opis</label>
                <textarea class="form-control<?= $invalid('opis') ?>" id="opis" name="opis" rows="5" required maxlength="2000"
                          aria-describedby="opis-pomoc"><?= e($old['opis'] ?? '') ?></textarea>
                <div id="opis-pomoc" class="form-text">Što je problem i gdje se točno nalazi (10–2000 znakova).</div>
                <?= $poruka('opis') ?>
            </div>

            <div class="mb-4">
                <label for="adresa" class="form-label">Adresa ili opis lokacije <span class="text-body-secondary">(neobavezno)</span></label>
                <input type="text" class="form-control<?= $invalid('adresa') ?>" id="adresa" name="adresa" maxlength="255"
                       autocomplete="street-address" value="<?= e($old['adresa'] ?? '') ?>">
                <?= $poruka('adresa') ?>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">Pošalji prijavu</button>
            <a href="<?= e(url('/prijave/moje')) ?>" class="btn btn-link">Moje prijave</a>
        </form>
    </div>
</div>
