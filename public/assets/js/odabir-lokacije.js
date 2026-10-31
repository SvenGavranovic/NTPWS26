// Odabir lokacije na karti: <div data-odabir-lokacije data-lat="id-polja" data-lng="id-polja" data-granice="[[..],[..]]">
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
        var marker = null;

        var karta = L.map(el, { maxBounds: granice.pad(0.05), maxBoundsViscosity: 1.0, minZoom: 11 })
            .setView([45.8131, 15.9775], 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            // OSM traži Referer; šalje se samo ishodište (http://localhost), ne puna adresa stranice
            referrerPolicy: 'strict-origin-when-cross-origin',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(karta);

        function postavi(tocka, centriraj) {
            if (!granice.contains(tocka)) {
                if (poruka) { poruka.textContent = 'Lokacija mora biti na području Grada Zagreba.'; }
                return;
            }
            lat.value = tocka.lat.toFixed(6);
            lng.value = tocka.lng.toFixed(6);
            if (marker) {
                marker.setLatLng(tocka);
            } else {
                marker = L.marker(tocka, { draggable: true }).addTo(karta);
                marker.on('dragend', function () { postavi(marker.getLatLng(), false); });
            }
            if (centriraj) { karta.setView(tocka, 16); }
            if (poruka) { poruka.textContent = 'Odabrano: ' + lat.value + ', ' + lng.value; }
        }

        if (lat.value && lng.value) {
            postavi(L.latLng(parseFloat(lat.value), parseFloat(lng.value)), true);
        }
        karta.on('click', function (e) { postavi(e.latlng, false); });

        var gumbMoja = el.parentNode.querySelector('[data-moja-lokacija]');
        if (gumbMoja && navigator.geolocation) {
            gumbMoja.classList.remove('d-none');
            gumbMoja.addEventListener('click', function () {
                navigator.geolocation.getCurrentPosition(function (pos) {
                    postavi(L.latLng(pos.coords.latitude, pos.coords.longitude), true);
                }, function () {
                    if (poruka) { poruka.textContent = 'Lokaciju uređaja nije moguće dohvatiti.'; }
                });
            });
        }

        var gumbUkloni = el.parentNode.querySelector('[data-ukloni-lokaciju]');
        if (gumbUkloni) {
            gumbUkloni.addEventListener('click', function () {
                lat.value = '';
                lng.value = '';
                if (marker) { karta.removeLayer(marker); marker = null; }
                if (poruka) { poruka.textContent = 'Lokacija nije odabrana.'; }
            });
        }
    });
})();
