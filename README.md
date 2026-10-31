# NTPWS26 – Zagreb Prijave

GitHub repo za projekt iz predmeta Napredne tehnike projektiranja web servisa.
Web aplikacija za prijavu komunalnih problema u Gradu Zagrebu, praćenje njihova rješavanja, pregled radova na infrastrukturi i stanja u prometu.

> Studentski projekt – **nije službena aplikacija Grada Zagreba**. Prijave se ne prosljeđuju gradskim službama.

**Glavne mogućnosti**
- javne vijesti s pretragom, filtrom po kategoriji i straničenjem;
- prijava problema s fotografijom, gradskom četvrti i lokacijom na karti; praćenje statusa prijave;
- kanban ploča za radnike, dodjela i provjera obavljenog posla za administratora (prijavljeno → dodijeljeno → u radu → čeka provjeru → završeno / vraćeno);
- CMS za vijesti (uređivač teksta, slika, nacrt i objava), kategorije, korisnike i uloge;
- karta Zagreba s prijavama, radovima i stanjem u prometu (TomTom);
- vlastiti REST API (`/api/v1`, JSON) s JSON Schemom.

Uloge i upute za korištenje: [docs/UPUTE.md](docs/UPUTE.md).

---

## Zahtjevi

| | Verzija | Napomena |
|---|---|---|
| PHP | 8.1 ili noviji (razvijano na 8.3) | ekstenzije: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json`, `zlib`; `curl` po želji |
| Baza | MariaDB 10.4+ ili MySQL 8.0.16+ | razvijano na MariaDB 10.11 (XAMPP) |
| Web poslužitelj | Apache 2.4 s `mod_rewrite` i `AllowOverride All` | XAMPP to ima zadano |
| Composer | 2.x | https://getcomposer.org/ |

Sve osim Composera dolazi s paketom **XAMPP** (https://www.apachefriends.org/).

## Instalacija i pokretanje (XAMPP, Windows)

1. Projekt smjestiti u `C:\xampp\htdocs\zagreb-prijave` (npr. `git clone` u `C:\xampp\htdocs`, pa mapu nazvati `zagreb-prijave`).
2. U XAMPP Control Panelu pokrenuti **Apache** i **MySQL**.
3. U mapi projekta instalirati ovisnosti:
   ```
   composer install
   ```
4. Napraviti konfiguraciju iz primjera:
   ```
   copy .env.example .env
   ```
   Zadane vrijednosti odgovaraju XAMPP-u (korisnik `root` bez lozinke, baza `zagreb_prijave`). Ako je projekt u drugoj mapi, promijeniti `APP_URL`.
5. Napraviti bazu, testne podatke i slike testnih prijava:
   ```
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql"
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\seed.sql"
   C:\xampp\php\php.exe database\demo_slike.php
   ```
6. Otvoriti http://localhost/zagreb-prijave

Pristupni podaci za testne račune (sve uloge) predaju se kroz LMS.

**Napomene**
- `schema.sql` briše i ponovno stvara sve tablice. Nakon promjene sheme korak 5 se ponavlja (vraćaju se početni testni podaci).
- `APP_DEBUG=true` u `.env` prikazuje detalje pogrešaka – samo za razvoj.

### Konfiguracija (`.env`)

| Varijabla | Značenje |
|---|---|
| `APP_ENV`, `APP_DEBUG` | okruženje; uz `APP_DEBUG=false` korisnici vide samo generičke poruke |
| `APP_URL` | adresa aplikacije (npr. `http://localhost/zagreb-prijave`) |
| `APP_TIMEZONE` | vremenska zona (`Europe/Zagreb`) |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | veza s bazom |
| `TOMTOM_API_KEY` | ključ za TomTom Traffic API (vidi niže) |
| `TOMTOM_TIMEOUT` | timeout poziva TomToma u sekundama (zadano 5) |
| `TOMTOM_CACHE_TTL` | trajanje cachea podataka o prometu u sekundama, 60–120 (zadano 90) |

`.env` je u `.gitignore` i ne objavljuje se; u repozitoriju je samo `.env.example` bez tajni.

### Promet (TomTom API ključ)

1. Registrirati se na https://developer.tomtom.com (besplatni plan, bez kreditne kartice) i napraviti API ključ s uključenim **Traffic API**.
2. U `.env` upisati `TOMTOM_API_KEY=...`.

Bez ključa aplikacija radi normalno, a karta prikazuje **jasno označene testne podatke** o prometu.
Ekstenzija `curl` nije nužna: ako je PHP pod Apacheom ne može učitati (česta pojava u XAMPP-u na Windowsima), proxy koristi ugrađeni https stream.

### Javna demo instalacija preko HTTPS-a (besplatno, privremeno)

Za testiranje s više uređaja (npr. mobitel na mobilnoj mreži) lokalna instalacija se može privremeno objaviti preko **Cloudflare Quick Tunnela** – besplatno i bez korisničkog računa:

1. Preuzeti `cloudflared-windows-amd64.exe` s https://github.com/cloudflare/cloudflared/releases/latest (službena stranica: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/).
2. Uz pokrenuti Apache, u naredbenom retku:
   ```
   cloudflared-windows-amd64.exe tunnel --url http://localhost:80
   ```
3. Otvoriti ispisanu adresu s dodatkom putanje aplikacije: `https://<nasumično>.trycloudflare.com/zagreb-prijave/`

Napomene:
- adresa je **javna** dok `cloudflared` radi i mijenja se pri svakom pokretanju; zaustavlja se s Ctrl+C;
- prije objave postaviti `APP_DEBUG=false`; koristiti samo testne podatke;
- Quick Tunnel je namijenjen testiranju (bez jamstva dostupnosti, najviše 200 istovremenih zahtjeva);
- aplikacija iza tunela koristi stvarnu IP adresu korisnika (ograničenje neuspjelih prijava) i postavlja kolačić sesije sa zastavicom `Secure`; preko HTTPS-a radi i gumb „Moja lokacija” na mobitelu.

---

## Korištene biblioteke i servisi

**PHP (Composer, `composer.json`)**

| Paket | Svrha |
|---|---|
| `vlucas/phpdotenv` ^5.6 | učitavanje konfiguracije iz `.env` |
| `ezyang/htmlpurifier` ^4.18 | čišćenje HTML-a iz uređivača vijesti (zaštita od XSS-a) |
| `justinrainbow/json-schema` ^6.0 | provjera zahtjeva API-ja prema JSON Schemi |

**Frontend (CDN jsDelivr, uz SRI provjeru integriteta)**

| Biblioteka | Verzija | Svrha |
|---|---|---|
| Bootstrap | 5.3.3 | izgled i responzivnost |
| Quill | 2.0.3 | uređivač teksta vijesti |
| SortableJS | 1.15.7 | povlačenje kartica na kanbanu |
| Leaflet | 1.9.4 | karta |

**Vanjski servisi**

| Servis | Svrha |
|---|---|
| OpenStreetMap (pločice karte) | podloga karte; © OpenStreetMap suradnici |
| TomTom Traffic API | incidenti i protok u prometu (preko vlastitog proxyja) |

Vlastiti JavaScript (`public/assets/js`) je bez dodatnih biblioteka (vanilla JS, `fetch` prema vlastitom API-ju).

## Integracija vanjskog servisa (TomTom Traffic)

- **Što se dohvaća:** incidenti u prometu za cijeli Zagreb (*Incident Details v5*: nesreće, radovi, zatvorene ceste, gužve) i brzina na šest glavnih prometnica (*Flow Segment Data v4*). Podaci se prikazuju na karti.
- **Kako:** preglednik zove samo vlastiti API `GET /api/v1/promet`; poslužitelj zove TomTom s ključem iz `.env` (ključ nikad ne ide u preglednik ni u git).
- **Timeout:** `TOMTOM_TIMEOUT` sekundi po zahtjevu.
- **Cache i limiti:** odgovor se čuva 60–120 s (`storage/cache`), osvježava ga samo jedan zahtjev istovremeno, a dnevno je najviše 80 osvježavanja (besplatni plan: 2.500 zahtjeva za incidente mjesečno). Korisniku se prikazuje vrijeme zadnjeg uspješnog dohvata.
- **Obrada kvarova:** servis nedostupan, timeout, HTTP pogreška ili odgovor neočekivane strukture → prikazuju se zadnji uspješno dohvaćeni podaci uz upozorenje ili, ako ih nema, odgovor `503`. Za demonstraciju: `GET /api/v1/promet?simulacija=nedostupan`, `?simulacija=neispravno` i `?simulacija=testni` (jasno označeni testni podaci).
- **Zastarjeli podaci:** dugotrajne zatvorenosti bez najavljenog kraja označene su kao moguće zastarjele; administrator ih može sakriti uz razlog.

Detalji odgovora: [docs/API.md – Promet](docs/API.md#promet-tomtom).

---

## Struktura projekta

```
public/            ulaz (index.php), CSS/JS/slike, uploads/ (javne slike vijesti, bez izvršavanja skripti)
src/Core/          router, prikaz, baza, sesija, CSRF, prijava, API odgovori, straničenje
src/Controllers/   kontroleri web stranica; Api/ – kontroleri REST API-ja
src/Services/      poslovna pravila (validacija, ovlasti, tok statusa, upload, TomTom)
src/Models/        SQL upiti (PDO, prepared statements)
views/             PHP predlošci
config/routes.php  sve rute i tko im smije pristupiti
database/          schema.sql, seed.sql, demo_slike.php
schemas/           JSON Schema za API
storage/           cache, log, slike prijava (izvan webroota; nije u gitu)
tests/             automatski testovi (bash + curl)
docs/              dokumentacija
```

## Dokumentacija

| Dokument | Sadržaj |
|---|---|
| [docs/UPUTE.md](docs/UPUTE.md) | uloge i upute za korištenje |
| [docs/API.md](docs/API.md) | REST API: krajnje točke, ovlasti, parametri, primjeri, pogreške, JSON Schema |
| [docs/ER.md](docs/ER.md) | ER dijagram, pravila brisanja, tok statusa prijave |
| [docs/TESTOVI.md](docs/TESTOVI.md) | testni scenariji, očekivani i dobiveni rezultati |
| [docs/PRIJEDLOG_PROJEKTA.md](docs/PRIJEDLOG_PROJEKTA.md) | prijedlog projekta |
| [docs/snimke/](docs/snimke/) | snimke zaslona ključnih funkcija |

## Sigurnost (ukratko)

Lozinke s `password_hash`; PDO prepared statements; validacija na poslužitelju; escaping izlaza; HTMLPurifier za HTML iz uređivača; CSRF token za svaki zahtjev koji mijenja podatke; novi ID sesije nakon prijave; deaktivacija prekida otvorene sesije; ograničenje broja neuspjelih prijava; provjera ovlasti nad svakim zapisom (vlastite vijesti, prijave, zadaci); upload s provjerom stvarne vrste i veličine, nasumičnim imenom i bez izvršavanja; slike prijava izvan webroota; tajne samo u `.env`; generičke poruke pogrešaka. Javna instalacija mora biti dostupna preko HTTPS-a.

## Testovi

Uz pokrenuti Apache i MySQL, u Git Bashu:
```
bash tests/sve.sh         # sve skripte redom, uz sažetak
bash tests/sigurnost.sh   # sigurnosni testovi (SQLi, XSS, CSRF, IDOR, upload, brute force...)
bash tests/cms.sh         # CMS: vijesti, kategorije, korisnici
bash tests/prijave.sh     # prijave problema
bash tests/zadaci.sh      # dodjela, kanban, tok statusa i provjera završetka
bash tests/api.sh         # REST API i JSON Schema
bash tests/karta.sh       # karta, radovi, lokacija prijava, TomTom proxy
```
Skripte rade nad lokalnom bazom i vraćaju je na početne testne podatke. Rezultati i tablica obaveznih scenarija: [docs/TESTOVI.md](docs/TESTOVI.md).

Na Windowsima testovi u kratkom vremenu otvore tisuće TCP veza; `tests/sve.sh` zato između skripti čeka da se oslobode portovi.

## Dodatne funkcionalnosti

Uz obavezne elemente: cache i ograničenje broja zahtjeva prema vanjskom servisu, ograničenje neuspjelih prijava (brute force), automatski testovi (više od 600 provjera, uključujući napade), povijest promjena statusa prijava (tko, kada, komentar), moderacija podataka vanjskog servisa uz zapis razloga, karta s odabirom lokacije.

## Korištenje AI alata

Tijekom razvoja korišten je **Claude Code** (Anthropic), uz pregled i ručno testiranje svake izmjene. Lozinke, API ključevi i stvarni osobni podaci nisu uneseni u AI alat (`.env` alat nije čitao; testni računi imaju izmišljene podatke).

| Faza | Za što |
|---|---|
| 1 | prijedlog strukture baze, SQL skripte, dokumentacija |
| 2 | struktura koda, router, autentifikacija, automatski testovi |
| 3 | javni dio, profil, stilovi, testovi, provjera licence grba |
| 4 | CMS (vijesti s uređivačem, kategorije, korisnici), sigurnosna analiza i testovi napada, zaštita od pogađanja lozinke |
| 5 | prijave problema (transakcija, slike izvan webroota), generator demo slika, testovi |
| 6 | kanban radnika, state machine statusa, dodjela i provjera završetka, testovi |
| 7 | REST API s JSON Schemom, dokumentacija API-ja, pretraga vijesti preko API-ja, testovi |
| 8 | karta (Leaflet), radovi, odabir lokacije, TomTom proxy s cacheom i obradom kvarova, testovi; provjera TomTom uvjeta i limita |
| 9 | završno testiranje, sigurnosni pregled, provjera instalacije, dokumentacija |

## Atribucije

Grb Grada Zagreba: Wikimedia Commons (javno vlasništvo; službeni simbol – aplikacija nije službena). Karta: © OpenStreetMap suradnici (ODbL). Podaci o prometu: © TomTom.
