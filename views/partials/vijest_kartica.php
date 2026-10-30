<?php
/**
 * Kartica jedne vijesti (popis i početna).
 * @var array $vijest
 */
$poveznica = url('/vijesti/' . (int) $vijest['id']);
?>
<article class="card h-100 vijest-kartica">
    <?php if (!empty($vijest['slika'])): ?>
        <img src="<?= e(url('uploads/vijesti/' . $vijest['slika'])) ?>" class="card-img-top" alt="" loading="lazy">
    <?php endif; ?>
    <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-center small mb-2">
            <a href="<?= e(url('/vijesti') . '?kategorija=' . (int) $vijest['kategorija_id']) ?>"
               class="badge text-bg-light text-decoration-none border"><?= e($vijest['kategorija']) ?></a>
            <time datetime="<?= e($vijest['datum_objave']) ?>" class="text-body-secondary"><?= e(datum($vijest['datum_objave'])) ?></time>
        </div>
        <h3 class="h5 card-title">
            <a href="<?= e($poveznica) ?>" class="stretched-link text-body text-decoration-none"><?= e($vijest['naslov']) ?></a>
        </h3>
        <p class="card-text text-body-secondary flex-grow-1"><?= e($vijest['sazetak']) ?></p>
        <p class="card-text small text-body-secondary mb-0"><?= e($vijest['autor']) ?></p>
    </div>
</article>
