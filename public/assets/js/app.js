// Potvrda prije slanja obrasca s atributom data-potvrda
document.addEventListener('submit', function (event) {
    var poruka = event.target.getAttribute('data-potvrda');
    if (poruka && !window.confirm(poruka)) {
        event.preventDefault();
    }
});

// Pregled odabrane slike: <input type="file" data-pregled="id-slike">
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

// Fotografija prijave: <div data-odabir-slike data-max-bajtova="..."> s poljem za datoteku.
// Na mobitelu gumbi za kameru i galeriju; velike fotografije smanjuju se prije slanja
// (ponovno iscrtavanje uklanja i EXIF podatke, uključujući GPS).
(function () {
    var NAJVECA_STRANICA = 2048;
    var PRAG_BAJTOVA = 1.5 * 1024 * 1024;

    function smanji(datoteka, gotovo) {
        var adresa = URL.createObjectURL(datoteka);
        var slika = new Image();
        slika.onload = function () {
            var k = Math.min(1, NAJVECA_STRANICA / Math.max(slika.naturalWidth, slika.naturalHeight));
            var platno = document.createElement('canvas');
            platno.width = Math.round(slika.naturalWidth * k);
            platno.height = Math.round(slika.naturalHeight * k);
            var ctx = platno.getContext('2d');
            ctx.fillStyle = '#fff'; // prozirni PNG → bijela pozadina u JPEG-u
            ctx.fillRect(0, 0, platno.width, platno.height);
            ctx.drawImage(slika, 0, 0, platno.width, platno.height);
            URL.revokeObjectURL(adresa);
            platno.toBlob(gotovo, 'image/jpeg', 0.85);
        };
        slika.onerror = function () {
            URL.revokeObjectURL(adresa);
            gotovo(null);
        };
        slika.src = adresa;
    }

    document.querySelectorAll('[data-odabir-slike]').forEach(function (blok) {
        var polje = blok.querySelector('input[type=file]');
        var gumbi = blok.querySelector('[data-gumbi-slike]');
        var poruka = blok.querySelector('[data-slika-poruka]');
        var posalji = polje.form && polje.form.querySelector('[type=submit]');
        var maxBajtova = parseInt(blok.getAttribute('data-max-bajtova'), 10) || 0;
        var izKamere = false;

        function javi(tekst) {
            poruka.textContent = tekst;
            poruka.classList.toggle('d-none', !tekst);
        }

        if (gumbi && window.matchMedia('(pointer: coarse)').matches) {
            gumbi.classList.replace('d-none', 'd-flex');
            blok.querySelector('[data-kamera]').addEventListener('click', function () {
                polje.setAttribute('capture', 'environment'); // stražnja kamera
                izKamere = true;
                polje.click();
            });
            blok.querySelector('[data-galerija]').addEventListener('click', function () {
                polje.removeAttribute('capture');
                izKamere = false;
                polje.click();
            });
        }

        polje.addEventListener('change', function () {
            var datoteka = polje.files && polje.files[0];
            javi('');
            if (!datoteka) {
                return;
            }
            if (izKamere) {
                blok.dispatchEvent(new CustomEvent('fotografija-snimljena', { bubbles: true }));
            }
            if (datoteka.size <= PRAG_BAJTOVA || typeof DataTransfer === 'undefined') {
                if (maxBajtova && datoteka.size > maxBajtova) {
                    javi('Fotografija je prevelika. Odaberite manju.');
                }
                return;
            }
            if (posalji) { posalji.disabled = true; }
            smanji(datoteka, function (blob) {
                if (posalji) { posalji.disabled = false; }
                if (!blob || blob.size >= datoteka.size) {
                    if (maxBajtova && datoteka.size > maxBajtova) {
                        javi('Fotografiju nije moguće smanjiti. Odaberite manju.');
                    }
                    return;
                }
                try {
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], datoteka.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                    polje.files = dt.files;
                } catch (e) {
                    if (maxBajtova && datoteka.size > maxBajtova) {
                        javi('Fotografija je prevelika. Odaberite manju.');
                    }
                }
            });
        });
    });
})();
