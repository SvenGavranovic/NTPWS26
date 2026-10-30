# CLAUDE.md – Zagreb Prijave (kolegij: Napredno web projektiranje web servisa)

## Kontekst
Studentski semestralni projekt. Jezik komunikacije: **hrvatski**. Kod, nazivi tablica i komentari: hrvatski ili engleski, ali dosljedno.
Službene upute (OBAVEZNO pročitaj na početku rada, mogu se mijenjati):
https://gist.github.com/asimec1/82b53312bd1a2d340d90acde30f67320

Student mora razumjeti i obraniti sav kod. Zato uz svaku veću izmjenu **kratko objasni zašto je napisana tako** i nakon svake faze dopuni `docs/OBJASNJENJA.md` (što je napravljeno, zašto, tok podataka zahtjev → baza → odgovor, sigurnosne odluke, moguća pitanja na obrani).

## Tema
Web aplikacija za prijavu komunalnih i drugih problema u gradu **Zagrebu**, pregled radova na gradskoj infrastrukturi i stanja u prometu. Sve je ograničeno SAMO na Zagreb.

## Tehnologije
- Backend: **čisti PHP 8.x**, vlastita MVC struktura (front controller, router, PDO). Bez frameworka.
- Baza: MySQL/MariaDB (XAMPP). Prepared statements svuda, transakcije gdje se mijenja više povezanih zapisa.
- Composer (samo malo biblioteka): `vlucas/phpdotenv`, `ezyang/htmlpurifier`, `justinrainbow/json-schema`.
- Frontend: Bootstrap 5, vanilla JS (fetch prema vlastitom API-ju), Quill ili TinyMCE (rich-text), SortableJS (kanban), Leaflet + OpenStreetMap (karta).
- Okruženje: VS Code + XAMPP (Apache + MySQL). Student pokreće Apache/MySQL i testira u pregledniku.

## Uloge
| Uloga | Ovlasti |
|---|---|
| Posjetitelj | Pregled javnih vijesti, karte, radova i prometa |
| Registrirani korisnik | Prijava problema (upload slike), pregled vlastitih prijava, profil, promjena lozinke |
| Radnik | Vidi dodijeljene zadatke po hitnosti (kanban), šalje završetak na provjeru |
| Urednik | Izrada i uređivanje VLASTITIH vijesti |
| Administrator | Sve vijesti, kategorije, korisnici i uloge, aktivacija/deaktivacija računa, dodjela zadataka, hitnost, radovi, konačno odobrenje završetka |

Registracijom se dobiva samo osnovna uloga. Svaku ovlast provjerava **backend** (skrivanje gumba nije zaštita). Deaktivacija računa mora prekinuti i već otvorene sesije.

## Tok prijave i kontrola radnika
`PRIJAVLJENO → DODIJELJENO → U_RADU → CEKA_PROVJERU → ZAVRSENO` (ili `VRACENO` natrag radniku)
- Prijavu kreira korisnik: slika + gradska četvrt + opis. Sustav sam sprema datum i vrijeme te zadanu hitnost (placeholder, npr. `srednja`).
- Hitnost (`niska/srednja/visoka/kritična`) mijenja samo administrator.
- Zadatak smije mijenjati samo radnik kojem je dodijeljen.
- Radnik NE može sam označiti ZAVRSENO. Prijelaz u `CEKA_PROVJERU` traži obaveznu sliku "nakon" i napomenu.
- Samo administrator odobrava (`ZAVRSENO`) ili vraća (`VRACENO` uz komentar).
- Prijelazi statusa provjeravaju se na backendu (state machine). Svaka promjena ide u `povijest_statusa` (tko, kada, iz, u, komentar).

## Karta i promet (Zagreb)
- Leaflet + OSM pločice, `maxBounds` na Zagreb (otprilike lat 45.70–45.95, lon 15.80–16.25, fino podesiti).
- Markeri prijava i radova dolaze iz VLASTITOG API-ja.
- Radove (`radovi`) unosi administrator: lokacija (lat/lng), vrsta, početak, kraj, opis.
- **Vanjski API #1: TomTom Traffic** (incidenti + protok) preko PHP proxyja `/api/v1/promet`:
  - ključ samo u `.env`, nikad u frontendu ni u gitu,
  - timeout na zahtjev, cache 60–120 s (datoteka ili tablica),
  - obrada: uspjeh / servis nedostupan / neočekivana struktura odgovora,
  - prikaz vremena zadnjeg uspješnog dohvata; za demonstraciju kvara jasno označen testni odgovor.
  - Prije implementacije provjeri aktualne uvjete i limite na TomTom stranici s cijenama.
- **Vanjski API #2 (opcionalno): Nominatim** (adresa → koordinate), max 1 zahtjev/s, ispravan User-Agent.

## Gradske četvrti (šifrarnik, 17)
Donji grad, Gornji grad – Medveščak, Trnje, Maksimir, Peščenica – Žitnjak, Novi Zagreb – istok, Novi Zagreb – zapad, Trešnjevka – sjever, Trešnjevka – jug, Črnomerec, Gornja Dubrava, Donja Dubrava, Stenjevec, Podsused – Vrapče, Podsljeme, Sesvete, Brezovica.

## Baza (okvirno)
`uloge`, `korisnici` (UNIQUE e-mail, aktivan), `kategorije_vijesti`, `vijesti` (nacrt/objavljeno, autor, created_at, updated_at, slika), `gradske_cetvrti`, `vrste_problema`, `prijave`, `prijava_slike` (tip: prije/poslije), `zadaci` (prijava, radnik, status), `povijest_statusa`, `radovi`.
Pravila brisanja definirati eksplicitno (npr. kategorija s vijestima se ne smije obrisati). Potrebno: ER dijagram, `database/schema.sql`, `database/seed.sql` (testni podaci: sve uloge, više kategorija, objavljene i neobjavljene vijesti, prijave u raznim statusima; bez stvarnih osobnih podataka).

## Vlastiti REST API (JSON, `/api/v1/`)
`GET/POST /vijesti`, `GET/PATCH/DELETE /vijesti/{id}`, `GET /kategorije`, `GET/POST /prijave`, `GET/PATCH /prijave/{id}`, `GET /radovi`, `GET /promet`.
Zahtjevi: ispravne HTTP metode i statusni kodovi (200, 201, 204, 400, 401, 403, 404, 500), `Content-Type: application/json`, dosljedna struktura uspjeha i pogreške, straničenje + barem jedan filtar, validacija obaveznih polja i tipova, autentifikacija i autorizacija za izmjene, **neobjavljene vijesti nikad ne smiju procuriti kroz API ni javne stranice**, JSON Schema za barem jedan POST + demonstracija valjanog i nevaljanog primjera. Frontend mora stvarno koristiti API (npr. popis vijesti, karta).

## Sigurnost (obavezno)
`password_hash`/`password_verify`; PDO prepared statements; validacija na serveru; escaping izlaza (XSS); HTMLPurifier za rich-text; CSRF tokeni; `session_regenerate_id()` nakon prijave i sigurna odjava; upload: provjera MIME tipa i veličine, nasumično ime, bez izvršavanja (`.htaccess`/izvan webroota); tajne samo u `.env` (u `.gitignore`, uz `.env.example`); korisniku generičke poruke pogrešaka bez SQL-a i putanja.

## Struktura repozitorija (prijedlog)
```
public/            (index.php, assets, uploads)
src/ (Controllers, Models, Services, Core)
views/
database/ (schema.sql, seed.sql)
docs/ (OBJASNJENJA.md, API.md, ER.png, TESTOVI.md)
.env.example  .gitignore  composer.json  README.md
```

## Faze
1. Prijedlog projekta, repo, ER dijagram, schema.sql + seed.sql
2. Struktura koda, router, registracija/prijava/uloge, CSRF
3. Javni dio: vijesti, filtriranje, pretraga, straničenje, profil
4. CMS: vijesti, kategorije, korisnici
5. Prijave problema: upload, forma, popis
6. Admin i radnik: kanban, state machine, provjera završetka
7. Vlastiti API + JSON Schema + dokumentacija
8. Karta, radovi, TomTom integracija s cacheom
9. Testiranje, sigurnosni pregled, README, screenshotovi
10. Seminar (zadnji, piše ga student samostalno): "Arhitektura i razvoj RESTful API-ja u modernim web aplikacijama", s vlastitim API-jem kao studijom slučaja

## Pravila rada
- Radi jednu fazu po jednu, objasni što radiš, pa čekaj potvrdu prije sljedeće.
- Male, smislene izmjene i git commit poruke koje opisuju promjenu (predloži poruku nakon svakog koraka).
- NE čitaj i ne ispisuj `.env` ni stvarne ključeve/lozinke. Koristi `.env.example`.
- U dokumentaciji navedi korištenje AI alata i svrhu (zahtjev kolegija).
- Prije predaje: README s uputama (verzija PHP-a, ekstenzije, baza, konfiguracija, pokretanje), dokumentacija API-ja, tablica testova (očekivano vs dobiveno).
