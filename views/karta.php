<?php
/**
 * @var array $granice  [[minLat, minLng], [maxLat, maxLng]]
 * @var array $statusi  kod => naziv statusa prijave
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
    <h1 class="h2 mb-0">Karta Zagreba</h1>
    <span class="text-body-secondary small">Prijavljeni problemi, radovi na infrastrukturi i stanje u prometu</span>
</div>

<div id="promet-info" class="alert alert-light border small py-2" role="status" aria-live="polite">Učitavanje podataka o prometu…</div>

<div id="karta" class="karta rounded-3 border"
     data-api="<?= e(url('/api/v1')) ?>"
     data-granice="<?= e(json_encode($granice)) ?>"
     data-statusi="<?= e(json_encode($statusi, JSON_UNESCAPED_UNICODE)) ?>"
     role="application" aria-label="Karta Zagreba s prijavama, radovima i prometom"></div>

<noscript><div class="alert alert-warning mt-3">Karta zahtijeva JavaScript.</div></noscript>

<div class="row g-3 mt-2 small">
    <div class="col-md-4">
        <h2 class="h6">Prijave problema</h2>
        <span class="legenda-tocka" style="background:#6c757d"></span> prijavljeno
        <span class="legenda-tocka ms-2" style="background:#0d6efd"></span> u obradi
        <span class="legenda-tocka ms-2" style="background:#198754"></span> riješeno
    </div>
    <div class="col-md-4">
        <h2 class="h6">Radovi</h2>
        <span class="legenda-kvadrat" style="background:#fd7e14"></span> u tijeku
        <span class="legenda-kvadrat ms-2" style="background:#6f42c1"></span> planirani
        <span class="legenda-kvadrat ms-2" style="background:#adb5bd"></span> završeni (zaseban sloj)
    </div>
    <div class="col-md-4">
        <h2 class="h6">Promet</h2>
        <span class="legenda-crta" style="background:#ffc107"></span> manji zastoji
        <span class="legenda-crta ms-2" style="background:#dc3545"></span> veći zastoji / zatvoreno<br>
        Crtkano: dugotrajno bez najavljenog kraja (možda zastarjelo)<br>
        Protok: <span class="legenda-tocka" style="background:#198754"></span> slobodno
        <span class="legenda-tocka ms-1" style="background:#fd7e14"></span> usporeno
        <span class="legenda-tocka ms-1" style="background:#dc3545"></span> gužva
    </div>
</div>
<p class="small text-body-secondary mt-3 mb-0">
    Slojeve uključujete i isključujete u gornjem desnom kutu karte. Podaci o prometu osvježavaju se automatski.
    Karta: © OpenStreetMap suradnici. Promet: © TomTom.
</p>
