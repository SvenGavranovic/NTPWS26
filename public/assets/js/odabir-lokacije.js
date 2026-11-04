// Odabir lokacije na karti: <div data-odabir-lokacije data-lat="id-polja" data-lng="id-polja" data-granice="[[..],[..]]"
//   data-cetvrt="id-polja" data-api-lokacija="url"> (zadnja dva neobavezna; adresa se samo ispisuje, polje adrese popunjava korisnik)
(function () {
    if (typeof L === 'undefined') {
        return;
    }
    // Samo prikaz točke: <div data-prikaz-lokacije data-tocka="[lat, lng]">
    document.querySelectorAll('[data-prikaz-lokacije]').forEach(function (el) {
        var tocka = JSON.parse(el.getAttribute('data-tocka'));
        var karta = L.map(el, { scrollWheelZoom: false }).setView(tocka, 16);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            // OSM traži Referer; šalje se samo ishodište (http://localhost), ne puna adresa stranice
            referrerPolicy: 'strict-origin-when-cross-origin',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(karta);
        L.marker(tocka).addTo(karta);
    });

    document.querySelectorAll('[data-odabir-lokacije]').forEach(function (el) {
        var lat = document.getElementById(el.getAttribute('data-lat'));
        var lng = document.getElementById(el.getAttribute('data-lng'));
        var granice = L.latLngBounds(JSON.parse(el.getAttribute('data-granice')));
        var poruka = el.parentNode.querySelector('[data-lokacija-poruka]');
        var poljeCetvrt = document.getElementById(el.getAttribute('data-cetvrt') || '');
        var apiLokacija = el.getAttribute('data-api-lokacija');
        var marker = null;
        var tocnost = null;
        var upit = 0;

        function javi(tekst) {
            if (poruka) { poruka.textContent = tekst; }
        }

        var karta = L.map(el, { maxBounds: granice.pad(0.05), maxBoundsViscosity: 1.0, minZoom: 11 })
            .setView([45.8131, 15.9775], 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            // OSM traži Referer; šalje se samo ishodište (http://localhost), ne puna adresa stranice
            referrerPolicy: 'strict-origin-when-cross-origin',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(karta);

        // Četvrt se bira samo ako je prazna ili ju je ranije odabrao JS; ručni odabir ima prednost.
        if (poljeCetvrt) {
            poljeCetvrt.addEventListener('change', function () { delete poljeCetvrt.dataset.auto; });
        }

        function popuniAdresu(tocka, opis) {
            if (!apiLokacija) {
                return;
            }
            var ovaj = ++upit;
            fetch(apiLokacija + '?lat=' + tocka.lat.toFixed(6) + '&lng=' + tocka.lng.toFixed(6), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (ovaj !== upit || !j || !j.data) {
                        return;
                    }
                    var d = j.data;
                    if (poljeCetvrt && d.cetvrt_id && (poljeCetvrt.value === '' || poljeCetvrt.dataset.auto)) {
                        poljeCetvrt.value = String(d.cetvrt_id);
                        poljeCetvrt.dataset.auto = '1';
                    }
                    if (d.adresa || d.cetvrt) {
                        javi(opis + ' · ' + [d.adresa, d.cetvrt].filter(Boolean).join(', '));
                    }
                })
                .catch(function () { /* adresa je neobavezna, može se upisati ručno */ });
        }

        function oznaci(tocka) {
            lat.value = tocka.lat.toFixed(6);
            lng.value = tocka.lng.toFixed(6);
            if (marker) {
                marker.setLatLng(tocka);
            } else {
                marker = L.marker(tocka, { draggable: true }).addTo(karta);
                marker.on('dragend', function () {
                    if (tocnost) { karta.removeLayer(tocnost); tocnost = null; }
                    postavi(marker.getLatLng(), false);
                });
            }
        }

        function postavi(tocka, centriraj, opis) {
            if (!granice.contains(tocka)) {
                javi('Lokacija mora biti na području Grada Zagreba.');
                return false;
            }
            oznaci(tocka);
            if (centriraj) { karta.setView(tocka, 17); }
            opis = opis || ('Odabrano: ' + lat.value + ', ' + lng.value);
            javi(opis);
            popuniAdresu(tocka, opis);
            return true;
        }

        // povratak na obrazac nakon pogreške: točka se samo prikaže, adresa se ne traži ponovno
        if (lat.value && lng.value) {
            var stara = L.latLng(parseFloat(lat.value), parseFloat(lng.value));
            if (granice.contains(stara)) {
                oznaci(stara);
                karta.setView(stara, 16);
                javi('Odabrano: ' + lat.value + ', ' + lng.value);
            }
        }
        karta.on('click', function (e) {
            if (tocnost) { karta.removeLayer(tocnost); tocnost = null; }
            postavi(e.latlng, false);
        });

        var gumbMoja = el.parentNode.querySelector('[data-moja-lokacija]');

        function mojaLokacija() {
            if (!window.isSecureContext) {
                javi('Lokacija uređaja dostupna je samo preko sigurne veze (HTTPS). Odaberite mjesto na karti.');
                return;
            }
            if (!navigator.geolocation) {
                javi('Preglednik ne podržava dohvat lokacije. Odaberite mjesto na karti.');
                return;
            }
            // Prvi odgovor je često gruba procjena (IP adresa, ± desetci km), a GPS stiže kasnije.
            // Zato se lokacija prati do DOVOLJNO_TOCNO ili do isteka vremena i uzima najtočnija.
            var DOVOLJNO_TOCNO = 50, NAJVISE_M = 1000, TRAJANJE_MS = 15000;
            var najbolja = null;
            var gotovo = false;

            gumbMoja.disabled = true;
            javi('Tražim lokaciju uređaja…');

            function zavrsi(err) {
                if (gotovo) { return; }
                gotovo = true;
                navigator.geolocation.clearWatch(pracenje);
                clearTimeout(rok);
                gumbMoja.disabled = false;

                if (!najbolja) {
                    var razlozi = {
                        1: 'Pristup lokaciji nije dopušten. Dopustite ga u postavkama preglednika ili odaberite mjesto na karti.',
                        2: 'Lokaciju uređaja trenutačno nije moguće odrediti. Provjerite je li uključena lokacija (GPS).',
                        3: 'Dohvat lokacije traje predugo. Pokušajte ponovno ili odaberite mjesto na karti.'
                    };
                    javi((err && razlozi[err.code]) || 'Lokaciju uređaja nije moguće odrediti. Odaberite mjesto na karti.');
                    return;
                }
                var tocka = L.latLng(najbolja.latitude, najbolja.longitude);
                var m = Math.round(najbolja.accuracy);
                if (m > NAJVISE_M) {
                    javi('Lokacija uređaja je preneprecizna (± ' + (m >= 10000 ? Math.round(m / 1000) + ' km' : m + ' m')
                        + '). Uključite lokaciju (GPS) i pokušajte ponovno ili odaberite mjesto na karti.');
                    return;
                }
                if (!granice.contains(tocka)) {
                    javi('Vaša lokacija nije na području Grada Zagreba. Odaberite mjesto na karti.');
                    return;
                }
                if (tocnost) { karta.removeLayer(tocnost); tocnost = null; }
                if (m > 30) {
                    tocnost = L.circle(tocka, { radius: m, weight: 1, fillOpacity: 0.1 }).addTo(karta);
                }
                postavi(tocka, true, 'Lokacija uređaja (± ' + m + ' m' + (m > 30 ? ', po potrebi povucite oznaku' : '') + ')');
            }

            var pracenje = navigator.geolocation.watchPosition(function (pos) {
                if (!najbolja || pos.coords.accuracy < najbolja.accuracy) {
                    najbolja = pos.coords;
                }
                if (najbolja.accuracy <= DOVOLJNO_TOCNO) {
                    zavrsi();
                } else {
                    javi('Tražim precizniju lokaciju… (trenutačno ± ' + Math.round(najbolja.accuracy) + ' m)');
                }
            }, function (err) {
                if (err.code === 1 || !najbolja) { zavrsi(err); }
            }, { enableHighAccuracy: true, timeout: TRAJANJE_MS, maximumAge: 0 });
            var rok = setTimeout(zavrsi, TRAJANJE_MS);
        }

        if (gumbMoja) {
            gumbMoja.addEventListener('click', mojaLokacija);
            // nakon fotografiranja kamerom korisnik je na mjestu problema: lokacija se dohvaća odmah
            document.addEventListener('fotografija-snimljena', function () {
                if (!lat.value && window.isSecureContext && navigator.geolocation) {
                    mojaLokacija();
                }
            });
        }

        var gumbUkloni = el.parentNode.querySelector('[data-ukloni-lokaciju]');
        if (gumbUkloni) {
            gumbUkloni.addEventListener('click', function () {
                lat.value = '';
                lng.value = '';
                upit++;
                if (marker) { karta.removeLayer(marker); marker = null; }
                if (tocnost) { karta.removeLayer(tocnost); tocnost = null; }
                javi('Lokacija nije odabrana.');
            });
        }
    });
})();
