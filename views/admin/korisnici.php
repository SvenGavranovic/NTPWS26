<?php
/**
 * @var array $korisnici
 * @var array $uloge   kod => naziv
 * @var array $filtri  ['q' => string, 'uloga' => ?string, 'aktivan' => ?int]
 * @var \App\Core\Paginator $paginator
 * @var int $mojId     prijavljeni administrator (vlastiti račun se ne može mijenjati)
 */
$povratak = $paginator->url($paginator->page);
// url() dodaje osnovnu putanju aplikacije; kontroler očekuje putanju bez nje
$povratak = substr($povratak, strlen(base_path_url()));
?>
<h1 class="h2 mb-3">Korisnici</h1>
<?php require __DIR__ . '/_nav.php'; ?>

<form method="get" action="<?= e(url('/admin/korisnici')) ?>" class="row g-2 mb-3" role="search">
    <div class="col-md-5">
        <label for="q" class="visually-hidden">Ime ili e-mail</label>
        <input type="search" class="form-control" id="q" name="q" maxlength="100" placeholder="Ime, prezime ili e-mail"
               value="<?= e($filtri['q']) ?>">
    </div>
    <div class="col-sm-5 col-md-3">
        <label for="uloga" class="visually-hidden">Uloga</label>
        <select class="form-select" id="uloga" name="uloga">
            <option value="">Sve uloge</option>
            <?php foreach ($uloge as $kod => $naziv): ?>
                <option value="<?= e($kod) ?>"<?= $filtri['uloga'] === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-4 col-md-2">
        <label for="aktivan" class="visually-hidden">Status računa</label>
        <select class="form-select" id="aktivan" name="aktivan">
            <option value="">Svi računi</option>
            <option value="1"<?= $filtri['aktivan'] === 1 ? ' selected' : '' ?>>Aktivni</option>
            <option value="0"<?= $filtri['aktivan'] === 0 ? ' selected' : '' ?>>Deaktivirani</option>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary">Filtriraj</button>
    </div>
</form>

<p class="small text-body-secondary">
    <?= $paginator->total ?> korisnika. Deaktivacija odmah odjavljuje korisnika i na svim uređajima.
</p>

<?php if ($korisnici === []): ?>
    <div class="text-center text-body-secondary py-5 border rounded-3">Nema korisnika koji odgovaraju filtru.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th scope="col">Korisnik</th>
                <th scope="col">Zadnja prijava</th>
                <th scope="col" style="min-width: 22rem">Uloga i status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($korisnici as $k): $vlastiti = (int) $k['id'] === $mojId; ?>
                <tr class="<?= (int) $k['aktivan'] === 1 ? '' : 'table-secondary' ?>">
                    <td>
                        <div class="fw-semibold"><?= e($k['ime'] . ' ' . $k['prezime']) ?></div>
                        <div class="small text-body-secondary"><?= e($k['email']) ?></div>
                    </td>
                    <td class="small text-nowrap"><?= $k['zadnja_prijava'] ? e(datum($k['zadnja_prijava'], true)) : '—' ?></td>
                    <td>
                        <?php if ($vlastiti): ?>
                            <span class="badge text-bg-primary bg-zagreb"><?= e($k['uloga_naziv']) ?></span>
                            <span class="small text-body-secondary ms-1">vlastiti račun</span>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/admin/korisnici/' . (int) $k['id'])) ?>"
                                  class="d-flex flex-wrap align-items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="povratak" value="<?= e($povratak) ?>">
                                <label class="visually-hidden" for="uloga-<?= (int) $k['id'] ?>">Uloga</label>
                                <select class="form-select form-select-sm w-auto" id="uloga-<?= (int) $k['id'] ?>" name="uloga">
                                    <?php foreach ($uloge as $kod => $naziv): ?>
                                        <option value="<?= e($kod) ?>"<?= $k['uloga'] === $kod ? ' selected' : '' ?>><?= e($naziv) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" value="1" name="aktivan"
                                           id="aktivan-<?= (int) $k['id'] ?>"<?= (int) $k['aktivan'] === 1 ? ' checked' : '' ?>>
                                    <label class="form-check-label small" for="aktivan-<?= (int) $k['id'] ?>">aktivan</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Spremi</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/paginacija.php'; ?>
<?php endif; ?>
