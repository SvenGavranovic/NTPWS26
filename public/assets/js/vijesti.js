// Pretraga vijesti uživo preko GET /api/v1/vijesti. Bez JS-a obrazac radi kao obična GET pretraga.
(function () {
    var polje = document.getElementById('q');
    var rezultati = document.getElementById('vijesti-rezultati');
    if (!polje || !rezultati || !window.fetch) {
        return;
    }
    var api = rezultati.getAttribute('data-api');
    var stranica = rezultati.getAttribute('data-stranica');
    var kategorija = rezultati.getAttribute('data-kategorija');
    var poStranici = rezultati.getAttribute('data-po-stranici');
    var broj = document.getElementById('vijesti-broj');
    var odgoda = null;
    var zadnjiZahtjev = 0;

    function el(oznaka, klasa, tekst) {
        var e = document.createElement(oznaka);
        if (klasa) { e.className = klasa; }
        if (tekst !== undefined) { e.textContent = tekst; }
        return e;
    }

    function datum(iso) {
        var d = new Date(iso);
        return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear() + '.';
    }

    function parametri(q, str) {
        var p = new URLSearchParams();
        if (q) { p.set('q', q); }
        if (kategorija) { p.set('kategorija', kategorija); }
        if (str > 1) { p.set('stranica', str); }
        return p;
    }

    function kartica(v) {
        var stupac = el('div', 'col-md-6');
        var clanak = el('article', 'card h-100 vijest-kartica');
        if (v.slika) {
            var slika = el('img', 'card-img-top');
            slika.src = v.slika;
            slika.alt = '';
            clanak.appendChild(slika);
        }
        var tijelo = el('div', 'card-body d-flex flex-column');
        var vrh = el('div', 'd-flex justify-content-between align-items-center small mb-2');
        vrh.appendChild(el('span', 'badge text-bg-light border', v.kategorija.naziv));
        vrh.appendChild(el('time', 'text-body-secondary', datum(v.datum_objave)));
        var naslov = el('h3', 'h5 card-title');
        var a = el('a', 'stretched-link text-body text-decoration-none', v.naslov);
        a.href = v.url;
        naslov.appendChild(a);
        tijelo.appendChild(vrh);
        tijelo.appendChild(naslov);
        tijelo.appendChild(el('p', 'card-text text-body-secondary flex-grow-1', v.sazetak));
        tijelo.appendChild(el('p', 'card-text small text-body-secondary mb-0', v.autor));
        clanak.appendChild(tijelo);
        stupac.appendChild(clanak);
        return stupac;
    }

    function prikazi(odgovor, q) {
        var meta = odgovor.meta;
        rezultati.replaceChildren();

        if (odgovor.data.length === 0) {
            var prazno = el('div', 'text-center border rounded-3 py-5 px-3 bg-body-tertiary');
            prazno.appendChild(el('p', 'h5', 'Nema pronađenih vijesti'));
            prazno.appendChild(el('p', 'text-body-secondary mb-0', 'Nijedna vijest ne odgovara pretrazi „' + q + '”.'));
            rezultati.appendChild(prazno);
        } else {
            var red = el('div', 'row g-4');
            odgovor.data.forEach(function (v) { red.appendChild(kartica(v)); });
            rezultati.appendChild(red);
        }

        if (meta.ukupno_stranica > 1) {
            var nav = el('nav', 'mt-4');
            nav.setAttribute('aria-label', 'Straničenje');
            var ul = el('ul', 'pagination justify-content-center flex-wrap');
            for (var i = 1; i <= meta.ukupno_stranica; i++) {
                var li = el('li', 'page-item' + (i === meta.stranica ? ' active' : ''));
                var veza = el('a', 'page-link', String(i));
                veza.href = stranica + '?' + parametri(q, i).toString();
                li.appendChild(veza);
                ul.appendChild(li);
            }
            nav.appendChild(ul);
            rezultati.appendChild(nav);
        }

        if (broj) {
            broj.textContent = meta.ukupno + (meta.ukupno === 1 ? ' vijest' : ' vijesti');
        }
        var query = parametri(q, 1).toString();
        history.replaceState(null, '', stranica + (query ? '?' + query : ''));
    }

    function trazi() {
        var q = polje.value.trim();
        var p = parametri(q, 1);
        p.set('po_stranici', poStranici);
        var ovaj = ++zadnjiZahtjev;

        fetch(api + '?' + p.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (odgovor) {
                // stariji odgovor koji stigne nakon novijeg se zanemaruje
                if (ovaj === zadnjiZahtjev && odgovor.data) {
                    prikazi(odgovor, q);
                }
            })
            .catch(function () { /* ostaje prethodni prikaz; Enter i dalje radi preko poslužitelja */ });
    }

    polje.addEventListener('input', function () {
        clearTimeout(odgoda);
        odgoda = setTimeout(trazi, 300);
    });
})();
