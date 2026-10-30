<?php
/**
 * @var array $prijava
 * @var array $slike     slike koje ovaj korisnik smije vidjeti
 * @var array $povijest  promjene statusa, kronološki
 * @var bool $sluzba     admin ili dodijeljeni radnik (vidi interne komentare i imena)
 * @var int $mojId
 */

use App\Services\StatusPrijave;

[, , $objasnjenje] = StatusPrijave::STATUSI[$prijava['status']];
$vlastita = (int) $prijava['korisnik_id'] === $mojId;
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <?php if ($vlastita): ?>
            <li class="breadcrumb-item"><a href="<?= e(url('/prijave/moje')) ?>">Moje prijave</a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page">Prijava #<?= (int) $prijava['id'] ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <h1 class="h2 mb-0"><?= e($prijava['naslov']) ?></h1>
    <span class="fs-5"><?= StatusPrijave::badge($prijava['status']) ?></span>
</div>
<p class="text-body-secondary"><?= e($objasnjenje) ?></p>

<div class="row g-4">
    <div class="col-lg-7">
        <?php foreach ($slike as $s): ?>
            <figure class="mb-3">
                <img src="<?= e(url('/prijave/' . (int) $prijava['id'] . '/slike/' . (int) $s['id'])) ?>"
                     alt="Fotografija <?= $s['tip'] === 'prije' ? 'prijavljenog problema' : 'nakon popravka' ?>"
                     class="img-fluid rounded-3 border">
                <figcaption class="small text-body-secondary mt-1">
                    <?= $s['tip'] === 'prije' ? 'Prije' : 'Nakon popravka' ?> · <?= e(datum($s['kreirano_u'], true)) ?>
                </figcaption>
            </figure>
        <?php endforeach; ?>

        <h2 class="h5 mt-2">Opis</h2>
        <!-- Opis je običan tekst: najprije escaping, zatim nl2br za prijelome redaka -->
        <p><?= nl2br(e($prijava['opis'])) ?></p>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-body-secondary fw-normal">Vrsta</dt>
                    <dd class="col-7"><?= e($prijava['vrsta']) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal">Četvrt</dt>
                    <dd class="col-7"><?= e($prijava['cetvrt']) ?></dd>
                    <?php if ($prijava['adresa']): ?>
                        <dt class="col-5 text-body-secondary fw-normal">Lokacija</dt>
                        <dd class="col-7"><?= e($prijava['adresa']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-body-secondary fw-normal">Prijavljeno</dt>
                    <dd class="col-7"><?= e(datum($prijava['kreirano_u'], true)) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal">Hitnost</dt>
                    <dd class="col-7"><?= StatusPrijave::hitnostBadge($prijava['hitnost']) ?></dd>
                    <?php if ($sluzba): ?>
                        <dt class="col-5 text-body-secondary fw-normal">Prijavitelj</dt>
                        <dd class="col-7 mb-0"><?= e($prijava['prijavitelj']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <h2 class="h5">Tijek prijave</h2>
        <ol class="list-unstyled tijek">
            <?php foreach ($povijest as $h): ?>
                <li class="tijek-stavka">
                    <div class="d-flex justify-content-between gap-2">
                        <?= StatusPrijave::badge($h['u_status']) ?>
                        <time class="small text-body-secondary" datetime="<?= e($h['kreirano_u']) ?>"><?= e(datum($h['kreirano_u'], true)) ?></time>
                    </div>
                    <div class="small mt-1">
                        <?php if ((int) $h['korisnik_id'] === $mojId): ?>
                            Vi
                        <?php elseif ($sluzba): ?>
                            <?= e($h['osoba']) ?> <span class="text-body-secondary">(<?= e($h['uloga']) ?>)</span>
                        <?php else: ?>
                            <!-- Građanin ne vidi imena zaposlenika, samo njihovu ulogu -->
                            <?= e($h['uloga']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($sluzba && $h['komentar']): ?>
                        <div class="small text-body-secondary fst-italic mt-1"><?= nl2br(e($h['komentar'])) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!$sluzba): ?>
            <p class="small text-body-secondary">Interne napomene službe i radnika nisu prikazane.</p>
        <?php endif; ?>
    </div>
</div>
