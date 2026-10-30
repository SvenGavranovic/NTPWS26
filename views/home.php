<div class="p-4 p-md-5 mb-4 bg-body-tertiary rounded-3">
    <h1 class="display-6 fw-semibold">Prijavite komunalni problem u Zagrebu</h1>
    <p class="lead col-lg-8">
        Rupa na cesti, neispravna rasvjeta ili divlje odlagalište? Pošaljite prijavu s fotografijom
        i pratite kako gradske službe rješavaju problem. Na karti pratite radove i stanje u prometu.
    </p>
    <?php if (!\App\Core\Auth::check()): ?>
        <a href="<?= e(url('/racun/registracija')) ?>" class="btn btn-primary">Registrirajte se</a>
        <a href="<?= e(url('/racun/prijava')) ?>" class="btn btn-outline-secondary">Prijava</a>
    <?php endif; ?>
</div>

<p class="text-muted">Vijesti, karta i prijave problema dodaju se u sljedećim fazama.</p>
