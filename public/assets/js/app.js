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

/*
 * Pregled odabrane slike prije slanja: <input type="file" data-pregled="id-slike">.
 * Slika se ne šalje nikamo – URL.createObjectURL prikazuje lokalnu datoteku.
 */
document.addEventListener('change', function (event) {
    var polje = event.target;
    var pregled = polje.getAttribute && document.getElementById(polje.getAttribute('data-pregled') || '');
    if (!pregled) {
        return;
    }
    var datoteka = polje.files && polje.files[0];
    if (datoteka && datoteka.type.indexOf('image/') === 0) {
        pregled.src = URL.createObjectURL(datoteka);
        pregled.classList.remove('d-none');
    } else {
        pregled.classList.add('d-none');
    }
});
