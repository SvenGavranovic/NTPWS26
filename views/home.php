<?php
/**
 * @var array $vijesti     najnovije objavljene vijesti
 * @var array $statistika  ['ukupno' => int, 'u_obradi' => int, 'rijeseno' => int]
 */

use App\Core\Auth;

$koraci = [
    ['Prijavite', 'Fotografirajte problem, odaberite gradsku četvrt i kratko ga opišite.'],
    ['Dodjela', 'Gradska služba određuje hitnost i dodjeljuje prijavu radniku.'],
    ['Rješavanje', 'Radnik rješava problem i šalje fotografiju stanja nakon popravka.'],
    ['Provjera', 'Služba provjerava rad i zatvara prijavu. Status pratite na profilu.'],
];
?>
<section class="hero">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <p class="text-uppercase small fw-semibold mb-2 opacity-75">Komunalni problemi · Zagreb</p>
                <h1 class="display-5 fw-bold mb-3">Uočili ste problem u svom kvartu?</h1>
                <p class="lead mb-4 col-xl-10">
                    Rupa na cesti, neispravna rasvjeta ili divlje odlagalište: pošaljite prijavu s fotografijom
                    i pratite kako se problem rješava. Na jednom mjestu su i vijesti o radovima i prometu.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (Auth::check()): ?>
                        <a href="<?= e(url('/racun/profil')) ?>" class="btn btn-light btn-lg text-zagreb fw-semibold">Moj profil</a>
                    <?php else: ?>
                        <a href="<?= e(url('/racun/registracija')) ?>" class="btn btn-light btn-lg text-zagreb fw-semibold">Registrirajte se i prijavite problem</a>
                    <?php endif; ?>
                    <a href="<?= e(url('/vijesti')) ?>" class="btn btn-outline-light btn-lg">Vijesti</a>
                </div>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <img src="<?= e(asset('img/grb-zagreb.svg')) ?>" alt="Grb Grada Zagreba" class="hero-grb">
            </div>
        </div>
    </div>
</section>

<section class="border-bottom bg-body">
    <div class="container py-4">
        <div class="row text-center g-3">
            <div class="col-4">
                <div class="stat-broj"><?= (int) $statistika['ukupno'] ?></div>
                <div class="small text-body-secondary mt-1">zaprimljenih prijava</div>
            </div>
            <div class="col-4">
                <div class="stat-broj"><?= (int) $statistika['u_obradi'] ?></div>
                <div class="small text-body-secondary mt-1">u obradi</div>
            </div>
            <div class="col-4">
                <div class="stat-broj"><?= (int) $statistika['rijeseno'] ?></div>
                <div class="small text-body-secondary mt-1">riješenih</div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5">
    <h2 class="h3 mb-4">Kako funkcionira</h2>
    <div class="row g-4">
        <?php foreach ($koraci as $i => [$naslov, $opis]): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="h-100 p-3 border rounded-3 bg-body">
                    <span class="korak-broj mb-3"><?= $i + 1 ?></span>
                    <h3 class="h5"><?= e($naslov) ?></h3>
                    <p class="mb-0 text-body-secondary"><?= e($opis) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="bg-body-tertiary border-top">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-baseline mb-4">
            <h2 class="h3 mb-0">Najnovije vijesti</h2>
            <a href="<?= e(url('/vijesti')) ?>">Sve vijesti &rarr;</a>
        </div>

        <?php if ($vijesti === []): ?>
            <p class="text-body-secondary">Trenutno nema objavljenih vijesti.</p>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($vijesti as $vijest): ?>
                    <div class="col-md-4">
                        <?php require __DIR__ . '/partials/vijest_kartica.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
