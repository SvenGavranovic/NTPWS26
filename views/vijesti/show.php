<?php
/** @var array $vijest */
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Početna</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/vijesti')) ?>">Vijesti</a></li>
        <li class="breadcrumb-item">
            <a href="<?= e(url('/vijesti') . '?kategorija=' . (int) $vijest['kategorija_id']) ?>"><?= e($vijest['kategorija']) ?></a>
        </li>
    </ol>
</nav>

<article class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <h1 class="display-6 fw-semibold mb-3"><?= e($vijest['naslov']) ?></h1>

        <p class="text-body-secondary border-bottom pb-3 mb-4">
            <a href="<?= e(url('/vijesti') . '?kategorija=' . (int) $vijest['kategorija_id']) ?>"
               class="badge text-bg-light border text-decoration-none"><?= e($vijest['kategorija']) ?></a>
            · <?= e($vijest['autor']) ?>
            · <time datetime="<?= e($vijest['datum_objave']) ?>"><?= e(datum($vijest['datum_objave'], true)) ?></time>
        </p>

        <?php if (!empty($vijest['slika'])): ?>
            <img src="<?= e(url('uploads/vijesti/' . $vijest['slika'])) ?>" class="img-fluid rounded-3 mb-4" alt="">
        <?php endif; ?>

        <p class="lead"><?= e($vijest['sazetak']) ?></p>

        <div class="vijest-sadrzaj">
            <?php
            // Sadržaj je HTML iz uređivača i ispisuje se BEZ escapinga, inače bi se
            // vidjele oznake <p>. Siguran je jer ga HTMLPurifier čisti PRI SPREMANJU
            // (CMS, faza 4): ostaju samo dopuštene oznake, bez <script> i on*="" atributa.
            echo $vijest['sadrzaj'];
            ?>
        </div>

        <a href="<?= e(url('/vijesti')) ?>" class="btn btn-outline-primary mt-4">&larr; Sve vijesti</a>
    </div>
</article>
