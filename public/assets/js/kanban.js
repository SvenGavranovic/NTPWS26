// Kanban radnika (SortableJS): povlačenje u "U radu" šalje PATCH /api/v1/prijave/{id}, u "Čeka provjeru" otvara obrazac.
(function () {
    var ploca = document.querySelector('.kanban');
    if (!ploca || typeof Sortable === 'undefined') {
        return;
    }
    var csrf = ploca.getAttribute('data-csrf');
    var osnova = ploca.getAttribute('data-osnova').replace(/\/$/, '');
    var poruka = document.getElementById('kanban-poruka');

    function prikazi(tekst, vrsta) {
        poruka.textContent = tekst;
        poruka.className = 'alert alert-' + vrsta;
    }

    document.querySelectorAll('.kanban-kartice').forEach(function (stupac) {
        Sortable.create(stupac, {
            group: {
                name: 'kanban',
                pull: true,
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

                fetch(osnova + '/api/v1/prijave/' + id, {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify({ status: cilj })
                })
                    .then(function (odgovor) {
                        return odgovor.json().catch(function () {
                            return { greska: { poruka: 'Neočekivan odgovor poslužitelja.' } };
                        });
                    })
                    .then(function (podaci) {
                        if (podaci.data) {
                            window.location.reload();
                        } else {
                            vratiNatrag();
                            prikazi((podaci.greska && podaci.greska.poruka) || 'Promjena nije uspjela.', 'danger');
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
