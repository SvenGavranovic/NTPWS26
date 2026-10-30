# Objašnjenja odluka (za obranu projekta)

Dokument se dopunjuje nakon svake faze. Za svaku fazu navodi što je napravljeno, zašto je napravljeno tako, kako teku podaci, koje su sigurnosne odluke donesene i koja se pitanja mogu očekivati na obrani.

---

## Faza 1 – Prijedlog projekta, ER dijagram, shema i testni podaci

### Što je napravljeno

| Datoteka | Sadržaj |
|---|---|
| `docs/PRIJEDLOG_PROJEKTA.md` | Prijedlog za nastavnika (naziv, problem, korisnici, funkcionalnosti, tehnologije, vanjski servis) |
| `docs/ER.md` | ER dijagram (Mermaid), pravila brisanja, dijagram statusa prijave |
| `database/schema.sql` | 11 tablica, strani ključevi, indeksi, CHECK ograničenja |
| `database/seed.sql` | Testni podaci za sve uloge i sve statuse |
| `.gitignore`, `.env.example` | Tajne izvan gita, primjer konfiguracije |

### Opće odluke

**1. Nazivi na hrvatskom, snake_case, bez dijakritika.**
`kreirano_u`, `azurirano_u`, `cetvrt_id`. CLAUDE.md traži dosljednost. Kad bi dio stupaca bio na engleskom (`created_at`), a dio na hrvatskom, shema bi bila nedosljedna. Dijakritici u nazivima stupaca rade, ali stvaraju probleme s kodiranjem u alatima i u PHP-u.

**2. `utf8mb4` + `utf8mb4_croatian_ci`.**
- `utf8mb4` je pravi UTF-8 (podržava sve znakove, i emoji). Stari MySQL `utf8` podržava samo 3 bajta.
- `croatian` znači da sortiranje ispravno slaže č, ć, dž, đ, lj, nj, š, ž, pa „Črnomerec” dolazi nakon „Cvjetnog”, a ne na kraj popisa.
- `_ci` (case-insensitive) znači da je usporedba neosjetljiva na velika i mala slova. Zato `UNIQUE` na e-mailu odbija i `Ana@x.hr` ako postoji `ana@x.hr`. U PHP-u ćemo e-mail ipak spremati malim slovima (dvostruka zaštita).

**3. InnoDB.** Jedini MariaDB pogon koji podržava strane ključeve i transakcije, a oba su obavezna prema uputama.

**4. `DATETIME`, a ne `TIMESTAMP`.** `TIMESTAMP` ima ograničenje do 2038. i automatski pretvara vremenske zone. `DATETIME` sprema točno ono što upišemo. Vremensku zonu (`Europe/Zagreb`) postavljamo u PHP-u.

**5. `UNSIGNED` cijeli brojevi i različite veličine.** ID ne može biti negativan. Za šifrarnike s malo redaka (uloge, četvrti) dovoljan je `TINYINT` (do 255), a za prijave i vijesti `INT`. Tip stranog ključa mora biti **isti** kao tip primarnog ključa na koji pokazuje, inače MariaDB odbija vezu.

**6. ENUM za fiksne popise, zasebna tablica za popise koji se mogu mijenjati.**
- `status`, `hitnost`, `tip` slike: vrijednosti su dio poslovne logike (state machine u kodu). Nova vrijednost ionako traži promjenu koda, pa je ENUM prikladan i sprječava neispravnu vrijednost.
- `gradske_cetvrti`, `vrste_problema`, `kategorije_vijesti`, `uloge`: to su podaci koji se prikazuju u padajućim izbornicima i koje administrator može mijenjati, pa su u zasebnim tablicama.
- Kompromis: vrste radova su ENUM jer ih nitko ne uređuje kroz sučelje. Kad bi trebalo, lako ih je prebaciti u tablicu.

**7. Pravila brisanja (ključno pitanje na obrani).** Puna tablica je u `docs/ER.md`. Ukratko:
- **RESTRICT** prema kategorijama: kategorija s vijestima se ne može obrisati. Baza to sama odbija (greška 1451), a PHP će korisniku prikazati razumljivu poruku.
- **RESTRICT** prema korisnicima: korisnik se nikad ne briše nego **deaktivira** (`aktivan = 0`). Inače bismo izgubili tko je napisao vijest ili tko je odobrio zadatak.
- **CASCADE** od prijave prema slikama, zadatku i povijesti: ti zapisi su „dijelovi” prijave i bez nje nemaju smisla.

**8. CHECK ograničenja** (MariaDB 10.2+ ih stvarno provodi):
- objavljena vijest mora imati `datum_objave`;
- koordinate su ili obje upisane ili obje prazne i moraju biti približno u Zagrebu;
- kraj radova ne smije biti prije početka.

Ovo je **zadnja linija obrane**. Glavna validacija je u PHP-u, gdje korisnik dobiva razumljivu poruku. CHECK hvata greške u našem kodu. Granice koordinata u bazi namjerno su šire (45.60–46.00, 15.75–16.30), a preciznu provjeru radi PHP.

**9. Indeksi.** Strani ključevi u InnoDB-u automatski dobivaju indeks. Ručno su dodani složeni indeksi za najčešće upite:
- `vijesti(status, datum_objave)`: javni popis `WHERE status='objavljeno' ORDER BY datum_objave DESC`;
- `prijave(status, hitnost)`: kanban i administratorski popis;
- `radovi(pocetak, kraj)`: filtar „aktivni radovi”.

### Odluke po tablicama

**`uloge` + `korisnici.uloga_id` (jedna uloga po korisniku).**
Upute govore o „promjeni uloge”, što odgovara jednoj ulozi po korisniku. Veza više-na-više (tablica `korisnik_uloge`) bila bi složenija bez stvarne koristi. Kod provjerava `uloge.kod` (`'admin'`), a ne `id = 4`, jer je kod čitljiviji i ne ovisi o redoslijedu unosa.

**`korisnici.aktivan` i prekid sesija.**
Nije potreban poseban stupac za sesije. U fazi 2 aplikacija će pri **svakom zahtjevu** prijavljenog korisnika iz baze provjeriti `aktivan` i ulogu. Kad administrator deaktivira račun, već sljedeći klik tog korisnika ga odjavljuje. Isto vrijedi za promjenu uloge: nova uloga vrijedi odmah.

**`lozinka_hash VARCHAR(255)`.** `password_hash()` danas vraća bcrypt od 60 znakova. PHP dokumentacija preporučuje 255 jer se zadani algoritam u budućnosti može promijeniti (npr. Argon2), a takav hash je duži.

**`vijesti`.**
- Samo dva statusa (`nacrt`, `objavljeno`). „Povlačenje” je vraćanje u `nacrt`. `datum_objave` ostaje sačuvan, pa se vidi da je vijest nekad bila objavljena (vijest 9 u seedu).
- `sadrzaj` je `MEDIUMTEXT` (do 16 MB) jer HTML iz uređivača može biti dugačak. U bazu se sprema **već pročišćen** HTML (HTMLPurifier pri spremanju), a ne sirovi unos.
- `slika` sadrži samo ime datoteke. Sama slika je na disku jer bi spremanje slika u bazu (BLOB) znatno povećalo bazu i usporilo sigurnosne kopije.
- Jedna slika po vijesti jer upute traže „prijenos, zamjenu i uklanjanje slike” (jednina).

**`prijave`: gdje je status?**
Status je **samo u tablici `prijave`**, a ne i u `zadaci`. Prijava postoji (`PRIJAVLJENO`) prije nego što zadatak uopće nastane. Da je status zapisan na dva mjesta, mogao bi se razići (npr. prijava kaže `ZAVRSENO`, a zadatak `U_RADU`). Pravilo: **jedan podatak, jedno mjesto (normalizacija).**

**`prijave.naslov`.** CLAUDE.md spominje samo opis, ali kartica na kanbanu treba kratki naslov. Dugački opis ne stane na karticu.

**`prijave.opis` je običan tekst, ne HTML.** Građanin ne treba formatiranje. Manji je i rizik od XSS-a jer se pri ispisu sve escapea s `htmlspecialchars()`.

**Redoslijed ENUM-a hitnosti** (`niska, srednja, visoka, kriticna`) je namjeran. MariaDB sortira ENUM po rednom broju vrijednosti, pa `ORDER BY hitnost DESC` daje kritične na vrhu.

**`zadaci` (1:1 s prijavom).**
- `UNIQUE(prijava_id)`: prijava ima najviše jedan zadatak. Pri preraspodjeli admin mijenja `radnik_id`, a trag ostaje u povijesti.
- `dodijelio_id` bilježi koji je administrator dodijelio zadatak.
- `napomena_radnika` je obavezna pri slanju na provjeru. To provjerava PHP jer CHECK ne može pratiti promjenu statusa u drugoj tablici.
- Zašto zasebna tablica, a ne stupac `radnik_id` u prijavama: odvaja podatke građanina od podataka o izvođenju radova, a kanban radnika postaje jednostavan upit `WHERE radnik_id = ?`.

**`prijava_slike` (tip `prije`/`poslije`).**
Jedna tablica za obje vrste slika, s razlikom u stupcu `tip`. Upit „ima li zadatak sliku poslije” glasi `WHERE prijava_id=? AND tip='poslije'`, za što postoji indeks. Spremaju se i `mime_tip` i `velicina` kako bi se vidjelo što je provjereno pri učitavanju. `datoteka` je `UNIQUE` (nasumično ime se ne smije ponoviti).

**`povijest_statusa` (revizijski trag).**
Retci se samo dodaju i nikad se ne mijenjaju. `iz_statusa` je NULL u prvom zapisu (nastanak prijave). Iz ove tablice se može rekonstruirati cijeli tijek: tko je, kada i zašto mijenjao status.

**`radovi`.** Koordinate su obavezne (radovi se uvijek prikazuju na karti). `kraj` može biti NULL (kraj još nije poznat). `utjecaj_na_promet` povezuje radove s prometnom temom aplikacije.

### Tok statusa i transakcije (priprema za fazu 6)

Dopušteni prijelazi (provjerava ih PHP, a ne baza):

| Iz | U | Tko | Uvjet |
|---|---|---|---|
| — | PRIJAVLJENO | korisnik | slika „prije”, četvrt, opis |
| PRIJAVLJENO | DODIJELJENO | admin | odabran radnik |
| DODIJELJENO | U_RADU | dodijeljeni radnik | |
| U_RADU | CEKA_PROVJERU | dodijeljeni radnik | slika „poslije” + napomena |
| CEKA_PROVJERU | ZAVRSENO | admin | |
| CEKA_PROVJERU | VRACENO | admin | obavezan komentar |
| VRACENO | U_RADU | dodijeljeni radnik | |

Primjer gdje je **transakcija obavezna**: dodjela zadatka mijenja tri tablice (`INSERT zadaci`, `UPDATE prijave.status`, `INSERT povijest_statusa`). Ako jedan upit ne uspije, ne smije ostati prijava sa statusom `DODIJELJENO` bez zadatka. Zato se izvodi `beginTransaction()` → tri upita → `commit()`, a kod greške `rollBack()`.

### Testni podaci – što pokrivaju

| Scenarij iz uputa | Podatak u seedu |
|---|---|
| sve uloge | 1 admin, 2 urednika, 2 radnika, 3 korisnika |
| deaktivirani račun | `neaktivna@...` (`aktivan = 0`) |
| urednik mijenja tuđu vijest | Lea (vijesti 2, 3, 6, 7) i Tin (vijesti 4, 5, 8) |
| javni dohvat neobjavljene vijesti | vijesti 7 i 8 (nacrt), 9 (povučena) |
| brisanje kategorije | „Arhiva” je prazna (smije se obrisati), ostale nisu |
| svi statusi prijava | prijave 1–10 pokrivaju svih 6 statusa i 4 hitnosti |
| prijava bez koordinata | prijava 2 |
| radovi | aktivni, budući, završeni, bez poznatog kraja |

- **Bez stvarnih osobnih podataka:** imena su izmišljena, a e-mail adrese koriste domenu `.test`, koja je rezervirana (RFC 2606) i ne može postojati na internetu.
- **Lozinke:** u `seed.sql` su samo bcrypt hashevi generirani s `password_hash()`. Svi računi imaju istu testnu lozinku, ali svaki hash je drukčiji jer bcrypt dodaje nasumičnu sol. Lozinka u čistom tekstu je samo u `database/testni_racuni.local.md`, koji je u `.gitignore`. Predaje se kroz LMS, kako upute traže.
- **Eksplicitni ID-jevi** u seedu služe čitljivosti (vidi se da prijava 6 pripada korisniku 7). AUTO_INCREMENT nakon toga nastavlja od najvećeg ID-a.
- **`START TRANSACTION … COMMIT`**: seed se upisuje u cijelosti ili se ništa ne upiše.
- Slike u seedu (`demo-p01-prije.jpg` …) za sada su samo zapisi u bazi. Datoteke se dodaju u fazi 5.

### Kako pokrenuti

1. U XAMPP-u pokrenuti MySQL.
2. phpMyAdmin → kartica **Import** → `database/schema.sql`, zatim `database/seed.sql`.
   Ili iz naredbenog retka:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root < database\seed.sql
   ```
3. `schema.sql` **briše i ponovno stvara tablice**. Koristi se samo za razvoj.

### Moguća pitanja na obrani

- *Zašto se korisnik ne briše?* Zbog RESTRICT pravila i revizijskog traga. Deaktivacija čuva povijest i sprječava pristup.
- *Što se dogodi ako obrišem kategoriju s vijestima?* Baza vraća grešku 1451 (strani ključ). PHP je hvata i prikazuje „Kategorija sadrži vijesti i ne može se obrisati.”
- *Zašto status nije i u tablici `zadaci`?* Da ne bude zapisan na dva mjesta (normalizacija, jedan izvor istine).
- *Kako baza sprječava dva računa s istim e-mailom?* `UNIQUE` indeks i `_ci` collation (neosjetljiv na velika i mala slova).
- *Zašto `VARCHAR(255)` za hash?* Preporuka PHP-a, jer budući algoritmi daju duže hasheve.
- *Gdje se provjerava da radnik ne može staviti ZAVRSENO?* U PHP-u (state machine). Baza samo zna koje vrijednosti statusa postoje, a ne tko ih smije postaviti.
- *Zašto CHECK ako PHP već validira?* Dubinska obrana: ako u kodu postoji greška, baza i dalje odbija nemoguće podatke.
- *Kad se koristi transakcija?* Kad jedna radnja mijenja više tablica koje moraju ostati usklađene (dodjela zadatka, slanje na provjeru sa slikom).

### Korištenje AI alata

U fazi 1 korišten je Claude Code (Anthropic) za izradu prijedloga strukture baze, SQL skripti i dokumentacije. Student je pregledao sve odluke i provjerio skripte uvozom u lokalnu bazu.

---

## Faza 2 – Struktura koda, router, registracija, prijava, uloge, CSRF

### Struktura

```
.htaccess                 sve zahtjeve šalje u public/ (ništa izvan public/ nije dostupno)
public/
  .htaccess               sve što nije postojeća datoteka → index.php
  index.php               FRONT CONTROLLER – jedina PHP datoteka dostupna iz preglednika
  assets/css/app.css
config/routes.php         popis svih ruta i tko im smije pristupiti
src/
  helpers.php             e(), url(), redirect(), post(), csrf_field()...
  Core/                   "mini framework": Router, View, Database, Session, Csrf, Auth
  Controllers/            prima zahtjev → poziva servis → bira odgovor
  Services/AuthService    poslovna logika (validacija, hashiranje, pravila)
  Models/Korisnik         SAMO SQL upiti nad tablicom korisnici
views/                    HTML predlošci (layout + pogledi)
storage/logs/app.log      zapisnik pogrešaka (nije u gitu)
```

**Zašto ovako?** Upute traže da se odvoje konfiguracija (`.env`, `config/`), rad s bazom (`Models`), poslovna logika (`Services`) i prikaz (`views`). Kontroler ih samo povezuje.

**Pravilo imenovanja:** tehnički pojmovi su na engleskom (Router, Controller, `loginForm`, `render`), a pojmovi iz domene na hrvatskom (Korisnik, uloge, `aktivan`, `/racun/prijava`). Tako se kod čita kao i svaki PHP projekt, a nazivi odgovaraju bazi.

### Tok podataka: prijava korisnika (primjer za obranu)

1. Preglednik šalje `POST /zagreb-prijave/racun/prijava` s poljima `email`, `lozinka` i `_csrf`.
2. **`.htaccess`** u korijenu prepisuje putanju u `public/racun/prijava`. `public/.htaccess` vidi da ta datoteka ne postoji i šalje zahtjev u `public/index.php`.
3. **`index.php`**:
   - učitava Composer autoloader i `.env`;
   - postavlja rukovatelje pogreškama i sigurnosna zaglavlja;
   - pokreće sesiju (`Session::start`);
   - učitava `config/routes.php` i poziva `Router::dispatch('POST', '/racun/prijava')`.
4. **`Router`** pronalazi rutu i prije kontrolera provjerava:
   - je li CSRF token ispravan (inače 403);
   - je li ruta označena kao `gost` (prijavljeni se preusmjeravaju na početnu).
5. **`AuthController::login`** čita polja preko `post()` i poziva `AuthService::attemptLogin`.
6. **`AuthService`**:
   - pretvara e-mail u mala slova;
   - preko **`Korisnik::findForLogin`** dohvaća korisnika: `SELECT ... WHERE email = ?` (prepared statement);
   - poziva `password_verify` (i kad korisnik ne postoji, vidi niže);
   - provjerava je li račun aktivan;
   - po potrebi obnavlja hash lozinke i bilježi zadnju prijavu.
7. Ako prijava ne uspije, kontroler prikazuje obrazac s generičkom porukom (status 401).
   Ako uspije, **`Auth::login`** poziva `session_regenerate_id(true)`, sprema samo `korisnik_id` u sesiju i stvara novi CSRF token. Zatim preusmjerava (303) na `/admin` ili na početnu.
8. Pri sljedećem zahtjevu **`Auth::user()`** iz baze ponovno čita korisnika, njegovu ulogu i polje `aktivan`.

### Kako radi router

- Rute se definiraju u `config/routes.php`: `$router->get('/admin', [AdminController::class, 'index'], ['uloge' => ['admin']]);`
- `{id}` u putanji postaje regex `(?P<id>\d+)`, dakle prihvaćaju se samo brojevi. Vrijednost se kontroleru predaje kao cijeli broj.
- Ako putanja postoji, ali za drugu metodu, odgovor je **405** sa zaglavljem `Allow`. Ako putanja ne postoji, odgovor je **404**.
- **Zaštite su opcije rute, a ne kod u svakom kontroleru.** Tako se ne mogu zaboraviti, a na jednom mjestu (`routes.php`) vidi se tko smije što.
  - `auth`: neprijavljeni korisnik se preusmjerava na prijavu;
  - `uloge`: kriva uloga dobiva **403**;
  - `gost`: prijavljeni korisnik se preusmjerava na početnu.
- **CSRF se provjerava za SVAKI zahtjev koji nije GET**, automatski. GET zahtjevi zato nikad ne smiju mijenjati podatke.

### Sigurnosne odluke

| Prijetnja | Zaštita | Gdje |
|---|---|---|
| Pristup `.env`, `src/`, `database/` iz preglednika | Sve se prepisuje u `public/`. Testirano: `/.env` vraća 404 | `.htaccess` |
| SQL injection | Prepared statements, `ATTR_EMULATE_PREPARES = false` (pravi prepared statements na strani baze) | `Database`, `Korisnik` |
| Krađa lozinki iz baze | `password_hash` (bcrypt, sol), `password_needs_rehash` | `AuthService` |
| bcrypt odsijeca lozinku nakon 72 bajta | Validacija odbija duže lozinke | `AuthService::validatePassword` |
| Otkrivanje postojećih e-mailova pri prijavi | Ista poruka za nepostojeći e-mail i pogrešnu lozinku. `password_verify` se izvodi i za nepostojeći e-mail (lažni hash), pa vrijeme odgovora ne otkriva ništa | `AuthService::attemptLogin` |
| Session fixation | `session_regenerate_id(true)` nakon prijave, `use_strict_mode`, ID samo u kolačiću | `Auth::login`, `Session::start` |
| Krađa kolačića sesije preko XSS-a | `HttpOnly` kolačić | `Session::start` |
| CSRF | Token u sesiji (`random_bytes(32)`), skriveno polje u svakom obrascu, `hash_equals`, automatska provjera u routeru, kolačić `SameSite=Lax` | `Csrf`, `Router` |
| CSRF odjava (tuđa stranica odjavljuje korisnika) | Odjava je POST s tokenom, a ne poveznica | `layout.php`, `routes.php` |
| XSS | Svaki ispis ide kroz `e()` (`htmlspecialchars` s `ENT_QUOTES`, UTF-8). Validacija imena dopušta samo slova | `helpers.php`, pogledi |
| Korisnik si dodjeljuje ulogu admin | Uloga je fiksna konstanta u servisu. Kontroler čita samo poznata polja, pa se podmetnuto `uloga=admin` ignorira (testirano) | `AuthService::OSNOVNA_ULOGA` |
| Deaktivirani korisnik ostaje prijavljen | Uloga i `aktivan` čitaju se iz baze pri svakom zahtjevu. Deaktiviranom korisniku sesija se uništava | `Auth::user` |
| Skrivanje gumba kao jedina zaštita | Router vraća 403 i kad netko ručno upiše `/admin` | `Router::passesGuards` |
| Curenje SQL-a i putanja u porukama | `display_errors` je isključen kad `APP_DEBUG=false`. Korisnik vidi generičku poruku, a detalji idu u `storage/logs/app.log` | `index.php` |
| Clickjacking i MIME sniffing | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` | `index.php` |
| Izmijenjen Bootstrap na CDN-u | SRI hash (`integrity`) izračunat iz stvarnih datoteka | `layout.php` |
| Slanje niza umjesto teksta (`email[]=x`) | `post()` vraća prazan string, pa korisnik dobiva validacijsku pogrešku umjesto greške 500 | `helpers.php` |
| Dvije istovremene registracije s istim e-mailom | `UNIQUE` u bazi. Greška 23000 se hvata i prikazuje kao „e-mail već postoji” | `AuthService::register` |

**Svjesno odgođeno ili izostavljeno:**
- ograničenje broja pokušaja prijave (brute force) moguće je dodati kao nadogradnju;
- isticanje sesije zbog neaktivnosti;
- `Strict-Transport-Security` (HSTS) ima smisla tek na javnoj HTTPS instalaciji.

### Statusni kodovi u HTML dijelu

| Situacija | Kod |
|---|---|
| Pogrešna lozinka | 401 (obrazac se ponovno prikazuje) |
| Neispravni podaci pri registraciji | 422 (obrazac s porukama uz polja) |
| Uspješan POST | 303 + preusmjeravanje (uzorak Post/Redirect/Get, pa osvježavanje stranice ne šalje obrazac ponovno) |
| Kriva uloga / neispravan CSRF token | 403 |
| Nepostojeća stranica / kriva metoda | 404 / 405 |

### Provedeni testovi (cURL, automatski)

| Scenarij | Očekivano | Dobiveno |
|---|---|---|
| `GET /.env`, `/src/helpers.php`, `/composer.json` | 404 | 404 |
| `/admin` bez prijave | preusmjeravanje na prijavu | 303 → `/racun/prijava` |
| `GET /racun/odjava` | 405 | 405 |
| POST bez CSRF tokena | 403 | 403 |
| Prijava s pogrešnom lozinkom / nepostojećim e-mailom | 401, ista poruka | 401, ista poruka |
| Prijava deaktiviranog računa | 401, poruka o deaktivaciji | 401 |
| Prijava admina s e-mailom VELIKIM slovima | uspjeh | 303 → `/admin` |
| Korisnik i urednik otvaraju `/admin` | 403 | 403 |
| Deaktivacija dok je korisnik prijavljen | odjava pri sljedećem kliku | odjavljen, poruka prikazana |
| Registracija: prazna / postojeći e-mail / slaba lozinka / `email[]` / `<script>` u imenu | 422 | 422, skripta escapeana |
| Registracija s podmetnutim `uloga=admin` | uloga `korisnik` | `korisnik` |
| Registracija s „đ” u prezimenu | ispravno spremljeno (UTF-8) | `C4 91` u bazi |
| Odjava, zatim `/admin` | traži prijavu | 303 → `/racun/prijava` |

### Moguća pitanja na obrani

- *Što je front controller i zašto?* Jedna ulazna točka (`public/index.php`) za sve zahtjeve. Konfiguracija, sesija, sigurnosna zaglavlja i provjere ovlasti zato se izvode na jednom mjestu, za svaki zahtjev.
- *Zašto je u sesiji samo ID, a ne cijeli korisnik?* Da bi promjena uloge ili deaktivacija vrijedile odmah. Podatak u sesiji bi bio zastario.
- *Što radi `session_regenerate_id(true)`?* Daje novi ID sesije i briše stari. Ako je napadač unaprijed podmetnuo ID (session fixation), nakon prijave on više ne vrijedi.
- *Zašto CSRF ako postoji `SameSite=Lax`?* Dubinska obrana. Stariji preglednici ne poštuju SameSite, a upute izričito traže CSRF zaštitu.
- *Zašto je odjava POST?* GET ne smije mijenjati stanje. Odjavu preko GET-a mogla bi pokrenuti bilo koja slika `<img src=".../odjava">` na tuđoj stranici.
- *Zašto ista poruka za krivi e-mail i krivu lozinku?* Da napadač ne može doznati koji su e-mailovi registrirani. Iz istog razloga postoji i lažni hash: vrijeme odgovora je jednako u oba slučaja.
- *Zašto je registracija preko 303 preusmjeravanja?* Uzorak Post/Redirect/Get: osvježavanje stranice ne šalje obrazac ponovno.
- *Kako PDO štiti od SQL injectiona?* Upit s `?` se šalje bazi odvojeno od podataka. Baza podatke nikad ne izvršava kao SQL.
- *Zašto `htmlspecialchars` s `ENT_QUOTES`?* Escapea i navodnike, pa je siguran i unutar HTML atributa (`value="..."`).

### Korištenje AI alata

U fazi 2 Claude Code je korišten za izradu strukture koda, routera, autentifikacije i automatskih cURL testova. Student je pregledao kod i testirao prijavu i registraciju u pregledniku.

---

## Faza 3 – Javni dio: početna, vijesti, filtriranje, pretraga, straničenje, profil

### Što je napravljeno

| Ruta | Što prikazuje | Tko |
|---|---|---|
| `GET /` | Početna: uvod, statistika prijava, „Kako funkcionira”, 3 najnovije vijesti | svi |
| `GET /vijesti?q=&kategorija=&stranica=` | Popis objavljenih vijesti s pretragom, filtrom i straničenjem | svi |
| `GET /vijesti/{id}` | Detalj objavljene vijesti | svi |
| `GET /o-projektu` | Opis projekta, uloge, tehnologije, napomena o AI alatima | svi |
| `GET /racun/profil` | Podaci o korisniku, obrasci za ime i lozinku | prijavljeni |
| `POST /racun/profil` | Promjena imena i prezimena | prijavljeni |
| `POST /racun/lozinka` | Promjena lozinke | prijavljeni |

Novi dijelovi koda:
- **modeli:** `Vijest`, `Kategorija`, `Prijava` (za sada samo statistika);
- **kontroleri:** `NewsController`, `ProfileController`;
- **servis:** `ProfileService`;
- **jezgra:** `Paginator`;
- **pomoćne funkcije:** `get()`, `get_int()`, `datum()`, `nav_link()`.

### Najvažnije pravilo: neobjavljene vijesti nikad ne smiju procuriti

Svi javni upiti u modelu `Vijest` prolaze kroz privatnu metodu `publicWhere()`, koja **uvijek** dodaje `v.status = 'objavljeno'`. Kontroler zato ne može „zaboraviti” uvjet, jer ga ne piše sam. Tako je pokriveno:
- popis i brojač (nacrt se ne broji ni u broju vijesti po kategoriji);
- pretraga (riječ iz nacrta ne vraća nacrt);
- izravan URL `/vijesti/7` (nacrt) i `/vijesti/9` (povučena vijest) vraća **404, isto kao nepostojeća vijest**. Poruka „nemate pravo” bi otkrila da vijest s tim ID-jem postoji.

### Tok podataka: `GET /vijesti?q=promet&kategorija=2&stranica=1`

1. `index.php` → `Router` → `NewsController::index` (javna ruta, GET, bez CSRF-a).
2. Kontroler čita parametre preko `get()` i `get_int()`:
   - `q` je skraćen na 100 znakova;
   - `kategorija=abc` postaje `null` (filtar se ignorira);
   - niz (`q[]=x`) postaje prazan string.
3. `Vijest::countPublished()`: `SELECT COUNT(*) ... WHERE status='objavljeno' AND kategorija_id = :kategorija AND (naslov LIKE :q1 OR ...)`.
4. `Paginator` iz ukupnog broja izračunava broj stranica, ispravlja nemoguću stranicu (`stranica=999` → zadnja) i daje `OFFSET`.
5. `Vijest::findPublished()` s `LIMIT :limit OFFSET :offset`, gdje su oba parametra vezana kao `PDO::PARAM_INT`.
6. Pogled `vijesti/index.php` ispisuje kartice (sve kroz `e()`), filtre i straničenje. Poveznice straničenja čuvaju aktivne filtre.

### Odluke i obrazloženja

- **Pretraga i filtri su GET, a ne POST.** Rezultat ima vlastiti URL, koji se može podijeliti, spremiti ili otvoriti gumbom Natrag. GET ništa ne mijenja, pa ne treba CSRF token.
- **Escaping zamjenskih znakova u LIKE.**
  - `%` i `_` u LIKE znače „bilo što” i „bilo koji znak”. Bez escapinga bi pretraga `%` vratila sve vijesti.
  - `addcslashes($q, '%_\\')` ih pretvara u obične znakove. Testirano: `q=%` vraća 0 rezultata.
- **`:q1`, `:q2`, `:q3` umjesto jednog `:q`.** S pravim prepared statementima (`EMULATE_PREPARES = false`) isti imenovani parametar ne smije se pojaviti dvaput u istom upitu.
- **WHERE se gradi iz dijelova, ali sigurno.** U SQL se spajaju samo fiksni dijelovi teksta koje je napisao programer. Korisnikove vrijednosti idu isključivo kroz `bindValue`/`execute`.
- **Pretraga ne razlikuje velika i mala slova** zahvaljujući collationu `utf8mb4_croatian_ci` iz faze 1: `PROMETA` nalazi „prometa”.
- **4 vijesti po stranici.** Broj je mali namjerno, da se straničenje vidi već sa 6 objavljenih vijesti iz seeda. Mijenja se jednom konstantom (`NewsController::PO_STRANICI`).
- **Nemoguća stranica se ispravlja, a ne javlja grešku.** `stranica=999` prikazuje zadnju stranicu, a `stranica=abc` prvu.
- **Prazni rezultati** imaju vlastitu poruku: drukčiju za pretragu bez rezultata, a drukčiju za praznu kategoriju. Uz poruku je i gumb za povratak (zahtjev iz uputa).
- **Sadržaj vijesti se ispisuje bez `e()`**, jer je to HTML iz uređivača. Siguran je zato što ga HTMLPurifier čisti **pri spremanju** u CMS-u (faza 4). U bazu tako nikad ne ulazi `<script>` ni `onerror=`. Sve ostalo (naslov, sažetak, autor, kategorija) ide kroz `e()`.
- **Statistika na početnoj** prikazuje samo zbirne brojeve (`COUNT`, `SUM`), bez podataka o pojedinoj prijavi ili korisniku.

### Profil i promjena lozinke

- **ID korisnika uzima se iz sesije, nikad iz obrasca.** Testirano: podmetnuti `id=1` i `email=...` u POST-u se ignoriraju, a mijenja se samo vlastito ime.
- **E-mail i uloga se ne mogu mijenjati na profilu.** Promjena e-maila zahtijevala bi potvrdu nove adrese, a ulogu mijenja administrator (faza 4).
- **Za promjenu lozinke traži se trenutna lozinka.** Netko tko sjedne za tuđe otključano računalo ne može tako preuzeti račun.
- **Nova lozinka mora biti različita od stare** i mora zadovoljiti ista pravila kao kod registracije (`AuthService::validatePassword`, jedno mjesto za pravila).
- **Nakon promjene lozinke poziva se `session_regenerate_id(true)`.**
- **Poznato ograničenje:** ostale otvorene sesije istog korisnika (npr. na drugom uređaju) ostaju aktivne do odjave. Rješenje bi bio stupac `lozinka_promijenjena_u` i usporedba s vremenom prijave spremljenim u sesiji. To je moguća nadogradnja.
- **Provjera imena je izdvojena u `AuthService::validateName()`** i koristi se i pri registraciji i na profilu, bez ponavljanja koda.

### Izgled, grb i favicon

- **Grb Grada Zagreba** (SVG) preuzet je s Wikimedia Commons:
  - licenca je **javno vlasništvo (Public domain)**, uz oznaku **„insignia”**: službeni simboli se ne smiju koristiti tako da sugeriraju službeni status;
  - prije uključivanja datoteka je pregledana: nema `<script>` ni vanjskih poveznica;
  - spremljena je lokalno u `public/assets/img/grb-zagreb.svg`, umjesto da se učitava s tuđeg poslužitelja;
  - dodan joj je `viewBox` da se ispravno smanjuje.
- **Gdje se grb koristi:** u zaglavlju lijevo od naziva, kao **favicon** (`<link rel="icon" type="image/svg+xml">`), u heroju početne stranice i u podnožju.
- **Napomena o neslužbenom statusu** stoji u podnožju svake stranice i na stranici „O projektu”. Uz službeni grb aplikacija ne smije izgledati kao službena aplikacija Grada.
- **Boje:** glavna boja je plava iz grba (`#0277ba`). Bootstrap gumbi, straničenje i aktivna kategorija prilagođeni su preko CSS varijabli u `app.css`.
- **Pristupačnost:**
  - poveznica „Preskoči na sadržaj”;
  - `aria-current` na aktivnoj stavci izbornika i stranici;
  - `alt=""` na ukrasnim slikama grba (čitač zaslona ih preskače), a opisni `alt` u heroju;
  - `prefers-reduced-motion` isključuje animaciju kartica.

### Provedeni testovi (cURL, automatski) – svi prošli

| Scenarij | Očekivano | Dobiveno |
|---|---|---|
| Početna: favicon, grb, napomena, statistika 10/5/2, 3 najnovije vijesti bez nacrta | sve prisutno | ✔ |
| `/assets/img/grb-zagreb.svg` | 200, `image/svg+xml` | ✔ |
| `/vijesti` | 6 vijesti, 4 na str. 1, 2 na str. 2, bez nacrta i povučene | ✔ |
| `stranica=999` / `stranica=abc` | zadnja / prva stranica | ✔ |
| `kategorija=2` (Promet) | 1 vijest (nacrt 7 izostavljen) | ✔ |
| `kategorija=6` (Arhiva) | poruka o praznoj kategoriji | ✔ |
| `q=prometa`, `q=PROMETA` | 1 (bez nacrta) | ✔ |
| `q=NACRT`, `q=%`, `q=_` | 0 rezultata | ✔ |
| `q=xyznema` | poruka „Nema pronađenih vijesti” | ✔ |
| `q=<script>…` | escapeano | ✔ |
| `q[]=x` | 200, bez greške | ✔ |
| `/vijesti/7` (nacrt), `/vijesti/9` (povučena), `/vijesti/999` | 404 | ✔ |
| Profil bez prijave | preusmjeravanje na prijavu | ✔ |
| Profil: POST bez CSRF / neispravno ime | 403 / 422 | ✔ |
| Profil s podmetnutim `id=1` i `email` | mijenja se samo vlastito ime, e-mail ne | ✔ |
| Lozinka: kriva trenutna / ista kao stara / potvrda ne odgovara | 422 | ✔ |
| Lozinka: ispravna promjena | stara lozinka više ne radi, nova radi | ✔ |

### Moguća pitanja na obrani

- *Kako ste osigurali da nacrt ne procuri?* Uvjet `status='objavljeno'` je u jednoj privatnoj metodi modela kroz koju prolaze svi javni upiti. Izravan URL nacrta vraća 404, isto kao nepostojeći zapis.
- *Zašto 404, a ne 403, za nacrt?* 403 bi potvrdio da vijest postoji. Za posjetitelja nacrt „ne postoji”.
- *Što je OFFSET i kako se računa?* `(stranica - 1) × po_stranici`. Za stranicu 2 uz 4 po stranici to je 4, pa se preskaču prve 4 vijesti.
- *Zašto pretraga escapea `%` i `_`?* To su zamjenski znakovi u LIKE. Bez escapinga korisnik bi mogao izvoditi „wildcard” upite koje nismo predvidjeli.
- *Zašto se sadržaj vijesti ispisuje bez escapinga?* Jer je to formatirani HTML. Sigurnost osigurava HTMLPurifier pri spremanju, pa u bazu ulaze samo dopuštene oznake.
- *Može li korisnik promijeniti tuđi profil?* Ne. ID se čita iz sesije, a ne iz zahtjeva. To je testirano podmetanjem `id=1`.
- *Smijete li koristiti grb Zagreba?* Grb je u javnom vlasništvu, ali je službeni simbol. Zato aplikacija jasno navodi da nije službena i ne predstavlja se kao Grad.

### Korištenje AI alata

U fazi 3 Claude Code je korišten za izradu javnog dijela, profila, stilova i automatskih testova te za provjeru licence grba. Student je pregledao kod i izgled stranica u pregledniku.
