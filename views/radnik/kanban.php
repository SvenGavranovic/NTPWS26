<?php
/**
 * Kanban ploča radnika.
 * @var array<string, array> $stupci  status => zadaci (već poredani po hitnosti)
 * @var array<string, string> $nazivi status => naslov stupca
 */

use App\Services\StatusPrijave;

/*
 * data-prima: iz kojih stupaca se kartica smije povući u ovaj stupac.
 * To je SAMO pomoć u sučelju (iste prijelaze dopušta StatusPrijave::PRIJELAZI).
 * Stvarnu provjeru uvijek radi poslužitelj.
 */
$prima = [
    'U_RADU'        => 'DODIJELJENO,VRACENO',
    'CEKA_PROVJERU' => 'U_RADU',
];
$ukupno = array_sum(array_map('count', $stupci));
?>
<div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
    <h1 class="h2 mb-0">Moji zadaci</h1>
    <span class="text-body-secondary small">Kartice su poredane po hitnosti. Povucite karticu u „U radu” za početak rada.</span>
</div>

<div id="kanban-poruka" class="alert d-none" role="status" aria-live="polite"></div>

<?php if ($ukupno === 0): ?>
    <div class="text-center text-body-secondary py-5 border rounded-3">Trenutno nemate dodijeljenih zadataka.</div>
<?php else: ?>
    <div class="kanban" data-csrf="<?= e(\App\Core\Csrf::token()) ?>" data-osnova="<?= e(url('/')) ?>">
        <?php foreach ($nazivi as $status => $naslov): ?>
            <section class="kanban-stupac" aria-labelledby="stupac-<?= e($status) ?>">
                <h2 class="h6 kanban-naslov" id="stupac-<?= e($status) ?>">
                    <?= e($naslov) ?> <span class="badge text-bg-light border"><?= count($stupci[$status]) ?></span>
                </h2>
                <div class="kanban-kartice" data-status="<?= e($status) ?>" data-prima="<?= e($prima[$status] ?? '') ?>">
                    <?php foreach ($stupci[$status] as $z): ?>
                        <article class="card kanban-kartica hitnost-<?= e($z['hitnost']) ?>" data-id="<?= (int) $z['id'] ?>">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between gap-2 mb-1">
                                    <a href="<?= e(url('/prijave/' . (int) $z['id'])) ?>" class="fw-semibold text-body text-decoration-none">
                                        <?= e($z['naslov']) ?>
                                    </a>
                                    <?= StatusPrijave::hitnostBadge($z['hitnost']) ?>
                                </div>
                                <div class="small text-body-secondary">
                                    <?= e($z['vrsta']) ?> · <?= e($z['cetvrt']) ?><br>
                                    <?= $z['adresa'] ? e($z['adresa']) . '<br>' : '' ?>
                                    prijavljeno <?= e(datum($z['kreirano_u'])) ?>
                                </div>
                                <?php if (StatusPrijave::canTransition($z['status'], 'U_RADU', 'radnik')): ?>
                                    <!-- Bez JavaScripta (ili na mobitelu) isto radi i gumb -->
                                    <form method="post" action="<?= e(url('/radnik/zadaci/' . (int) $z['id'] . '/zapocni')) ?>" class="mt-2">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Započni rad</button>
                                    </form>
                                <?php elseif ($z['status'] === 'U_RADU'): ?>
                                    <a href="<?= e(url('/prijave/' . (int) $z['id'] . '#zavrsetak')) ?>" class="btn btn-sm btn-outline-success mt-2">Pošalji na provjeru</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
