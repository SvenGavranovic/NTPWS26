// Javna karta (Leaflet + OSM): prijave, radovi i promet iz /api/v1.
(function () {
    var el = document.getElementById('karta');
    if (!el || typeof L === 'undefined') {
        return;
    }
    var api = el.getAttribute('data-api');
    var granice = JSON.parse(el.getAttribute('data-granice'));
    var statusi = JSON.parse(el.getAttribute('data-statusi'));
    var info = document.getElementById('promet-info');

    var karta = L.map(el, {
        maxBounds: L.latLngBounds(granice).pad(0.05),
        maxBoundsViscosity: 1.0,
        minZoom: 11
    }).setView([45.8131, 15.9775], 12);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        // OSM traži Referer; šalje se samo ishodište (http://localhost), ne puna adresa stranice
        referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> suradnici'
    }).addTo(karta);

    var slojevi = {
        prijave: L.layerGroup().addTo(karta),
        radovi: L.layerGroup().addTo(karta),
        radoviZavrseni: L.layerGroup(),
        incidenti: L.layerGroup().addTo(karta),
        protok: L.layerGroup().addTo(karta)
    };
    L.control.layers(null, {
        'Prijave problema': slojevi.prijave,
        'Radovi': slojevi.radovi,
        'Radovi: završeni': slojevi.radoviZavrseni,
        'Promet: incidenti': slojevi.incidenti,
        'Promet: protok': slojevi.protok
    }, { collapsed: false }).addTo(karta);

    function dohvati(putanja) {
        return fetch(api + putanja, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    // Skočni prozor gradi se iz DOM elemenata s textContent, pa se podaci nikad ne tumače kao HTML.
    function popup(naslov, retci) {
        var div = document.createElement('div');
        var h = document.createElement('strong');
        h.textContent = naslov;
        div.appendChild(h);
        retci.forEach(function (r) {
            if (r) {
                var p = document.createElement('div');
                p.className = 'small';
                p.textContent = r;
                div.appendChild(p);
            }
        });
        return div;
    }

    function datum(iso) {
        if (!iso) { return ''; }
        var d = new Date(iso);
        return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear() + '.';
    }

    function datumVrijeme(iso) {
        if (!iso) { return ''; }
        return datum(iso) + ' u ' + new Date(iso).toLocaleTimeString('hr-HR', { hour: '2-digit', minute: '2-digit' });
    }

    function vrijeme(iso) {
        var d = new Date(iso);
        return d.toLocaleTimeString('hr-HR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    // --- Prijave (javni sažetak, sve stranice) ---
    function bojaPrijave(status) {
        if (status === 'ZAVRSENO') { return '#198754'; }
        return status === 'PRIJAVLJENO' ? '#6c757d' : '#0d6efd';
    }

    function ucitajPrijave(stranica) {
        dohvati('/prijave?po_stranici=100&stranica=' + stranica).then(function (o) {
            (o.data || []).forEach(function (p) {
                if (!p.lokacija) { return; }
                L.circleMarker([p.lokacija.lat, p.lokacija.lng], {
                    radius: 7, color: '#fff', weight: 2, fillColor: bojaPrijave(p.status), fillOpacity: 0.9
                }).bindPopup(popup(p.naslov, [
                    p.vrsta.naziv + ' · ' + p.cetvrt.naziv,
                    'Status: ' + (statusi[p.status] || p.status),
                    'Prijavljeno ' + datum(p.kreirano_u)
                ])).addTo(slojevi.prijave);
            });
            if (o.meta && stranica < o.meta.ukupno_stranica && stranica < 10) {
                ucitajPrijave(stranica + 1);
            }
        });
    }

    // --- Radovi (planirani i u tijeku; završeni u zasebnom sloju, isključenom po zadanom) ---
    function ucitajRadove() {
        dohvati('/radovi?po_stranici=100').then(function (o) {
            (o.data || []).forEach(function (r) {
                L.marker([r.lokacija.lat, r.lokacija.lng], {
                    icon: L.divIcon({ className: 'ikona-rad ikona-rad-' + r.stanje, iconSize: [18, 18] }),
                    title: r.naslov
                }).bindPopup(popup(r.naslov, [
                    r.vrsta_naziv + (r.cetvrt ? ' · ' + r.cetvrt.naziv : ''),
                    r.utjecaj_naziv,
                    'Od ' + datum(r.pocetak) + (r.kraj ? ' do ' + datum(r.kraj) : ' (kraj nije poznat)'),
                    r.adresa,
                    r.opis
                ])).addTo(r.stanje === 'zavrseno' ? slojevi.radoviZavrseni : slojevi.radovi);
            });
        });
    }

    // --- Promet (TomTom preko našeg proxyja) ---
    function bojaIncidenta(i) {
        return i.kategorija === 8 || i.intenzitet >= 3 ? '#dc3545' : (i.intenzitet === 2 ? '#fd7e14' : '#ffc107');
    }

    function bojaProtoka(p) {
        if (p.zatvoreno || p.omjer < 0.5) { return '#dc3545'; }
        return p.omjer < 0.8 ? '#fd7e14' : '#198754';
    }

    function prikaziInfo(data) {
        var tekst = data.testni
            ? data.upozorenje
            : 'Promet: ' + data.incidenti.length + ' incidenata · zadnji uspješan dohvat ' + vrijeme(data.zadnji_uspjesni_dohvat)
              + (data.izvor === 'cache' ? ' (iz cachea)' : '')
              + (data.skriveno_incidenata ? ' · ' + data.skriveno_incidenata + ' zastarjelih skrio administrator' : '')
              + (data.upozorenje ? ' · ' + data.upozorenje : '');
        info.textContent = tekst + ' · ' + data.atribucija;
        info.className = 'alert small py-2 ' + (data.testni || data.zastarjelo ? 'alert-warning' : 'alert-light border');
    }

    function ucitajPromet() {
        dohvati('/promet').then(function (o) {
            slojevi.incidenti.clearLayers();
            slojevi.protok.clearLayers();
            if (!o.data) {
                info.textContent = 'Podaci o prometu trenutno nisu dostupni: ' + ((o.greska && o.greska.poruka) || 'nepoznata pogreška') + '.';
                info.className = 'alert alert-danger small py-2';
                setTimeout(ucitajPromet, 60000);
                return;
            }
            var d = o.data;
            d.incidenti.forEach(function (i) {
                var opis = [
                    i.intenzitet_naziv,
                    i.od && i.do ? i.od + ' → ' + i.do : (i.od || ''),
                    i.kasnjenje_s ? 'Kašnjenje: ' + Math.round(i.kasnjenje_s / 60) + ' min' : '',
                    i.opis,
                    i.pocetak ? 'Od ' + datum(i.pocetak) + (i.kraj ? ' do ' + datum(i.kraj) : ' (kraj nije poznat)') : '',
                    i.zadnja_potvrda ? 'Zadnja potvrda: ' + datumVrijeme(i.zadnja_potvrda) + (i.broj_potvrda ? ' (' + i.broj_potvrda + ' potvrda)' : '') : '',
                    i.dugotrajno ? '⚠ Dugotrajna zatvorenost prema TomTomu – podatak možda nije ažuran.' : ''
                ];
                // dugotrajni zapisi bez kraja crtkano i blijeđe (moguće zastarjeli)
                var stil = i.dugotrajno ? { dashArray: '8 8', opacity: 0.5 } : { opacity: 0.85 };
                var naslov = (d.testni ? 'TEST · ' : '') + i.kategorija_naziv;
                var oblik = i.geometrija.koordinate.length > 1
                    ? L.polyline(i.geometrija.koordinate, Object.assign({ color: bojaIncidenta(i), weight: 6 }, stil))
                    : L.circleMarker(i.geometrija.koordinate[0], { radius: 8, color: bojaIncidenta(i), weight: 3, fillOpacity: 0.6 });
                oblik.bindPopup(popup(naslov, opis)).addTo(slojevi.incidenti);
            });
            d.protok.forEach(function (p) {
                L.circleMarker([p.lat, p.lng], {
                    radius: 9, color: '#fff', weight: 2, fillColor: bojaProtoka(p), fillOpacity: 0.95
                }).bindPopup(popup((d.testni ? 'TEST · ' : '') + p.naziv, [
                    p.zatvoreno ? 'Cesta je zatvorena' : 'Brzina: ' + p.brzina + ' km/h (slobodno ' + p.slobodna_brzina + ' km/h)',
                    'Protok: ' + Math.round(p.omjer * 100) + ' % slobodnog'
                ])).addTo(slojevi.protok);
            });
            prikaziInfo(d);
            setTimeout(ucitajPromet, (d.osvjezavanje_s || 90) * 1000);
        }).catch(function () {
            info.textContent = 'Podaci o prometu trenutno nisu dostupni.';
            info.className = 'alert alert-danger small py-2';
            setTimeout(ucitajPromet, 60000);
        });
    }

    ucitajPrijave(1);
    ucitajRadove();
    ucitajPromet();
})();
