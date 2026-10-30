<?php
/**
 * @var array $korisnik
 * @var array $errorsProfil   pogreške obrasca s podacima
 * @var array $errorsLozinka  pogreške obrasca za lozinku
 * @var array $old
 */
$errorsProfil ??= [];
$errorsLozinka ??= [];
$old ??= [];

$polje = static function (array $errors, string $name): array {
    return [
        isset($errors[$name]) ? ' is-invalid' : '',
        isset($errors[$name]) ? '<div class="invalid-feedback">' . e($errors[$name]) . '</div>' : '',
    ];
};
?>
<h1 class="h2 mb-4">Moj profil</h1>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 card-title"><?= e($korisnik['ime'] . ' ' . $korisnik['prezime']) ?></h2>
                <dl class="mb-0">
                    <dt class="small text-body-secondary">E-mail</dt>
                    <dd><?= e($korisnik['email']) ?></dd>
                    <dt class="small text-body-secondary">Uloga</dt>
                    <dd><span class="badge text-bg-primary bg-zagreb"><?= e($korisnik['uloga_naziv']) ?></span></dd>
                    <dt class="small text-body-secondary">Član od</dt>
                    <dd><?= e(datum($korisnik['kreirano_u'])) ?></dd>
                    <dt class="small text-body-secondary">Posljednja prijava</dt>
                    <dd class="mb-0"><?= $korisnik['zadnja_prijava'] ? e(datum($korisnik['zadnja_prijava'], true)) : '—' ?></dd>
                </dl>
            </div>
            <div class="card-footer small text-body-secondary">
                E-mail i ulogu može promijeniti samo administrator.
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 card-title mb-3">Osobni podaci</h2>
                <form method="post" action="<?= e(url('/racun/profil')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php [$cls, $msg] = $polje($errorsProfil, 'ime'); ?>
                    <div class="mb-3">
                        <label for="ime" class="form-label">Ime</label>
                        <input type="text" class="form-control<?= $cls ?>" id="ime" name="ime" maxlength="50" required
                               autocomplete="given-name" value="<?= e($old['ime'] ?? $korisnik['ime']) ?>">
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = $polje($errorsProfil, 'prezime'); ?>
                    <div class="mb-3">
                        <label for="prezime" class="form-label">Prezime</label>
                        <input type="text" class="form-control<?= $cls ?>" id="prezime" name="prezime" maxlength="50" required
                               autocomplete="family-name" value="<?= e($old['prezime'] ?? $korisnik['prezime']) ?>">
                        <?= $msg ?>
                    </div>
                    <button type="submit" class="btn btn-primary">Spremi</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 card-title mb-3">Promjena lozinke</h2>
                <form method="post" action="<?= e(url('/racun/lozinka')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php [$cls, $msg] = $polje($errorsLozinka, 'trenutna_lozinka'); ?>
                    <div class="mb-3">
                        <label for="trenutna_lozinka" class="form-label">Trenutna lozinka</label>
                        <input type="password" class="form-control<?= $cls ?>" id="trenutna_lozinka" name="trenutna_lozinka"
                               required autocomplete="current-password">
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = $polje($errorsLozinka, 'nova_lozinka'); ?>
                    <div class="mb-3">
                        <label for="nova_lozinka" class="form-label">Nova lozinka</label>
                        <input type="password" class="form-control<?= $cls ?>" id="nova_lozinka" name="nova_lozinka"
                               required autocomplete="new-password" aria-describedby="nova-pomoc">
                        <div id="nova-pomoc" class="form-text">Najmanje 8 znakova, barem jedno slovo i jedna znamenka.</div>
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = $polje($errorsLozinka, 'nova_lozinka_potvrda'); ?>
                    <div class="mb-3">
                        <label for="nova_lozinka_potvrda" class="form-label">Ponovite novu lozinku</label>
                        <input type="password" class="form-control<?= $cls ?>" id="nova_lozinka_potvrda" name="nova_lozinka_potvrda"
                               required autocomplete="new-password">
                        <?= $msg ?>
                    </div>
                    <button type="submit" class="btn btn-primary">Promijeni lozinku</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($korisnik['uloga'] === 'korisnik'): ?>
    <div class="card mt-4">
        <div class="card-body">
            <h2 class="h5 card-title">Moje prijave</h2>
            <p class="text-body-secondary mb-0">Pregled vlastitih prijava i njihovih statusa dodaje se u fazi 5.</p>
        </div>
    </div>
<?php endif; ?>
