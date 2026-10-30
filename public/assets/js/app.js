/*
 * Zajednički JavaScript za sve stranice.
 * Obrasci s atributom data-potvrda traže potvrdu prije slanja (npr. brisanje).
 * To je samo pomoć korisniku – ovlasti i CSRF i dalje provjerava poslužitelj.
 */
document.addEventListener('submit', function (event) {
    var poruka = event.target.getAttribute('data-potvrda');
    if (poruka && !window.confirm(poruka)) {
        event.preventDefault();
    }
});
