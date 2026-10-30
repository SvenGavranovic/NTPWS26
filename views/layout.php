<?php
/**
 * Zajednički okvir svih stranica.
 * @var string $content  HTML pogleda (već pripremljen u View::render)
 * @var string $title
 */

use App\Core\Auth;
use App\Core\Session;

$korisnik = Auth::user();
$flash = Session::pullFlash();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Zagreb Prijave') ?> · Zagreb Prijave</title>
    <!-- integrity: preglednik odbija datoteku ako je na CDN-u izmijenjena -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= e(url('/')) ?>">Zagreb Prijave</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#glavniIzbornik"
                aria-controls="glavniIzbornik" aria-expanded="false" aria-label="Izbornik">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="glavniIzbornik">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/')) ?>">Početna</a></li>
                <?php if (Auth::hasRole('admin')): ?>
                    <!-- Poveznica je skrivena ostalima, ali PRAVU zaštitu radi router (403) -->
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('/admin')) ?>">Administracija</a></li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if ($korisnik): ?>
                    <li class="nav-item">
                        <span class="navbar-text me-3">
                            <?= e($korisnik['ime'] . ' ' . $korisnik['prezime']) ?>
                            <span class="badge text-bg-light"><?= e($korisnik['uloga_naziv']) ?></span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <!-- Odjava je POST s CSRF tokenom: tuđa stranica ne može odjaviti korisnika -->
                        <form method="post" action="<?= e(url('/racun/odjava')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-light btn-sm">Odjava</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('/racun/prijava')) ?>">Prijava</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('/racun/registracija')) ?>">Registracija</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php foreach ($flash as $poruka): ?>
        <div class="alert alert-<?= e($poruka['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($poruka['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zatvori"></button>
        </div>
    <?php endforeach; ?>

    <?= $content /* već escapean u pogledu */ ?>
</main>

<footer class="bg-light border-top py-3 mt-auto">
    <div class="container small text-muted">
        Zagreb Prijave · studentski projekt · kolegij Napredno web projektiranje web servisa
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
