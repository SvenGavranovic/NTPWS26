<?php
/**
 * Radnje za admina i dodijeljenog radnika.
 *
 * @var array $prijava
 * @var bool $jeAdmin
 * @var bool $jeRadnik
 * @var array $radnici
 * @var int $maxMb
 */

use App\Services\StatusPrijave;

$id = (int) $prijava['id'];
$status = $prijava['status'];
?>
<?php if ($jeAdmin || $jeRadnik): ?>
<div class="card mb-4 border-primary-subtle" id="radnje">
    <div class="card-header bg-primary-subtle fw-semibold">
        <?= $jeAdmin ? 'Radnje administratora' : 'Moj zadatak' ?>
    </div>
    <div class="card-body">

        <?php if ($prijava['napomena_radnika'] && in_array($status, ['CEKA_PROVJERU', 'VRACENO', 'ZAVRSENO'], true)): ?>
            <div class="mb-3">
                <div class="small text-body-secondary">Napomena radnika</div>
                <div><?= nl2br(e($prijava['napomena_radnika'])) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($jeAdmin): ?>
            <?php if ($status === 'CEKA_PROVJERU'): ?>
                <p class="small mb-2">Radnik je poslao posao na provjeru. Pregledajte sliku „nakon popravka” i napomenu.</p>
                <form method="post" action="<?= e(url("/admin/prijave/{$id}/odobri")) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success w-100">Odobri – prijava je riješena</button>
                </form>
                <form method="post" action="<?= e(url("/admin/prijave/{$id}/vrati")) ?>" class="mb-3 pb-3 border-bottom">
                    <?= csrf_field() ?>
                    <label for="komentar" class="form-label small">Razlog vraćanja (obavezno)</label>
                    <textarea class="form-control mb-2" id="komentar" name="komentar" rows="2" maxlength="1000" required></textarea>
                    <button type="submit" class="btn btn-outline-danger w-100">Vrati radniku na doradu</button>
                </form>
            <?php endif; ?>

            <?php if (StatusPrijave::canTransition($status, 'DODIJELJENO', 'admin')): ?>
                <form method="post" action="<?= e(url("/admin/prijave/{$id}/dodjela")) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <label for="radnik_id" class="form-label small"><?= $prijava['radnik_id'] ? 'Preraspodjela radniku' : 'Dodjela radniku' ?></label>
                    <div class="input-group">
                        <select class="form-select" id="radnik_id" name="radnik_id" required>
                            <option value="">Odaberite radnika…</option>
                            <?php foreach ($radnici as $r): ?>
                                <option value="<?= (int) $r['id'] ?>"<?= (int) $r['id'] === (int) $prijava['radnik_id'] ? ' selected' : '' ?>>
                                    <?= e($r['ime']) ?> (otvorenih: <?= (int) $r['otvoreno'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><?= $prijava['radnik_id'] ? 'Preraspodijeli' : 'Dodijeli' ?></button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($status !== 'ZAVRSENO'): ?>
                <form method="post" action="<?= e(url("/admin/prijave/{$id}/hitnost")) ?>">
                    <?= csrf_field() ?>
                    <label for="hitnost" class="form-label small">Hitnost</label>
                    <div class="input-group">
                        <select class="form-select" id="hitnost" name="hitnost">
                            <?php foreach (StatusPrijave::HITNOSTI as $kod => [$naziv]): ?>
                                <option value="<?= e($kod) ?>"<?= $prijava['hitnost'] === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary">Spremi</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="small text-body-secondary mb-0">Prijava je završena. Nema daljnjih radnji.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($jeRadnik): ?>
            <?php if (StatusPrijave::canTransition($status, 'U_RADU', 'radnik')): ?>
                <form method="post" action="<?= e(url("/radnik/zadaci/{$id}/zapocni")) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $status === 'VRACENO' ? 'Nastavi rad (dorada)' : 'Započni rad' ?>
                    </button>
                </form>
            <?php elseif ($status === 'U_RADU'): ?>
                <form method="post" action="<?= e(url("/radnik/zadaci/{$id}/zavrsi")) ?>" enctype="multipart/form-data" id="zavrsetak">
                    <?= csrf_field() ?>
                    <p class="small mb-2">Kad završite, pošaljite fotografiju stanja nakon popravka i kratku napomenu. Prijavu zatvara administrator.</p>
                    <div class="mb-2">
                        <label for="slika" class="form-label small">Fotografija nakon popravka</label>
                        <input type="file" class="form-control" id="slika" name="slika" required
                               accept="image/jpeg,image/png,image/webp" data-pregled="slika-pregled">
                        <div class="form-text">JPG, PNG ili WebP, najviše <?= (int) $maxMb ?> MB.</div>
                        <img id="slika-pregled" alt="Pregled fotografije" class="img-fluid rounded mt-2 d-none">
                    </div>
                    <div class="mb-2">
                        <label for="napomena" class="form-label small">Napomena o obavljenom poslu</label>
                        <textarea class="form-control" id="napomena" name="napomena" rows="3" maxlength="2000" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Pošalji na provjeru</button>
                </form>
            <?php elseif ($status === 'CEKA_PROVJERU'): ?>
                <p class="small mb-0">Posao je poslan na provjeru. Administrator će ga odobriti ili vratiti na doradu.</p>
            <?php elseif ($status === 'ZAVRSENO'): ?>
                <p class="small mb-0">Posao je odobren. Prijava je završena.</p>
            <?php endif; ?>
            <a href="<?= e(url('/radnik/zadaci')) ?>" class="btn btn-link px-0 mt-2">&larr; Moji zadaci</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
