# Zagreb Prijave

Web aplikacija za prijavu komunalnih problema u Gradu Zagrebu, pregled radova na infrastrukturi i stanja u prometu. Sve je ograničeno na Zagreb.

Jezik: hrvatski. Kod: tehnički pojmovi engleski (Router, Controller), pojmovi domene hrvatski (Korisnik, prijave).
Upute kolegija: https://gist.github.com/asimec1/82b53312bd1a2d340d90acde30f67320

## Tehnologije
- Čisti PHP 8.x, vlastiti MVC (front controller, router, PDO), bez frameworka.
- MySQL/MariaDB (XAMPP); prepared statements, transakcije kad se mijenja više povezanih zapisa.
- Composer: `vlucas/phpdotenv`, `ezyang/htmlpurifier`, `justinrainbow/json-schema`.
- Frontend: Bootstrap 5, vanilla JS (fetch prema vlastitom API-ju), Quill, SortableJS, Leaflet + OpenStreetMap.

## Uloge
| Uloga | Ovlasti |
|---|---|
| Posjetitelj | javne vijesti, karta, radovi, promet |
| Korisnik | prijava problema sa slikom, vlastite prijave, profil |
| Radnik | dodijeljeni zadaci (kanban), slanje na provjeru |
| Urednik | vlastite vijesti |
| Administrator | sve vijesti, kategorije, korisnici i uloge, dodjela, hitnost, radovi, odobrenje završetka |

Registracija daje samo ulogu korisnik. Sve ovlasti provjerava backend. Deaktivacija prekida otvorene sesije.

## Tok prijave
`PRIJAVLJENO → DODIJELJENO → U_RADU → CEKA_PROVJERU → ZAVRSENO` (ili `VRACENO` natrag radniku).
- Hitnost mijenja samo admin; zadatak mijenja samo dodijeljeni radnik.
- Radnik ne može postaviti ZAVRSENO; CEKA_PROVJERU traži sliku "poslije" i napomenu.
- Admin odobrava ili vraća (s komentarom). Svaka promjena ide u `povijest_statusa`.

## Karta i promet
- Leaflet + OSM, `maxBounds` na Zagreb (oko lat 45.70–45.95, lon 15.80–16.25).
- Markeri prijava i radova iz vlastitog API-ja; radove unosi admin.
- TomTom Traffic preko proxyja `/api/v1/promet`: ključ samo u `.env`, timeout, cache 60–120 s, obrada uspjeha / nedostupnosti / neočekivane strukture, vrijeme zadnjeg dohvata, jasno označen testni odgovor. Prije implementacije provjeriti aktualne uvjete i limite TomToma.
- Opcionalno Nominatim (najviše 1 zahtjev/s, ispravan User-Agent).

## Vlastiti REST API (`/api/v1/`, JSON)
`GET/POST /vijesti`, `GET/PATCH/DELETE /vijesti/{id}`, `GET /kategorije`, `GET/POST /prijave`, `GET/PATCH /prijave/{id}`, `GET /radovi`, `GET /promet`.
Ispravne metode i statusni kodovi (200, 201, 204, 400, 401, 403, 404, 500), dosljedna struktura uspjeha i pogreške, straničenje i filtar, validacija, autentifikacija za izmjene, neobjavljene vijesti nikad ne izlaze, JSON Schema za barem jedan POST (valjan i nevaljan primjer). Frontend stvarno koristi API.

## Sigurnost
`password_hash`, prepared statements, validacija na serveru, escaping izlaza, HTMLPurifier, CSRF, `session_regenerate_id()`, siguran upload (MIME, veličina, nasumično ime, bez izvršavanja), tajne samo u `.env`, generičke poruke pogrešaka.

## Preostale faze
7. Vlastiti API + JSON Schema + `docs/API.md`
8. Karta, radovi, TomTom s cacheom
9. Završno testiranje, README, screenshotovi

## Pravila rada
- Jedna faza po jedna; nakon faze predložiti commit poruku i čekati potvrdu.
- Ne čitati ni ispisivati `.env` ni stvarne ključeve; koristiti `.env.example`.
- Dokumentacija u repozitoriju: `README.md` (instalacija, korištenje AI alata), `docs/UPUTE.md`, `docs/TESTOVI.md`, `docs/API.md`, `docs/ER.md`.
- Testovi u `tests/*.sh` dopunjuju se u svakoj fazi.
