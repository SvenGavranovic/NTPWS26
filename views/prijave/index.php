<?php
/**
 * @var array $prijave
 * @var \App\Core\Paginator $paginator
 * @var string|null $status
 * @var int $ukupno  broj svih vlastitih prijava (bez filtra)
 */

use App\Services\StatusPrijave;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0">Moje prijave</h1>
    <a href="<?= e(url('/prijave/nova')) ?>" class="btn btn-primary">+ Prijavi problem</a>
</div>

<?php if ($ukupno === 0): ?>
    <div class="text-center py-5 border rounded-3">
        <p class="lead mb-3">Još niste poslali nijednu prijavu.</p>
        <a href="<?= e(url('/prijave/nova')) ?>" class="btn btn-primary">Prijavite prvi problem</a>
    </div>
<?php else: ?>
    <form method="get" action="<?= e(url('/prijave/moje')) ?>" class="row g-2 mb-3">
        <div class="col-sm-6 col-md-4">
            <label for="status" class="visually-hidden">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Svi statusi</option>
                <?php foreach (StatusPrijave::STATUSI as $kod => [$naziv]): ?>
                    <option value="<?= e($kod) ?>"<?= $status === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Filtriraj</button>
        </div>
    </form>

    <?php if ($prijave === []): ?>
        <div class="text-center text-body-secondary py-5 border rounded-3">
            Nema prijava s tim statusom. <a href="<?= e(url('/prijave/moje')) ?>">Prikaži sve</a>
        </div>
    <?php else: ?>
        <div class="list-group mb-3">
            <?php foreach ($prijave as $p): ?>
                <a href="<?= e(url('/prijave/' . (int) $p['id'])) ?>" class="list-group-item list-group-item-action d-flex gap-3 align-items-center py-3">
                    <?php if ($p['slika_id']): ?>
                        <img src="<?= e(url('/prijave/' . (int) $p['id'] . '/slike/' . (int) $p['slika_id'])) ?>" alt=""
                             class="rounded prijava-slicica" loading="lazy" width="80" height="60">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <span class="fw-semibold"><?= e($p['naslov']) ?></span>
                            <?= StatusPrijave::badge($p['status']) ?>
                        </div>
                        <div class="small text-body-secondary">
                            <?= e($p['vrsta']) ?> · <?= e($p['cetvrt']) ?>
                            · prijavljeno <?= e(datum($p['kreirano_u'], true)) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/paginacija.php'; ?>
    <?php endif; ?>
<?php endif; ?>
