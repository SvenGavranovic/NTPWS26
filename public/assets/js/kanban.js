/*
 * Kanban radnika (SortableJS).
 *
 * Povlačenje kartice je samo drugi način za istu radnju koju rade i gumbi:
 *  - u "U radu"        → POST /radnik/zadaci/{id}/zapocni (fetch, odgovor JSON)
 *  - u "Čeka provjeru" → otvara obrazac za završetak (treba slika i napomena)
 * Ako poslužitelj odbije promjenu, kartica se vraća natrag i prikaže se poruka.
 */
(function () {
    var ploca = document.querySelector('.kanban');
    if (!ploca || typeof Sortable === 'undefined') {
        return;
    }
    var csrf = ploca.getAttribute('data-csrf');
    var osnova = ploca.getAttribute('data-osnova').replace(/\/$/, '');
    var poruka = document.getElementById('kanban-poruka');

    function prikazi(tekst, vrsta) {
        poruka.textContent = tekst;          // textContent, ne innerHTML: poruka se ne tumači kao HTML
        poruka.className = 'alert alert-' + vrsta;
    }

    document.querySelectorAll('.kanban-kartice').forEach(function (stupac) {
        Sortable.create(stupac, {
            group: {
                name: 'kanban',
                pull: true,
                // Stupac prima karticu samo iz stupaca navedenih u data-prima
                put: function (cilj, izvor) {
                    var dopusteni = (cilj.el.getAttribute('data-prima') || '').split(',');
                    return dopusteni.indexOf(izvor.el.getAttribute('data-status')) !== -1;
                }
            },
            sort: false,               // redoslijed unutar stupca određuje hitnost, ne korisnik
            animation: 150,
            filter: 'a, button, form', // klik na poveznicu ili gumb ne pokreće povlačenje
            preventOnFilter: false,
            onEnd: function (dogadaj) {
                if (dogadaj.from === dogadaj.to) {
                    return;
                }
                var kartica = dogadaj.item;
                var id = kartica.getAttribute('data-id');
                var cilj = dogadaj.to.getAttribute('data-status');

                function vratiNatrag() {
                    dogadaj.from.insertBefore(kartica, dogadaj.from.children[dogadaj.oldIndex] || null);
                }

                if (cilj === 'CEKA_PROVJERU') {
                    vratiNatrag();
                    window.location.href = osnova + '/prijave/' + id + '#zavrsetak';
                    return;
                }

                fetch(osnova + '/radnik/zadaci/' + id + '/zapocni', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                    credentials: 'same-origin'
                })
                    .then(function (odgovor) {
                        return odgovor.json().catch(function () {
                            return { ok: false, poruka: 'Neočekivan odgovor poslužitelja.' };
                        });
                    })
                    .then(function (podaci) {
                        if (podaci.ok) {
                            // Osvježavanje: gumbi i brojači na karticama odgovaraju novom stanju
                            window.location.reload();
                        } else {
                            vratiNatrag();
                            prikazi(podaci.poruka || 'Promjena nije uspjela.', 'danger');
                        }
                    })
                    .catch(function () {
                        vratiNatrag();
                        prikazi('Poslužitelj nije dostupan. Pokušajte ponovno.', 'danger');
                    });
            }
        });
    });
})();
