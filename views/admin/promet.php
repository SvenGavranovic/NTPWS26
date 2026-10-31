<?php
/**
 * @var array|null $promet       odgovor PrometService (bez skrivanja)
 * @var string|null $greska
 * @var array $incidenti         poredani od najstarijeg
 * @var array $skriveniHash      sha256(incident_id) => indeks
 * @var array $skriveni
 */
?>
<h1 class="h2 mb-3">Promet (TomTom)</h1>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($promet === null): ?>
    <div class="alert alert-danger">Podaci o prometu nisu dostupni: <?= e($greska) ?></div>
<?php else: ?>
    <div class="alert <?= $promet['testni'] || $promet['zastarjelo'] ? 'alert-warning' : 'alert-light border' ?> small">
        <?php if ($promet['testni']): ?>
            <?= e($promet['upozorenje']) ?>
        <?php else: ?>
            Izvor: <strong><?= e($promet['izvor']) ?></strong> ·
            zadnji uspješan dohvat: <?= e(datum($promet['zadnji_uspjesni_dohvat'], true)) ?>
            <?= $promet['upozorenje'] ? ' · ' . e($promet['upozorenje']) : '' ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<p class="small text-body-secondary">
    Podaci dolaze iz TomToma i aplikacija ih ne može ispraviti. Incident za koji pouzdano znate da ne odgovara stvarnosti
    (npr. cesta je ponovno otvorena) možete sakriti: tada se ne prikazuje na karti ni u API-ju. Skrivanje se bilježi s razlogom i može se poništiti.
</p>

<h2 class="h5 mt-4">Trenutni incidenti (<?= count($incidenti) ?>)</h2>
<?php if ($incidenti === []): ?>
    <p class="text-body-secondary">Nema incidenata.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
            <tr>
                <th scope="col">Vrsta</th>
                <th scope="col">Dionica</th>
                <th scope="col">Od – do</th>
                <th scope="col">Zadnja potvrda</th>
                <th scope="col" style="min-width: 18rem">Radnja</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($incidenti as $i): $skriven = $i['id'] !== null && isset($skriveniHash[\App\Models\SkriveniIncident::hash($i['id'])]); ?>
                <tr class="<?= $skriven ? 'table-secondary' : '' ?>">
                    <td>
                        <?= e($i['kategorija_naziv']) ?>
                        <div class="small text-body-secondary"><?= e($i['intenzitet_naziv']) ?></div>
                    </td>
                    <td class="small"><?= e(trim(($i['od'] ?? '') . ' → ' . ($i['do'] ?? ''), ' →')) ?></td>
                    <td class="small text-nowrap">
                        <?= $i['pocetak'] ? e(datum($i['pocetak'])) : '?' ?> – <?= $i['kraj'] ? e(datum($i['kraj'])) : 'nepoznato' ?>
                        <?php if ($i['dugotrajno']): ?><span class="badge text-bg-warning ms-1">dugotrajno</span><?php endif; ?>
                    </td>
                    <td class="small text-nowrap"><?= $i['zadnja_potvrda'] ? e(datum($i['zadnja_potvrda'], true)) : '—' ?></td>
                    <td>
                        <?php if ($skriven): ?>
                            <span class="badge text-bg-secondary">skriveno</span>
                        <?php elseif ($i['id'] !== null): ?>
                            <form method="post" action="<?= e(url('/admin/promet/sakrij')) ?>" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="incident_id" value="<?= e($i['id']) ?>">
                                <label class="visually-hidden" for="razlog-<?= e(md5($i['id'])) ?>">Razlog</label>
                                <input type="text" class="form-control form-control-sm" id="razlog-<?= e(md5($i['id'])) ?>" name="razlog"
                                       maxlength="255" placeholder="Razlog (npr. cesta otvorena)" required>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Sakrij</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2 class="h5 mt-4">Skriveni incidenti (<?= count($skriveni) ?>)</h2>
<?php if ($skriveni === []): ?>
    <p class="text-body-secondary">Nijedan incident nije skriven.</p>
<?php else: ?>
    <ul class="list-group">
        <?php foreach ($skriveni as $s): ?>
            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div><?= e($s['opis']) ?></div>
                    <div class="small text-body-secondary">
                        Razlog: <?= e($s['razlog']) ?> · <?= e($s['sakrio']) ?>, <?= e(datum($s['kreirano_u'], true)) ?>
                    </div>
                </div>
                <form method="post" action="<?= e(url('/admin/promet/skriveni/' . (int) $s['id'] . '/vrati')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Vrati na kartu</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
