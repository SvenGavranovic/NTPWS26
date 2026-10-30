<?php
/**
 * @var array $errors  pogreške po poljima
 * @var array $old     prethodno upisane vrijednosti (bez lozinki)
 */
$errors ??= [];
$old ??= [];

/** Bootstrap klasa i poruka ispod polja s pogreškom */
$invalid = static fn (string $polje): string => isset($errors[$polje]) ? ' is-invalid' : '';
$poruka = static fn (string $polje): string => isset($errors[$polje])
    ? '<div class="invalid-feedback" id="' . $polje . '-greska">' . e($errors[$polje]) . '</div>'
    : '';
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h1 class="h3 mb-4">Registracija</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">Provjerite označena polja.</div>
        <?php endif; ?>

        <!-- novalidate: pravila provjerava poslužitelj, pa poruke uvijek dolaze iz istog izvora -->
        <form method="post" action="<?= e(url('/racun/registracija')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="ime" class="form-label">Ime</label>
                    <input type="text" class="form-control<?= $invalid('ime') ?>" id="ime" name="ime"
                           required maxlength="50" autocomplete="given-name"
                           value="<?= e($old['ime'] ?? '') ?>">
                    <?= $poruka('ime') ?>
                </div>
                <div class="col-sm-6">
                    <label for="prezime" class="form-label">Prezime</label>
                    <input type="text" class="form-control<?= $invalid('prezime') ?>" id="prezime" name="prezime"
                           required maxlength="50" autocomplete="family-name"
                           value="<?= e($old['prezime'] ?? '') ?>">
                    <?= $poruka('prezime') ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
                       required maxlength="255" autocomplete="email"
                       value="<?= e($old['email'] ?? '') ?>">
                <?= $poruka('email') ?>
            </div>

            <div class="mb-3">
                <label for="lozinka" class="form-label">Lozinka</label>
                <input type="password" class="form-control<?= $invalid('lozinka') ?>" id="lozinka" name="lozinka"
                       required autocomplete="new-password" aria-describedby="lozinka-pomoc">
                <div id="lozinka-pomoc" class="form-text">Najmanje 8 znakova, barem jedno slovo i jedna znamenka.</div>
                <?= $poruka('lozinka') ?>
            </div>

            <div class="mb-4">
                <label for="lozinka_potvrda" class="form-label">Ponovite lozinku</label>
                <input type="password" class="form-control<?= $invalid('lozinka_potvrda') ?>" id="lozinka_potvrda"
                       name="lozinka_potvrda" required autocomplete="new-password">
                <?= $poruka('lozinka_potvrda') ?>
            </div>

            <button type="submit" class="btn btn-primary w-100">Registriraj se</button>
        </form>

        <p class="mt-3 text-center">
            Već imate račun? <a href="<?= e(url('/racun/prijava')) ?>">Prijavite se</a>
        </p>
    </div>
</div>
