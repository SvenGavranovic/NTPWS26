<?php
/**
 * @var string|null $greska
 * @var array $old
 */
$old ??= [];
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <h1 class="h3 mb-4">Prijava</h1>

        <?php if (!empty($greska)): ?>
            <div class="alert alert-danger" role="alert"><?= e($greska) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/racun/prijava')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required
                       autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="lozinka" class="form-label">Lozinka</label>
                <input type="password" class="form-control" id="lozinka" name="lozinka" required
                       autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary w-100">Prijavi se</button>
        </form>

        <p class="mt-3 text-center">
            Nemate račun? <a href="<?= e(url('/racun/registracija')) ?>">Registrirajte se</a>
        </p>
    </div>
</div>
