<?php
/**
 * @var array $rad
 * @var array $errors
 * @var array $cetvrti  id => naziv
 * @var string $title
 */

use App\Services\Geo;
use App\Services\RadService;

$errors ??= [];
$uredjivanje = isset($rad['id']);
$invalid = static fn (string $polje): string => isset($errors[$polje]) ? ' is-invalid' : '';
$poruka = static fn (string $polje): string => isset($errors[$polje])
    ? '<div class="invalid-feedback d-block">' . e($errors[$polje]) . '</div>'
    : '';
?>
<h1 class="h2 mb-3"><?= e($title) ?></h1>
<?php require __DIR__ . '/../_nav.php'; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">Radovi nisu spremljeni. Provjerite označena polja.</div>
<?php endif; ?>

<form method="post" action="<?= e(url($uredjivanje ? '/admin/radovi/' . (int) $rad['id'] : '/admin/radovi')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="mb-3">
                <label for="naslov" class="form-label">Naslov</label>
                <input type="text" class="form-control<?= $invalid('naslov') ?>" id="naslov" name="naslov" maxlength="150" required
                       value="<?= e($rad['naslov'] ?? '') ?>">
                <?= $poruka('naslov') ?>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="vrsta" class="form-label">Vrsta radova</label>
                    <select class="form-select<?= $invalid('vrsta') ?>" id="vrsta" name="vrsta">
                        <option value="">Odaberite…</option>
                        <?php foreach (RadService::VRSTE as $kod => $naziv): ?>
                            <option value="<?= e($kod) ?>"<?= ($rad['vrsta'] ?? '') === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $poruka('vrsta') ?>
                </div>
                <div class="col-sm-6">
                    <label for="utjecaj_na_promet" class="form-label">Utjecaj na promet</label>
                    <select class="form-select<?= $invalid('utjecaj_na_promet') ?>" id="utjecaj_na_promet" name="utjecaj_na_promet">
                        <?php foreach (RadService::UTJECAJI as $kod => $naziv): ?>
                            <option value="<?= e($kod) ?>"<?= ($rad['utjecaj_na_promet'] ?? '') === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $poruka('utjecaj_na_promet') ?>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="pocetak" class="form-label">Početak</label>
                    <input type="datetime-local" class="form-control<?= $invalid('pocetak') ?>" id="pocetak" name="pocetak"
                           value="<?= e($rad['pocetak'] ?? '') ?>">
                    <?= $poruka('pocetak') ?>
                </div>
                <div class="col-sm-6">
                    <label for="kraj" class="form-label">Kraj <span class="text-body-secondary">(ako je poznat)</span></label>
                    <input type="datetime-local" class="form-control<?= $invalid('kraj') ?>" id="kraj" name="kraj"
                           value="<?= e($rad['kraj'] ?? '') ?>">
                    <?= $poruka('kraj') ?>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="cetvrt_id" class="form-label">Gradska četvrt</label>
                    <select class="form-select<?= $invalid('cetvrt_id') ?>" id="cetvrt_id" name="cetvrt_id">
                        <option value="">—</option>
                        <?php foreach ($cetvrti as $id => $naziv): ?>
                            <option value="<?= (int) $id ?>"<?= (string) ($rad['cetvrt_id'] ?? '') === (string) $id ? ' selected' : '' ?>><?= e($naziv) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $poruka('cetvrt_id') ?>
                </div>
                <div class="col-sm-6">
                    <label for="adresa" class="form-label">Adresa</label>
                    <input type="text" class="form-control<?= $invalid('adresa') ?>" id="adresa" name="adresa" maxlength="255"
                           value="<?= e($rad['adresa'] ?? '') ?>">
                    <?= $poruka('adresa') ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="opis" class="form-label">Opis</label>
                <textarea class="form-control<?= $invalid('opis') ?>" id="opis" name="opis" rows="4" maxlength="2000"><?= e($rad['opis'] ?? '') ?></textarea>
                <?= $poruka('opis') ?>
            </div>
        </div>

        <div class="col-lg-6">
            <label class="form-label">Lokacija <span class="text-body-secondary">(kliknite na kartu)</span></label>
            <div class="odabir-lokacije rounded-3 border<?= $invalid('lokacija') ?>" data-odabir-lokacije
                 data-lat="lat" data-lng="lng" data-granice="<?= e(json_encode(Geo::bounds())) ?>"></div>
            <div class="form-text" data-lokacija-poruka>Lokacija nije odabrana.</div>
            <?= $poruka('lokacija') ?>
            <div class="row g-2 mt-1">
                <div class="col"><input type="text" class="form-control form-control-sm" id="lat" name="lat" placeholder="Geografska širina"
                                        aria-label="Geografska širina" value="<?= e((string) ($rad['lat'] ?? '')) ?>"></div>
                <div class="col"><input type="text" class="form-control form-control-sm" id="lng" name="lng" placeholder="Geografska dužina"
                                        aria-label="Geografska dužina" value="<?= e((string) ($rad['lng'] ?? '')) ?>"></div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Spremi</button>
        <a href="<?= e(url('/admin/radovi')) ?>" class="btn btn-outline-secondary">Natrag</a>
    </div>
</form>
