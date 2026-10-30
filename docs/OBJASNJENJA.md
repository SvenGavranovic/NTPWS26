# Zagreb Prijave – priručnik

Kako aplikacija radi, zašto je napravljena baš tako i kako u nju dodati nešto novo.

Dokument je organiziran **po temama**, a ne po redoslijedu razvoja. Svaka tema ima:
- objašnjenje kako stvar radi;
- odluke s obrazloženjem;
- **recepte**: kratke postupke korak po korak („kako dodati novu stranicu samo za admina”).

Na kraju su **česta pitanja**, **dnevnik faza** (što je dodano kada) i napomena o korištenju AI alata. Rezultati testova su u [TESTOVI.md](TESTOVI.md).

**Sadržaj**
1. [Arhitektura i put jednog zahtjeva](#1-arhitektura-i-put-jednog-zahtjeva)
2. [Baza podataka](#2-baza-podataka)
3. [Rute i zaštita stranica](#3-rute-i-zaštita-stranica)
4. [Prijava, sesije i uloge](#4-prijava-sesije-i-uloge)
5. [Javne vijesti: popis, pretraga, straničenje](#5-javne-vijesti-popis-pretraga-straničenje)
6. [Profil korisnika](#6-profil-korisnika)
7. [CMS: vijesti, kategorije, korisnici](#7-cms-vijesti-kategorije-korisnici)
8. [Prijave problema](#8-prijave-problema)
9. [Upload slika](#9-upload-slika)
10. [Sigurnost – pregled svih zaštita](#10-sigurnost--pregled-svih-zaštita)
11. [Izgled i pristupačnost](#11-izgled-i-pristupačnost)
12. [Testiranje](#12-testiranje)
13. [Česta pitanja](#13-česta-pitanja)
14. [Dnevnik faza](#14-dnevnik-faza)
15. [Korištenje AI alata](#15-korištenje-ai-alata)

---

## 1. Arhitektura i put jednog zahtjeva

### Struktura mapa

```
.htaccess                 sve zahtjeve šalje u public/ (ništa izvan public/ nije dostupno)
public/
  .htaccess               sve što nije postojeća datoteka → index.php
  index.php               FRONT CONTROLLER – jedina PHP datoteka dostupna iz preglednika
  assets/                 css/app.css, js/app.js, js/urednik.js, img/grb-zagreb.svg
  uploads/                javne slike (vijesti); .htaccess zabranjuje izvršavanje
config/routes.php         popis svih ruta i tko im smije pristupiti
src/
  helpers.php             e(), url(), redirect(), post(), get(), csrf_field()...
  Core/                   "mini framework": Router, View, Database, Session, Csrf, Auth, Paginator
  Controllers/            prima zahtjev → poziva servis → bira odgovor
  Services/               poslovna logika: validacija, pravila, tko smije što
  Models/                 SAMO SQL upiti, jedna klasa po tablici (ili namjeni)
views/                    HTML predlošci (layout + pogledi + partials)
database/                 schema.sql, seed.sql, demo_slike.php
tests/                    automatski testovi (bash + curl)
storage/                  logs/app.log, cache/, uploads/prijave/ (privatne slike) – nije u gitu
```

Uloge slojeva:

| Sloj | Odgovornost | Ne radi |
|---|---|---|
| **Router** | odabire kontroler; provjerava CSRF, prijavu i ulogu | ne zna ništa o podacima |
| **Controller** | čita ulaz (`post()`, `get()`), poziva servis, bira pogled ili preusmjeravanje | ne piše SQL |
| **Service** | validacija, poslovna pravila, ovlasti nad konkretnim zapisom | ne ispisuje HTML |
| **Model** | prepared statements nad bazom | ne odlučuje što je dopušteno |
| **View** | ispis HTML-a; svaki podatak kroz `e()` | ne dohvaća podatke |

Konfiguracija je odvojena od koda: tajne su u `.env` (nije u gitu), a primjer je u `.env.example`.

### Konvencija imenovanja

- **Tehnički pojmovi na engleskom:** `Router`, `Controller`, `loginForm`, `render`.
- **Pojmovi iz domene na hrvatskom:** `Korisnik`, `uloge`, `aktivan`, `/racun/prijava`.
- **Baza:** hrvatski, `snake_case`, bez dijakritika (`kreirano_u`, `cetvrt_id`). Dijakritici u nazivima stupaca rade, ali stvaraju probleme s kodiranjem u alatima.
- Korisnički račun je pod `/racun/...`, jer bi se „prijava” (login) inače miješala s „prijavama” komunalnih problema (`/prijave`).

### Put jednog zahtjeva (primjer: prijava korisnika)

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
   - provjerava ograničenje broja pokušaja (vidi [4](#zaštita-od-pogađanja-lozinke-brute-force));
   - preko **`Korisnik::findForLogin`** dohvaća korisnika: `SELECT ... WHERE email = ?` (prepared statement);
   - poziva `password_verify` (i kad korisnik ne postoji, vidi [4](#prijava));
   - provjerava je li račun aktivan;
   - po potrebi obnavlja hash lozinke i bilježi zadnju prijavu.
7. Ako prijava ne uspije, kontroler prikazuje obrazac s generičkom porukom (status 401, ili 429 kod blokade).
   Ako uspije, **`Auth::login`** poziva `session_regenerate_id(true)`, sprema samo `korisnik_id` u sesiju i stvara novi CSRF token. Slijedi preusmjeravanje (303): admin na `/admin`, urednik na `/admin/vijesti`, ostali na početnu.
8. Pri sljedećem zahtjevu **`Auth::user()`** iz baze ponovno čita korisnika, njegovu ulogu i polje `aktivan`.

### Pogreške

- `display_errors` je uključen samo kad je `APP_DEBUG=true`.
- Upozorenja (warning/notice) pretvaraju se u iznimke, pa se ne mogu tiho ignorirati.
- Neuhvaćena iznimka se zapisuje u `storage/logs/app.log`, a korisnik vidi generičku stranicu 500, bez SQL-a i putanja.
- Ako ni stranica pogreške ne može nastati (npr. baza ne radi), ispisuje se običan tekst.

---

## 2. Baza podataka

Puni ER dijagram i tablica pravila brisanja: [ER.md](ER.md).

### Opća pravila sheme

- **`utf8mb4` + `utf8mb4_croatian_ci`.**
  - `utf8mb4` je pravi UTF-8 (svi znakovi, i emoji). Stari MySQL `utf8` podržava samo 3 bajta.
  - `croatian` ispravno sortira č, ć, dž, đ, lj, nj, š, ž, pa „Črnomerec” dolazi nakon „Cvjetnog”, a ne na kraj popisa.
  - `_ci` (case-insensitive): usporedba ne razlikuje velika i mala slova. `UNIQUE` na e-mailu zato odbija `Ana@x.hr` ako postoji `ana@x.hr`, a pretraga `PROMETA` nalazi „prometa”.
- **InnoDB.** Jedini MariaDB pogon sa stranim ključevima i transakcijama.
- **`DATETIME`, a ne `TIMESTAMP`.** `TIMESTAMP` ima ograničenje do 2038. i sam pretvara vremenske zone. `DATETIME` sprema točno ono što se upiše. Zona (`Europe/Zagreb`) postavlja se u PHP-u, a `Database` istu zonu postavlja i za MySQL vezu.
- **`UNSIGNED` cijeli brojevi različitih veličina.** ID ne može biti negativan. Šifrarnicima s malo redaka (uloge, četvrti) dovoljan je `TINYINT` (do 255), a prijavama i vijestima `INT`. Strani ključ mora imati **isti** tip kao primarni ključ na koji pokazuje, inače MariaDB odbija vezu.
- **ENUM za fiksne popise, tablica za popise koji se mijenjaju.**
  - `status`, `hitnost` i `tip` slike su dio poslovne logike (state machine u kodu). Nova vrijednost ionako traži promjenu koda, pa je ENUM prikladan i sprječava neispravnu vrijednost.
  - `gradske_cetvrti`, `vrste_problema`, `kategorije_vijesti` i `uloge` prikazuju se u izbornicima i administrator ih može mijenjati, pa su zasebne tablice.
  - Iznimka: vrste radova su ENUM jer ih nitko ne uređuje kroz sučelje.
- **CHECK ograničenja** (MariaDB 10.2+ ih stvarno provodi):
  - objavljena vijest mora imati `datum_objave`;
  - koordinate su ili obje upisane ili obje prazne i moraju biti približno u Zagrebu;
  - kraj radova ne smije biti prije početka.

  CHECK je **zadnja linija obrane**. Glavna validacija je u PHP-u, gdje korisnik dobiva razumljivu poruku, a CHECK hvata greške u kodu. Granice koordinata u bazi su namjerno šire (45.60–46.00, 15.75–16.30); preciznu provjeru radi PHP.
- **Indeksi.** Strani ključevi u InnoDB-u automatski dobivaju indeks. Ručno su dodani složeni indeksi za najčešće upite:
  - `vijesti(status, datum_objave)`: javni popis `WHERE status='objavljeno' ORDER BY datum_objave DESC`;
  - `prijave(status, hitnost)`: kanban i administratorski popis;
  - `radovi(pocetak, kraj)`: filtar „aktivni radovi”;
  - `pokusaji_prijave(email, vrijeme)` i `(ip, vrijeme)`: brojanje neuspjelih prijava.

### Tablice

**`uloge` + `korisnici.uloga_id`.** Jedna uloga po korisniku, jer se u zahtjevima govori o „promjeni uloge”. Veza više-na-više bila bi složenija bez stvarne koristi. Kod provjerava `uloge.kod` (`'admin'`), a ne `id = 4`, jer je čitljivije i ne ovisi o redoslijedu unosa.

**`korisnici`.**
- `email` je `UNIQUE`; PHP ga uz to sprema malim slovima.
- `lozinka_hash VARCHAR(255)`: bcrypt danas ima 60 znakova, ali PHP preporučuje 255 jer budući algoritmi (npr. Argon2) daju duže hasheve.
- `aktivan`: korisnik se ne briše nego deaktivira, jer je vezan uz vijesti, prijave i povijest. Za prekid sesija nije potreban dodatni stupac (vidi [4](#sesija)).

**`vijesti`.**
- Dva statusa: `nacrt` i `objavljeno`. „Povlačenje” je vraćanje u `nacrt`; `datum_objave` ostaje, pa se vidi da je vijest nekad bila objavljena.
- `sadrzaj` je `MEDIUMTEXT` (do 16 MB). U bazu se sprema **već pročišćen** HTML (vidi [7](#rich-text-quill--htmlpurifier)).
- `slika` je samo ime datoteke. Slika je na disku, jer bi spremanje u bazu (BLOB) znatno povećalo bazu i usporilo sigurnosne kopije. Jedna slika po vijesti.

**`prijave`.**
- **Status je samo u tablici `prijave`**, a ne i u `zadaci`. Prijava postoji (`PRIJAVLJENO`) prije nego što zadatak nastane. Da je status zapisan na dva mjesta, mogao bi se razići (prijava `ZAVRSENO`, zadatak `U_RADU`). Pravilo normalizacije: jedan podatak, jedno mjesto.
- `naslov` je dodan jer dugački opis ne stane na karticu kanbana.
- `opis` je običan tekst, ne HTML: građanin ne treba formatiranje, a rizik od XSS-a je manji.
- Redoslijed ENUM-a hitnosti (`niska, srednja, visoka, kriticna`) je namjeran: MariaDB sortira ENUM po rednom broju, pa `ORDER BY hitnost DESC` stavlja kritične na vrh.

**`zadaci` (1:1 s prijavom).**
- `UNIQUE(prijava_id)`: prijava ima najviše jedan zadatak. Pri preraspodjeli mijenja se `radnik_id`, a trag ostaje u povijesti.
- `dodijelio_id` bilježi koji je administrator dodijelio zadatak.
- `napomena_radnika` je obavezna pri slanju na provjeru. To provjerava PHP, jer CHECK ne može pratiti promjenu statusa u drugoj tablici.
- Zasebna tablica odvaja podatke građanina od podataka o izvođenju, a kanban radnika je jednostavan upit `WHERE radnik_id = ?`.

**`prijava_slike` (tip `prije`/`poslije`).** Jedna tablica za obje vrste slika, s razlikom u stupcu `tip`. Upit „ima li zadatak sliku poslije” je `WHERE prijava_id=? AND tip='poslije'` i ima indeks. Spremaju se `mime_tip` i `velicina` (što je provjereno pri uploadu). `datoteka` je `UNIQUE`.

**`povijest_statusa` (revizijski trag).** Retci se samo dodaju, nikad mijenjaju. `iz_statusa` je NULL u prvom zapisu. Iz tablice se rekonstruira cijeli tijek: tko, kada, iz kojeg u koji status i zašto.

**`radovi`.** Koordinate su obavezne (radovi se uvijek prikazuju na karti). `kraj` može biti NULL (još nije poznat). `utjecaj_na_promet` povezuje radove s prometom.

**`pokusaji_prijave`.** Neuspjeli pokušaji prijave (e-mail, IP, vrijeme). Namjerno **nema** strani ključ prema korisnicima, jer se bilježe i pokušaji za nepostojeće e-mailove (vidi [4](#zaštita-od-pogađanja-lozinke-brute-force)).

### Pravila brisanja

- **RESTRICT** prema kategorijama: kategorija s vijestima se ne može obrisati. Baza vraća grešku 1451, a aplikacija prije toga sama provjeri i prikaže razumljivu poruku (vidi [7](#kategorije)).
- **RESTRICT** prema korisnicima: korisnik se nikad ne briše, nego deaktivira. Inače bi se izgubilo tko je napisao vijest ili odobrio zadatak.
- **CASCADE** od prijave prema slikama, zadatku i povijesti: to su „dijelovi” prijave i bez nje nemaju smisla.

### Tok statusa prijave i transakcije

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

**Transakcija je obavezna** kad jedna radnja mijenja više povezanih tablica. Primjer: dodjela zadatka radi `INSERT zadaci`, `UPDATE prijave.status` i `INSERT povijest_statusa`. Ako jedan upit ne uspije, ne smije ostati prijava sa statusom `DODIJELJENO` bez zadatka. Postupak je `beginTransaction()` → tri upita → `commit()`, a kod greške `rollBack()`.

### Testni podaci (`seed.sql`)

| Scenarij | Podatak |
|---|---|
| sve uloge | 1 admin, 2 urednika, 2 radnika, 3 korisnika |
| deaktivirani račun | `neaktivna@...` (`aktivan = 0`) |
| urednik i tuđa vijest | Lea (vijesti 2, 3, 6, 7) i Tin (vijesti 4, 5, 8) |
| neobjavljene vijesti | 7 i 8 (nacrt), 9 (povučena) |
| brisanje kategorije | „Arhiva” je prazna (smije se obrisati), ostale nisu |
| svi statusi prijava | prijave 1–10 pokrivaju svih 6 statusa i 4 hitnosti |
| prijava bez koordinata | prijava 2 |
| radovi | aktivni, budući, završeni, bez poznatog kraja |

- **Bez stvarnih osobnih podataka:** imena su izmišljena, a e-mailovi koriste domenu `.test`, rezerviranu standardom RFC 2606.
- **Lozinke:** u `seed.sql` su samo bcrypt hashevi. Svi računi imaju istu testnu lozinku, ali je svaki hash drukčiji zbog nasumične soli. Lozinka u čistom tekstu je samo u `database/testni_racuni.local.md` (u `.gitignore`).
- **Eksplicitni ID-jevi** služe čitljivosti (vidi se da prijava 6 pripada korisniku 7). AUTO_INCREMENT zatim nastavlja od najvećeg ID-a.
- Seed je u transakciji (`START TRANSACTION … COMMIT`): upiše se sve ili ništa.

### Recept: postavljanje ili resetiranje baze

1. U XAMPP-u pokreni MySQL.
2. Uvezi shemu, pa podatke:
   ```
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql"
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\seed.sql"
   C:\xampp\php\php.exe database\demo_slike.php
   ```
   Zadnja naredba stvara slike za testne prijave. Uvoz se može i kroz phpMyAdmin → **Import** (za phpMyAdmin mora raditi i Apache).
3. `schema.sql` **briše i ponovno stvara sve tablice**. Postupak se ponavlja svaki put kad se shema promijeni.

### Recept: nova tablica

1. Dodaj `DROP TABLE IF EXISTS ...` na vrh `schema.sql` (prije tablica o kojima ovisi) i `CREATE TABLE` na kraj.
2. Odredi pravilo brisanja za svaki strani ključ (`RESTRICT` ili `CASCADE`) i upiši ga u `docs/ER.md`.
3. Dodaj testne podatke u `seed.sql` ako su potrebni.
4. Napravi model u `src/Models/` (samo SQL, prepared statements).
5. Ponovno uvezi bazu (recept iznad).

---

## 3. Rute i zaštita stranica

### Kako radi router

- Rute se definiraju u `config/routes.php`:
  ```php
  $router->get('/admin/korisnici', [UserAdminController::class, 'index'], $samoAdmin);
  ```
- `{id}` u putanji postaje regex `(?P<id>\d+)`: prihvaćaju se samo brojevi, a kontroler dobiva cijeli broj (`/vijesti/abc` → 404).
- Putanja postoji, ali za drugu metodu → **405** sa zaglavljem `Allow`. Putanja ne postoji → **404**.
- **Zaštite su opcije rute, a ne kod u svakom kontroleru.** Tako se ne mogu zaboraviti, a u `routes.php` se na jednom mjestu vidi tko smije što:
  - `auth`: neprijavljeni se preusmjerava na prijavu;
  - `uloge`: kriva uloga dobiva **403** (`$samoAdmin`, `$urednistvo` su kratice za česte kombinacije);
  - `gost`: prijavljeni se preusmjerava na početnu.
- **CSRF se automatski provjerava za SVAKI zahtjev koji nije GET.** Zato GET zahtjev nikad ne smije mijenjati podatke.

### Statusni kodovi u HTML dijelu

| Situacija | Kod |
|---|---|
| Pogrešna lozinka | 401 (obrazac se ponovno prikazuje) |
| Previše neuspjelih prijava | 429 + `Retry-After` |
| Neispravni podaci u obrascu | 422 (obrazac s porukama uz polja i upisanim vrijednostima) |
| Uspješan POST | 303 + preusmjeravanje (Post/Redirect/Get: osvježavanje stranice ne šalje obrazac ponovno) |
| Kriva uloga / tuđi zapis / neispravan CSRF token | 403 |
| Nepostojeća stranica ili zapis / kriva metoda | 404 / 405 |

### Recept: nova javna stranica

1. `config/routes.php`: `$router->get('/nesto', [NekiController::class, 'metoda']);`
2. U kontroleru: `View::render('nesto', ['title' => 'Naslov', ...]);`
3. Napravi `views/nesto.php`. Svaku vrijednost ispiši kroz `<?= e($vrijednost) ?>`.
4. Poveznicu u izbornik dodaj u `views/layout.php` s `nav_link('/nesto', 'Naziv')`.

### Recept: stranica samo za određenu ulogu

1. Ruti dodaj opciju: `$samoAdmin`, `$urednistvo` ili `['uloge' => ['radnik']]`.
2. Ako se poveznica prikazuje u izborniku, omotaj je s `Auth::hasRole(...)`. To je samo udobnost: pravu zaštitu radi router.
3. Ako stranica radi s **konkretnim zapisom** (vijest, zadatak), uloga nije dovoljna: provjeri i vlasništvo zapisa (recept u [7](#recept-provjera-vlasništva-zapisa-zaštita-od-idor)).

### Recept: obrazac koji sprema podatke

1. U obrascu: `method="post"` i `<?= csrf_field() ?>`. Ako obrazac šalje datoteku, i `enctype="multipart/form-data"`.
2. Ruta: `$router->post(...)`. CSRF provjera je automatska.
3. Kontroler čita **samo poznata polja** preko `post('ime_polja')`. Funkcija vraća prazan string ako netko pošalje niz (`polje[]=x`), pa nema greške 500.
4. Validaciju radi servis i vraća niz `['polje' => 'poruka']`.
5. Pogreška: `View::render(..., 422)` s porukama i upisanim vrijednostima (bez lozinki).
6. Uspjeh: `Session::flash('success', '...')` i `redirect('/putanja')` (303).

---

## 4. Prijava, sesije i uloge

### Registracija

- Registracijom se **uvijek** dobiva uloga `korisnik` (konstanta `AuthService::OSNOVNA_ULOGA`). Kontroler čita samo poznata polja, pa se podmetnuto `uloga=admin` ignorira.
- Pravila za ime i lozinku su na jednom mjestu (`AuthService::validateName`, `validatePassword`) i koriste se i na profilu.
- Lozinka: najmanje 8 znakova, barem jedno slovo i jedna znamenka, **najviše 72 bajta**, jer bcrypt sve iza 72. bajta tiho zanemaruje.
- Dvije istovremene registracije s istim e-mailom: obje prođu provjeru `emailExists()`, ali `UNIQUE` u bazi propusti samo jednu. Greška `23000` se hvata i prikazuje kao „e-mail već postoji”.

### Prijava

- Ista poruka („Neispravan e-mail ili lozinka.”) za nepostojeći e-mail i pogrešnu lozinku, da se ne može doznati koji su e-mailovi registrirani.
- `password_verify` se izvodi i kad korisnik ne postoji, nad lažnim hashem (`LAZNI_HASH`). Inače bi odgovor za nepostojeći e-mail bio primjetno brži, pa bi se računi mogli otkriti mjerenjem vremena.
- `password_needs_rehash`: ako se u budućnosti promijeni zadani algoritam ili cijena, hash se pri prijavi tiho obnovi.

### Zaštita od pogađanja lozinke (brute force)

- Svaki neuspjeli pokušaj upisuje se u `pokusaji_prijave` (e-mail, IP, vrijeme).
- U 15 minuta dopušteno je najviše **5 neuspjelih pokušaja po e-mailu** (štiti jedan račun) i **20 po IP adresi** (sprječava *password spraying*: jedna lozinka isprobana na mnogo računa).
- Nakon granice odgovor je **429 Too Many Requests** sa zaglavljem `Retry-After: 900`.
- Provjera se radi **prije** provjere lozinke: dok traje blokada, ni ispravna lozinka ne prolazi, pa napadač ne može saznati da je pogodio.
- Bilježe se i pokušaji za **nepostojeće** e-mailove, pa se po blokadi ne vidi koji računi postoje.
- Uspješna prijava briše pokušaje za taj e-mail. Zapisi stariji od jednog dana brišu se automatski.
- Kompromis: netko može namjerno blokirati tuđi račun na 15 minuta. U produkciji bi CAPTCHA nakon nekoliko pokušaja bila bolja od potpune blokade.
- Ručno odblokiranje u razvoju: `DELETE FROM pokusaji_prijave`.

### Sesija

- **U sesiji je samo `korisnik_id`.** Uloga i `aktivan` čitaju se iz baze pri **svakom** zahtjevu (`Auth::user()`). Zato:
  - deaktivacija računa odmah prekida i već otvorene sesije, na svim uređajima;
  - promjena uloge vrijedi odmah, bez ponovne prijave.
- **`session_regenerate_id(true)`** nakon prijave i nakon promjene lozinke: novi ID, a stari se briše. Ako je napadač unaprijed podmetnuo ID (*session fixation*), on nakon prijave više ne vrijedi.
- **`session.use_strict_mode`**: poslužitelj odbija ID sesije koji nije sam izdao.
- **Kolačić `ZGPRIJAVE`**: `HttpOnly` (JavaScript ga ne može pročitati), `SameSite=Lax` (ne šalje se uz POST s tuđih stranica), `Secure` automatski na HTTPS-u. ID je samo u kolačiću, nikad u URL-u.
- **Odjava** je POST s CSRF tokenom, a ne poveznica. Odjavu preko GET-a mogla bi pokrenuti bilo koja `<img src=".../odjava">` na tuđoj stranici. `Session::destroy()` briše podatke, kolačić i sesiju na poslužitelju.

### CSRF

- Token je `random_bytes(32)` spremljen u sesiji i obnavlja se nakon prijave.
- Šalje se kao skriveno polje `_csrf` (`csrf_field()`) ili kao zaglavlje `X-CSRF-Token` (za fetch u kasnijim fazama).
- Uspoređuje se s `hash_equals`, koji traje jednako dugo bez obzira na to gdje se nizovi razlikuju.
- `SameSite=Lax` je dodatni sloj. Stariji preglednici ga ne poštuju, pa token ostaje glavna zaštita.

---

## 5. Javne vijesti: popis, pretraga, straničenje

### Glavno pravilo: neobjavljena vijest nikad ne smije procuriti

Svi javni upiti u modelu `Vijest` prolaze kroz privatnu metodu `publicWhere()`, koja **uvijek** dodaje `v.status = 'objavljeno'`. Kontroler ne piše taj uvjet sam, pa ga ne može ni zaboraviti. Pokriveno je:
- popis i brojači (nacrt se ne broji ni u broju vijesti po kategoriji);
- pretraga (riječ iz nacrta ne vraća nacrt);
- izravan URL `/vijesti/7` (nacrt) i `/vijesti/9` (povučena), koji vraća **404, isto kao nepostojeća vijest**. Odgovor 403 („nemate pravo”) otkrio bi da vijest s tim ID-jem postoji.

Nacrte vidi samo CMS, kroz zaseban model `VijestCms` (vidi [7](#vijesti-u-cms-u)).

### Tok podataka: `GET /vijesti?q=promet&kategorija=2&stranica=1`

1. `index.php` → `Router` → `NewsController::index` (javna ruta, GET, bez CSRF-a).
2. Kontroler čita parametre:
   - `get('q')`, skraćen na 100 znakova;
   - `get_int('kategorija')`: `kategorija=abc` postaje `null` i filtar se ignorira;
   - niz (`q[]=x`) postaje prazan string.
3. `Vijest::countPublished()`: `SELECT COUNT(*) ... WHERE status='objavljeno' AND kategorija_id = :kategorija AND (naslov LIKE :q1 OR ...)`.
4. `Paginator` iz ukupnog broja izračunava broj stranica, ispravlja nemoguću stranicu i daje `OFFSET`.
5. `Vijest::findPublished()` s `LIMIT :limit OFFSET :offset`; oba parametra vezana su kao `PDO::PARAM_INT`.
6. Pogled `vijesti/index.php` ispisuje kartice (sve kroz `e()`), filtre i straničenje. Poveznice straničenja čuvaju aktivne filtre.

### Odluke

- **Pretraga i filtri su GET.** Rezultat ima vlastiti URL koji se može podijeliti, spremiti ili otvoriti gumbom Natrag. GET ništa ne mijenja, pa ne treba CSRF token.
- **Zamjenski znakovi u LIKE se escapeaju.** `%` i `_` u LIKE znače „bilo što” i „bilo koji znak”. Bez escapinga bi pretraga `%` vratila sve vijesti. `addcslashes($q, '%_\\')` ih pretvara u obične znakove.
- **`:q1`, `:q2`, `:q3` umjesto jednog `:q`.** S pravim prepared statementima (`EMULATE_PREPARES = false`) isti imenovani parametar ne smije se pojaviti dvaput u istom upitu.
- **WHERE se gradi iz dijelova, ali sigurno.** U SQL tekst ulaze samo fiksni dijelovi koje je napisao programer. Korisnikove vrijednosti idu isključivo kroz `bindValue`/`execute`.
- **Nemoguća stranica se ispravlja umjesto greške:** `stranica=999` prikazuje zadnju stranicu, `stranica=abc` prvu.
- **4 vijesti po stranici**, da se straničenje vidi već sa 6 testnih vijesti. Mijenja se konstantom `NewsController::PO_STRANICI`.
- **Prazni rezultati** imaju vlastitu poruku (drukčiju za pretragu i za praznu kategoriju) i gumb za povratak.
- **Sadržaj vijesti ispisuje se bez `e()`**, jer je to formatirani HTML. Siguran je zato što ga HTMLPurifier čisti **pri spremanju** (vidi [7](#rich-text-quill--htmlpurifier)). Naslov, sažetak, autor i kategorija idu kroz `e()`.
- **Statistika na početnoj** prikazuje samo zbirne brojeve (`COUNT`, `SUM`), bez podataka o pojedinoj prijavi ili korisniku.

### Recept: popis sa straničenjem i filtrom

1. U modelu napravi dvije metode koje dijele isti WHERE: `count(filtri)` i `find(filtri, limit, offset)`. WHERE gradi privatna metoda koja vraća `[sqlDio, parametri]`.
2. U kontroleru:
   ```php
   $paginator = new Paginator(
       total: $model->count($filtar),
       perPage: 10,
       requestedPage: get_int('stranica'),
       path: '/putanja',
       query: ['filtar' => $filtar],   // poveznice zadržavaju filtre
   );
   $redovi = $model->find($filtar, $paginator->perPage, $paginator->offset);
   ```
3. U pogledu: `<?php require __DIR__ . '/partials/paginacija.php'; ?>` (put prilagodi dubini mape).
4. `LIMIT` i `OFFSET` veži s `PDO::PARAM_INT`; kao tekst bi upit pao.

---

## 6. Profil korisnika

- **ID korisnika uzima se iz sesije, nikad iz obrasca.** Podmetnuti `id=1` i `email=...` u POST-u se ignoriraju.
- **E-mail i uloga ne mijenjaju se na profilu.** Promjena e-maila zahtijevala bi potvrdu nove adrese, a ulogu mijenja administrator.
- **Za promjenu lozinke traži se trenutna lozinka.** Netko tko sjedne za tuđe otključano računalo ne može tako preuzeti račun.
- Nova lozinka mora biti različita od stare i zadovoljiti ista pravila kao kod registracije.
- Nakon promjene lozinke poziva se `session_regenerate_id(true)`.
- **Poznato ograničenje:** ostale otvorene sesije istog korisnika (npr. na drugom uređaju) ostaju aktivne do odjave. Rješenje bi bio stupac `lozinka_promijenjena_u` i usporedba s vremenom prijave spremljenim u sesiji.

---

## 7. CMS: vijesti, kategorije, korisnici

| Ruta | Što radi | Tko |
|---|---|---|
| `GET /admin` | Pregled s brojevima i poveznicama na module | admin |
| `GET /admin/vijesti?q=&status=` | Popis vijesti s pretragom po naslovu, filtrom statusa i straničenjem | urednik (svoje), admin (sve) |
| `GET /admin/vijesti/nova`, `POST /admin/vijesti` | Nova vijest | urednik, admin |
| `GET /admin/vijesti/{id}/uredi`, `POST /admin/vijesti/{id}` | Uređivanje, objava, povlačenje, slika | autor ili admin |
| `POST /admin/vijesti/{id}/obrisi` | Brisanje vijesti i datoteke slike | autor ili admin |
| `GET/POST /admin/kategorije`, `POST /admin/kategorije/{id}`, `POST .../obrisi` | Kategorije | admin |
| `GET /admin/korisnici?q=&uloga=&aktivan=`, `POST /admin/korisnici/{id}` | Uloge i aktivnost računa | admin |

### Recept: provjera vlasništva zapisa (zaštita od IDOR)

Router provjerava samo **ulogu**. Urednik bi promjenom broja u URL-u (`/admin/vijesti/4/uredi`) mogao doći do tuđe vijesti. Taj se napad zove IDOR (*Insecure Direct Object Reference*).

1. U servisu napravi pravilo, npr. `NewsAdminService::canEdit($korisnik, $vijest)`: admin smije sve, urednik samo kad je `autor_id` jednak njegovom ID-u.
2. U kontroleru napravi jednu privatnu metodu koja učitava zapis i odmah provjerava pravilo:
   ```php
   private function findEditable(int $id): ?array
   {
       $vijest = (new VijestCms())->findById($id);
       if ($vijest === null) { View::error(404); return null; }
       if (!NewsAdminService::canEdit(Auth::user(), $vijest)) { View::error(403); return null; }
       return $vijest;
   }
   ```
3. Pozovi je u **svakoj** radnji nad postojećim zapisom: prikaz obrasca, spremanje i brisanje.
4. U popisu filtriraj već u SQL-u (`WHERE v.autor_id = :autor`), da se tuđi zapisi ni ne prikazuju.

Isti obrazac koristit će radnik i njegovi zadaci u fazi 6.

### Vijesti u CMS-u

- **Zaseban model `VijestCms`.** Javni model `Vijest` uvijek filtrira samo objavljene vijesti. Kad bi isti model imao i metode koje vraćaju nacrte, lako bi se greškom pozvala kriva metoda na javnoj stranici. `VijestCms` koriste samo CMS kontroleri.
- **Autor je uvijek prijavljeni korisnik iz sesije.** Kontroler uzima samo poznata polja (`naslov`, `sazetak`, `sadrzaj`, `kategorija_id`, `status`, `ukloni_sliku`). Podmetnuti `autor_id` ili `datum_objave` se ignoriraju (zaštita od *mass assignment*). Kad admin uređuje tuđu vijest, autor ostaje isti.
- **Objava i povlačenje:**
  - `datum_objave` postavlja SQL pri **prvoj** objavi i poslije se ne mijenja: `IF(? = 'objavljeno' AND datum_objave IS NULL, NOW(), datum_objave)`;
  - povučena vijest (nacrt s datumom objave) u popisu ima oznaku „povučeno”.
- **Urednik sam objavljuje svoje vijesti.** Odobravanje od strane admina moguća je nadogradnja.
- **Brisanje je stvarno brisanje**, uz potvrdu u pregledniku (atribut `data-potvrda`, obrađuje ga `app.js`). Za „skrivanje” postoji povlačenje u nacrt.

**Tok podataka: `POST /admin/vijesti`**
1. Router: CSRF → prijava → uloga `urednik` ili `admin`.
2. `NewsAdminController::store()` slaže ulaz od poznatih polja.
3. `NewsAdminService::create()`:
   - čisti sadržaj HTMLPurifierom;
   - validira duljine, postojanje kategorije i status;
   - tek kad je sve ispravno, sprema sliku (`ImageUpload::save()`);
   - poziva `VijestCms::create()`.
4. Ako INSERT ne uspije, upravo spremljena slika se briše, pa nema „napuštenih” datoteka.
5. Uspjeh: 303 na obrazac za uređivanje. Pogreška: 422 i obrazac s porukama.

### Rich-text: Quill + HTMLPurifier

- **Quill 2** se učitava s CDN-a sa SRI hashom (`integrity`), i to samo na obrascu vijesti (`'urednik' => true` u podacima pogleda).
- **Obrazac radi i bez JavaScripta.** U HTML-u je obično `<textarea name="sadrzaj">`. `urednik.js` ga sakrije, prikaže Quill i prije slanja upiše HTML iz uređivača natrag u polje (`getSemanticHTML()`).
- **Alatna traka nudi samo ono što Purifier dopušta:** naslovi H2/H3, podebljano, kurziv, podcrtano, precrtano, liste, citat, poveznica.
- **HTMLPurifier (`HtmlSanitizer`) radi po bijeloj listi:** `p, br, strong, b, em, i, u, s, h2, h3, ul, ol, li, blockquote, a[href|title]`.
  - Sve ostalo se briše: `<script>`, `<iframe>`, `<img>`, `<style>`, atributi `on*=` i `style=`.
  - Poveznice smiju biti samo `http`, `https` i `mailto`, pa `javascript:alert(1)` nestaje.
  - Vanjske poveznice dobivaju `target="_blank" rel="noreferrer noopener"`.
- **Čisti se pri spremanju, a ne pri prikazu.** U bazi je uvijek siguran HTML, pa ga javna stranica i API mogu ispisati bez dodatne obrade. Purifier je spor, a spremanje je rijetko, prikaz čest.
- Ograničenje alatne trake samo je udobnost za urednika. POST se može poslati i bez preglednika, pa je Purifier na poslužitelju prava zaštita.
- `HtmlSanitizer::isEmpty()` provjerava je li ostao vidljiv tekst; prazan uređivač šalje `<p><br></p>`.

### Kategorije

- Naziv je jedinstven. Provjeru radi **baza** (`UNIQUE`); aplikacija hvata SQLSTATE `23000` i prikazuje poruku. Zbog `_ci` collationa „Kultura” i „kultura” su duplikat.
- **Kategorija s vijestima, uključujući nacrte, ne može se obrisati:**
  - `CategoryService::delete()` prebroji vijesti i vrati razumljivu poruku;
  - strani ključ `ON DELETE RESTRICT` je zadnja zaštita, ako se vijest doda između provjere i brisanja;
  - u popisu je gumb „Obriši” onemogućen za takve kategorije, ali poslužitelj pravilo provjerava neovisno o gumbu.
- Uređivanje: `/admin/kategorije?uredi=3` popuni obrazac na vrhu stranice.

### Korisnici i uloge

- Admin mijenja **ulogu** i **aktivnost** računa. Ime, e-mail i lozinku mijenja samo vlasnik računa.
- **Deaktivacija odmah odjavljuje korisnika** na svim uređajima (vidi [4](#sesija)).
- **Admin ne može promijeniti vlastitu ulogu ni deaktivirati sebe**, pa sustav nikad ne ostaje bez administratora. Provjera je na poslužitelju; vlastiti redak u popisu nema obrazac.
- **Uloga se zadaje kodom** (`urednik`) i mora postojati u tablici `uloge`. UPDATE se radi s `JOIN uloge u ON u.kod = ?`, pa nepostojeća uloga ne mijenja ništa.
- **Povratak na isti filtrirani popis** nakon spremanja: skriveno polje `povratak`. Dopuštena je samo putanja `/admin/korisnici?...` bez razmaka i novih redaka (regex). Time su spriječeni **open redirect** (preusmjeravanje na `https://napadac.example`) i **CRLF injection** (podmetanje zaglavlja).

---

## 8. Prijave problema

| Ruta | Što radi | Tko |
|---|---|---|
| `GET /prijave/nova`, `POST /prijave` | Obrazac i slanje nove prijave | svaki prijavljeni korisnik |
| `GET /prijave/moje?status=` | Vlastite prijave s filtrom statusa i straničenjem | svaki prijavljeni korisnik |
| `GET /prijave/{id}` | Detalj: slike, podaci, tijek statusa | podnositelj, admin, dodijeljeni radnik |
| `GET /prijave/{id}/slike/{slikaId}` | Posluživanje slike (iz `storage/`, ne iz `public/`) | isto kao detalj |

Novi dijelovi koda:
- **kontroler:** `PrijavaController`;
- **servisi:**
  - `PrijavaService` – validacija, transakcija i pravila tko vidi što;
  - `StatusPrijave` – nazivi i boje statusa i hitnosti (u fazi 6 i dopušteni prijelazi);
- **modeli:** `Prijava` (proširen), `PrijavaSlika`, `PovijestStatusa`, `Sifrarnik` (četvrti i vrste problema);
- **skripta:** `database/demo_slike.php`.

Prijavu može poslati **svaki prijavljeni korisnik**, a ne samo uloga `korisnik`. I radnik ili urednik je građanin koji može primijetiti rupu na cesti.

### Što šalje korisnik, a što postavlja sustav

| Polje | Izvor |
|---|---|
| fotografija, gradska četvrt, vrsta problema, naslov, opis | korisnik (obavezno) |
| adresa ili opis lokacije | korisnik (neobavezno) |
| podnositelj (`korisnik_id`) | sesija |
| datum i vrijeme (`kreirano_u`) | baza, `DEFAULT CURRENT_TIMESTAMP` |
| hitnost `srednja`, status `PRIJAVLJENO` | baza, `DEFAULT` (hitnost mijenja samo admin, faza 6) |

Podmetnuti `hitnost`, `status` ili `korisnik_id` u zahtjevu se ignoriraju (mass assignment).

Opis je **običan tekst**, ne HTML. Ispisuje se s `nl2br(e($opis))`: najprije escaping, pa tek onda pretvaranje prijeloma redaka u `<br>`. Obrnutim redoslijedom `e()` bi escapeao i `<br>`.

### Tok podataka: `POST /prijave` (transakcija)

1. Router: CSRF → prijava (`auth`).
2. `PrijavaController::store()` slaže ulaz od poznatih polja.
3. `PrijavaService::create()`:
   - provjerava ograničenje (najviše 10 prijava po korisniku u sat vremena, inače **429**);
   - validira četvrt i vrstu (moraju postojati u šifrarniku), naslov (5–150), opis (10–2000) i adresu (do 255);
   - tek ako je sve ispravno, sprema sliku (`ImageUpload`, do 5 MB, u `storage/uploads/prijave/`).
4. **Transakcija:**
   ```php
   $db->beginTransaction();
   try {
       $id = (new Prijava())->create($korisnikId, $data);                      // 1. prijava
       (new PrijavaSlika())->add($id, $korisnikId, 'prije', ...);              // 2. slika
       (new PovijestStatusa())->add($id, $korisnikId, null, 'PRIJAVLJENO');    // 3. povijest
       $db->commit();
   } catch (Throwable $e) {
       $db->rollBack();
       ImageUpload::delete($upload['ime'], 'prijave');   // datoteka bez zapisa nikome ne treba
       throw $e;
   }
   ```
   Bez transakcije bi pad drugog ili trećeg upita ostavio prijavu bez slike ili bez povijesti. Test to dokazuje okidačem (*trigger*) u bazi koji namjerno ruši treći upit: nakon pogreške u bazi nema ni prijave, ni zapisa slike, ni datoteke.
5. Uspjeh: 303 na `/prijave/{id}`. Pogreška validacije: 422 i obrazac s porukama. Preglednik ne može ponovno popuniti polje za datoteku, pa poruka traži ponovni odabir fotografije.

### Tko vidi što

| | Podnositelj | Dodijeljeni radnik | Admin | Ostali |
|---|---|---|---|---|
| detalj prijave i slika „prije” | ✔ | ✔ | ✔ | 403 |
| slika „poslije” | tek kad je status `ZAVRSENO` | ✔ | ✔ | 403 |
| interni komentari (napomena radnika, razlog vraćanja) | — | ✔ | ✔ | — |
| imena zaposlenika u tijeku | samo uloga („Radnik”, „Administrator”) | ✔ | ✔ | — |
| ime podnositelja | „Vi” | ✔ | ✔ | — |

- Pravila su u `PrijavaService` (`canView`, `isStaff`, `canSeeImage`). Kontroler ih poziva i za detalj i za **svaku** sliku (recept iz [7](#recept-provjera-vlasništva-zapisa-zaštita-od-idor)).
- Slika „poslije” skrivena je od građanina dok admin ne odobri posao: može je još odbiti i vratiti radniku.
- `PrijavaSlika::find($prijavaId, $slikaId)` traži sliku **unutar** te prijave. Inače bi se kroz vlastitu prijavu mogla dohvatiti tuđa slika: `/prijave/1/slike/<id slike druge prijave>` → 404.

### Slike izvan webroota

Fotografije prijava mogu sadržavati osobne podatke (lica, registarske oznake, kuću podnositelja). Zato se, za razliku od slika vijesti, **ne nalaze u `public/`**:
- spremaju se u `storage/uploads/prijave/`, do kojeg preglednik ne može doći (korijenski `.htaccess` sve šalje u `public/`);
- jedini put do slike je `PrijavaController::image()`, koji provjerava prijavu i pravo pristupa, a zatim šalje datoteku s `readfile()`;
- odgovor ima `Content-Type` iz baze (provjeren pri uploadu), `Cache-Control: private` (ne sprema se na dijeljenim proxyjima) i `Content-Security-Policy: sandbox`;
- ime datoteke dolazi iz baze, a `ImageUpload::path()` uz to koristi `basename()`, pa `../` ne može izaći iz mape.

### Demo slike za testne podatke

Prijave iz `seed.sql` pokazuju na datoteke `demo-p01-prije.png` … Stvara ih `database/demo_slike.php` (pokreće se nakon uvoza seeda, vidi [2](#recept-postavljanje-ili-resetiranje-baze)).
- XAMPP nema GD, pa skripta slaže PNG „ručno": retci piksela, svaki s bajtom filtra `0`, sažeti `gzcompress` i zapakirani u PNG blokove `IHDR`, `IDAT`, `IEND` s CRC32.
- Natpis („PRIJAVA #06 / POSLIJE / DEMO”) iscrtava se vlastitim fontom 5×7 piksela.
- Datoteke nisu u gitu (`/storage/uploads/*`), jer ih skripta uvijek može ponovno stvoriti.

### Statusi u sučelju

`StatusPrijave::STATUSI` za svaki status drži naziv, boju oznake i kratko objašnjenje za građanina (npr. „Čeka provjeru – radnik je završio, služba provjerava obavljeni posao”). Nazivi su na jednom mjestu, pa popis, detalj i (kasnije) kanban prikazuju iste nazive i boje.

---

## 9. Upload slika

`ImageUpload` ne vjeruje ničemu što šalje preglednik:

| Provjera | Zašto |
|---|---|
| `is_uploaded_file()` | datoteka je stvarno stigla HTTP uploadom |
| veličina ≤ 2 MB za vijesti, ≤ 5 MB za prijave (fotografije s mobitela) | zaštita diska i memorije |
| `finfo` čita MIME iz **sadržaja** datoteke (magični bajtovi) | `Content-Type` i ekstenziju bira klijent i može ih lažirati |
| samo JPEG, PNG, WebP | **SVG nije dopušten**, jer može sadržavati `<script>` |
| `getimagesize()` prepoznaje sliku, MIME se podudara, dimenzije do 6000 px | odbija lažne slike i „decompression bomb” slike |
| ime `bin2hex(random_bytes(16)).ext`, ekstenzija iz provjerenog MIME-a | ime se ne može pogoditi ni podmetnuti (`../`, `.php`) |
| `public/uploads/.htaccess` (vijesti) | PHP isključen (`php_flag engine off`), poslužuju se samo `.jpg/.png/.webp`, nema popisa datoteka, `nosniff`, CSP `sandbox` |
| `storage/uploads/` (prijave) | izvan webroota; slike poslužuje PHP nakon provjere ovlasti (vidi [8](#slike-izvan-webroota)) |

Mape su zadane u `ImageUpload::MAPE` (`'vijesti'` → `public/uploads/vijesti`, `'prijave'` → `storage/uploads/prijave`), pa pozivatelj ne može upisati proizvoljnu putanju.

- **Obrana u dubinu.** Ispravan PNG s PHP kodom na kraju („poliglot”) prolazi provjere, jer jest valjana slika. Ipak se sprema kao `<nasumično>.png` i ne izvršava se. I PHP datoteka ručno podmetnuta u `uploads/` vraća 403.
- **Redoslijed:** slika se sprema tek kad su ostala polja ispravna. Stara slika briše se s diska tek **nakon** uspješnog UPDATE-a, a ako baza ne uspije, briše se nova. Baza tako nikad ne pokazuje na nepostojeću datoteku.
- `ImageUpload::delete()` prihvaća samo ime oblika `[a-f0-9]{32}.(jpg|png|webp)`, pa se ne može iskoristiti za brisanje drugih datoteka (`../../.env`).
- **Moguća nadogradnja:** ponovno kodiranje slike (GD/Imagick) uklonilo bi skrivene podatke, npr. EXIF s GPS lokacijom. U XAMPP-u GD nije uključen.

### Recept: upload slike u novom obrascu

1. Obrazac: `enctype="multipart/form-data"`, polje `<input type="file" name="slika" accept="image/jpeg,image/png,image/webp">`.
2. Odluči je li slika javna (`public/uploads/...`) ili privatna (`storage/uploads/...`) i dodaj mapu u `ImageUpload::MAPE`.
3. U servisu, **nakon** validacije ostalih polja:
   ```php
   $upload = ImageUpload::save($_FILES['slika'] ?? null, 'vijesti');      // treći argument: najveća veličina
   if (isset($upload['greska'])) { return ['slika' => $upload['greska']]; }
   $ime = $upload['ime'] ?? null;   // null = slika nije poslana; uz ime su i 'mime' i 'velicina'
   ```
4. Ako spremanje u bazu ne uspije, pozovi `ImageUpload::delete($ime, 'vijesti')`.
5. Prikaz:
   - javna slika: `<img src="<?= e(url('uploads/vijesti/' . $ime)) ?>">`;
   - privatna slika: ruta kroz kontroler koji provjeri ovlast i pošalje datoteku (uzor je `PrijavaController::image()`).

---

## 10. Sigurnost – pregled svih zaštita

| Prijetnja | Zaštita | Gdje |
|---|---|---|
| Pristup `.env`, `src/`, `database/`, `.git` iz preglednika | Sve se prepisuje u `public/` | `.htaccess` |
| SQL injection | Prepared statements, `ATTR_EMULATE_PREPARES = false` (pravi prepared statements na strani baze), `LIMIT/OFFSET` kao `PARAM_INT` | `Database`, modeli |
| XSS | Svaki ispis kroz `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8); rich-text kroz HTMLPurifier pri spremanju | `helpers.php`, pogledi, `HtmlSanitizer` |
| CSRF | Token u svakom POST-u, automatska provjera u routeru, `hash_equals`, kolačić `SameSite=Lax`; odjava samo POST-om | `Csrf`, `Router` |
| Krađa lozinki iz baze | `password_hash` (bcrypt, sol), `password_needs_rehash`, granica 72 bajta | `AuthService` |
| Pogađanje lozinke | 5 pokušaja po e-mailu / 20 po IP-u u 15 min → 429 | `AuthService`, `PokusajPrijave` |
| Otkrivanje postojećih računa | Ista poruka i isto trajanje prijave (lažni hash); blokada i za nepostojeće e-mailove | `AuthService::attemptLogin` |
| Session fixation i krađa sesije | `session_regenerate_id(true)`, `use_strict_mode`, `HttpOnly`, ID samo u kolačiću | `Auth::login`, `Session::start` |
| Deaktivirani korisnik ostaje prijavljen | Uloga i `aktivan` čitaju se iz baze pri svakom zahtjevu | `Auth::user` |
| Neovlašten pristup stranici | Opcije rute (`auth`, `uloge`), 403 i kad se URL upiše ručno | `Router::passesGuards` |
| IDOR (tuđi zapis preko ID-a) | Provjera vlasništva za svaku radnju i svaku sliku (`findEditable`/`canEdit`, `findVisible`/`canView`) | `NewsAdminController`, `PrijavaController` |
| Curenje osobnih podataka iz prijava | Slike prijava izvan webroota, poslužuje ih PHP nakon provjere; građanin ne vidi interne komentare ni imena zaposlenika | `PrijavaController::image`, `PrijavaService` |
| Nedovršeni zapisi pri pogrešci | Transakcija za prijavu + sliku + povijest; datoteka se briše kod `rollBack` | `PrijavaService::create` |
| Zatrpavanje prijavama (spam) | Najviše 10 prijava po korisniku u sat vremena → 429 | `PrijavaService` |
| Mass assignment | Kontroler čita samo poznata polja; autor i uloga dolaze iz sesije ili konstante | kontroleri, `AuthService::OSNOVNA_ULOGA` |
| Admin se zaključa izvan sustava | Vlastita uloga i status ne mogu se mijenjati | `UserAdminController` |
| Zlonamjerni upload | MIME iz sadržaja, `getimagesize`, bez SVG-a, nasumično ime, PHP isključen u `uploads/` | `ImageUpload`, `uploads/.htaccess` |
| Open redirect i CRLF injection | `redirect()` gradi URL unutar aplikacije; `povratak` provjeren regexom | `helpers.php`, `UserAdminController` |
| Curenje SQL-a i putanja | Generičke poruke, detalji u `storage/logs/app.log`, `display_errors` samo uz `APP_DEBUG` | `index.php` |
| Clickjacking, MIME sniffing, Referer | `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy: same-origin` | `index.php` |
| Otkrivanje verzije PHP-a | `header_remove('X-Powered-By')` | `index.php` |
| Izmijenjena datoteka na CDN-u | SRI hash (`integrity`) za Bootstrap i Quill | `layout.php` |
| Niz umjesto teksta (`email[]=x`) | `post()`/`get()` vraćaju prazan string → validacijska poruka, ne 500 | `helpers.php` |
| Istovremeni duplikati | `UNIQUE` u bazi, hvatanje SQLSTATE `23000` | servisi |

**Poznata ograničenja i moguće nadogradnje:**
- isticanje sesije zbog neaktivnosti;
- odjava ostalih sesija nakon promjene lozinke;
- `Strict-Transport-Security` (HSTS) i `Secure` kolačić imaju smisla tek na HTTPS-u;
- zaglavlje `Server` otkriva verzije Apachea i PHP-a (podešava se u konfiguraciji poslužitelja: `ServerTokens Prod`, `expose_php = Off`);
- registracija otkriva postoji li e-mail (uobičajen kompromis radi jasne poruke);
- ponovno kodiranje slika (uklanjanje EXIF-a);
- CAPTCHA umjesto potpune blokade nakon krivih lozinki.

---

## 11. Izgled i pristupačnost

- **Grb Grada Zagreba** (SVG) preuzet je s Wikimedia Commons:
  - licenca je **javno vlasništvo (Public domain)**, uz oznaku **„insignia”**: službeni simboli ne smiju se koristiti tako da sugeriraju službeni status;
  - datoteka je pregledana prije uključivanja (nema `<script>` ni vanjskih poveznica) i spremljena lokalno u `public/assets/img/grb-zagreb.svg`;
  - dodan joj je `viewBox` da se ispravno smanjuje.
- **Gdje se grb koristi:** zaglavlje, favicon (`<link rel="icon" type="image/svg+xml">`), uvodni dio početne stranice, podnožje.
- **Napomena „Neslužbena aplikacija”** stoji u podnožju svake stranice i na stranici „O projektu”, jer uz službeni grb aplikacija ne smije izgledati kao aplikacija Grada.
- **Boje:** glavna boja je plava iz grba (`#0277ba`). Bootstrap gumbi i straničenje prilagođeni su preko CSS varijabli u `app.css`.
- **Pristupačnost:**
  - poveznica „Preskoči na sadržaj”;
  - `aria-current` na aktivnoj stavci izbornika i stranici;
  - `alt=""` na ukrasnim slikama (čitač zaslona ih preskače), opisni `alt` gdje slika nosi značenje;
  - skrivene oznake (`visually-hidden`) uz polja za pretragu i filtre;
  - `prefers-reduced-motion` isključuje animaciju kartica.

---

## 12. Testiranje

Automatski testovi u mapi `tests/` šalju stvarne HTTP zahtjeve aplikaciji i provjeravaju odgovore i stanje baze:

```
bash tests/sigurnost.sh     # poznati napadi (SQLi, XSS, CSRF, IDOR, upload, brute force...)
bash tests/cms.sh           # funkcionalnost CMS-a
bash tests/prijave.sh       # prijave problema (uključujući test transakcije)
```

Skripte vraćaju bazu na početne podatke. Opis, upute i tablice „očekivano / dobiveno” za sve faze su u [TESTOVI.md](TESTOVI.md).

### Recept: novi test

1. U odgovarajuću skriptu dodaj redak, npr.:
   ```bash
   check "opis scenarija" "$(code -b "$LEA" "$B/admin/vijesti/4/uredi")" "403"
   has   "  → tekst koji mora biti u odgovoru" 'neki tekst'
   eq    "  → stanje u bazi" "$(echo 'SELECT ...' | SQL)" "očekivano"
   ```
2. Za prijavljenog korisnika: `JAR=$(prijava email@...)`, zatim `-b "$JAR"`.
3. Za POST: CSRF token s `t=$(tok "$JAR" /stranica-s-obrascem)` i `--data-urlencode "_csrf=$t"`.
4. Upload: `-F "slika=@$TW/datoteka.png"` (`$TW` je Windows putanja privremene mape).
   Tekst koji počinje znakom `<` (npr. HTML) šalje se s `--form-string`, jer `-F 'polje=<...'` curl čita kao ime datoteke.
5. Dopuni tablicu u `TESTOVI.md`.

---

## 13. Česta pitanja

**Arhitektura**
- *Što je front controller?* Jedna ulazna točka (`public/index.php`) za sve zahtjeve. Konfiguracija, sesija, zaglavlja i provjere ovlasti izvode se na jednom mjestu, za svaki zahtjev.
- *Zašto servisi između kontrolera i modela?* Pravila (validacija, tko smije što) su tako na jednom mjestu i mogu se koristiti iz više kontrolera, a kasnije i iz API-ja.

**Baza**
- *Zašto se korisnik ne briše?* Zbog RESTRICT pravila i revizijskog traga. Deaktivacija čuva povijest i sprječava pristup.
- *Zašto status prijave nije i u tablici `zadaci`?* Da ne bude zapisan na dva mjesta (normalizacija, jedan izvor istine).
- *Kako baza sprječava dva računa s istim e-mailom?* `UNIQUE` indeks i collation `_ci`, neosjetljiv na velika i mala slova.
- *Zašto CHECK ako PHP već validira?* Obrana u dubinu: ako u kodu postoji greška, baza i dalje odbija nemoguće podatke.
- *Kad se koristi transakcija?* Kad jedna radnja mijenja više tablica koje moraju ostati usklađene: nova prijava (prijava + slika + povijest), dodjela zadatka, slanje na provjeru sa slikom.
- *Kako se može dokazati da transakcija radi?* Privremenim okidačem u bazi koji ruši zadnji upit. Nakon pogreške u bazi ne smije ostati ništa od prvih upita (`tests/prijave.sh`).
- *Gdje se provjerava da radnik ne može staviti ZAVRSENO?* U PHP-u (state machine). Baza zna koje vrijednosti statusa postoje, ali ne i tko ih smije postaviti.

**Sigurnost**
- *Kako PDO štiti od SQL injectiona?* Upit s `?` šalje se bazi odvojeno od podataka, pa baza podatke nikad ne izvršava kao SQL.
- *Zašto `htmlspecialchars` s `ENT_QUOTES`?* Escapea i navodnike, pa je siguran i unutar atributa (`value="..."`).
- *Zašto je u sesiji samo ID, a ne cijeli korisnik?* Da promjena uloge i deaktivacija vrijede odmah. Podatak spremljen u sesiji bi zastario.
- *Zašto CSRF token ako postoji `SameSite=Lax`?* Obrana u dubinu: stariji preglednici ne poštuju SameSite.
- *Zašto je odjava POST?* GET ne smije mijenjati stanje; inače bi odjavu mogla pokrenuti slika na tuđoj stranici.
- *Zašto 404, a ne 403, za nacrt na javnoj stranici?* 403 bi potvrdio da vijest postoji. Za posjetitelja nacrt „ne postoji”.
- *Zašto 403 za tuđu vijest u CMS-u?* Urednik je prijavljen i zna da vijesti postoje (vidi ih na javnoj stranici), pa 403 ne otkriva ništa novo, a jasno kaže zašto radnja nije dopuštena.
- *Zašto se sadržaj vijesti čisti pri spremanju, a ne pri ispisu?* Baza tako sadrži samo siguran HTML, koji se može ispisati na bilo kojem mjestu. Purifier je spor, a spremanja su rijetka.
- *Zašto se ne vjeruje `Content-Type` zaglavlju slike?* Postavlja ga klijent. `finfo` čita stvarne bajtove datoteke.
- *Što ako netko ipak učita PHP kod unutar slike?* Datoteka dobiva nasumično ime s ekstenzijom slike, a u `uploads/` PHP je isključen.
- *Zašto SVG nije dopušten?* SVG je XML koji može sadržavati `<script>`. Otvoren izravno, izvršio bi se u kontekstu naše domene.
- *Što je mass assignment?* Napad u kojem se zahtjevu dodaju polja koja obrazac nema (`autor_id`, `uloga`). Kod uzima samo poznata polja.
- *Kako radi zaštita od pogađanja lozinke?* Neuspjeli pokušaji broje se po e-mailu i po IP adresi u prozoru od 15 minuta. Nakon granice odgovor je 429, čak i za ispravnu lozinku.

**Prijave**
- *Zašto su slike prijava izvan `public/`, a slike vijesti nisu?* Slike vijesti su javne. Slike prijava mogu sadržavati osobne podatke i smije ih vidjeti samo podnositelj i služba, pa ih poslužuje PHP nakon provjere.
- *Zašto građanin ne vidi sliku „poslije” dok posao nije odobren?* Administrator je još može odbiti. Građaninu se prikazuje tek odobreni rezultat.
- *Zašto korisnik ne bira hitnost?* Svatko bi odabrao „kritično”. Hitnost procjenjuje služba (administrator), a sustav postavlja zadanu vrijednost.
- *Zašto `nl2br(e(...))`, a ne `e(nl2br(...))`?* Escaping mora biti prvi. Obrnuto bi i `<br>` pretvorio u vidljivi tekst `&lt;br&gt;`.

**Vijesti i CMS**
- *Što je OFFSET i kako se računa?* `(stranica − 1) × po_stranici`. Za stranicu 2 uz 4 po stranici to je 4: preskaču se prve četiri vijesti.
- *Zašto pretraga escapea `%` i `_`?* To su zamjenski znakovi u LIKE; bez escapinga bi se mogli izvoditi nepredviđeni upiti.
- *Zašto se kategorija s nacrtima ne može obrisati?* Strani ključ ne razlikuje nacrt od objavljene vijesti; vijest bi ostala bez kategorije.
- *Može li admin ukloniti sve administratore?* Ne. Vlastitu ulogu ne može mijenjati, pa uvijek ostaje barem onaj koji je prijavljen.
- *Smije li aplikacija koristiti grb Zagreba?* Grb je u javnom vlasništvu, ali je službeni simbol. Zato aplikacija jasno navodi da nije službena.

---

## 14. Dnevnik faza

| Faza | Što je dodano | Teme u priručniku |
|---|---|---|
| 1 | Prijedlog projekta, ER dijagram, `schema.sql`, `seed.sql` | [2](#2-baza-podataka) |
| 2 | Front controller, router, registracija, prijava, uloge, CSRF, sigurnosna zaglavlja | [1](#1-arhitektura-i-put-jednog-zahtjeva), [3](#3-rute-i-zaštita-stranica), [4](#4-prijava-sesije-i-uloge) |
| 3 | Početna s grbom, javne vijesti (pretraga, filtar, straničenje), profil, „O projektu” | [5](#5-javne-vijesti-popis-pretraga-straničenje), [6](#6-profil-korisnika), [11](#11-izgled-i-pristupačnost) |
| 4 | CMS (vijesti s Quillom, HTMLPurifierom i slikom, kategorije, korisnici), upload, zaštita od pogađanja lozinke (tablica `pokusaji_prijave`), skrivanje `X-Powered-By`, testovi napada | [7](#7-cms-vijesti-kategorije-korisnici), [9](#9-upload-slika), [10](#10-sigurnost--pregled-svih-zaštita), [12](#12-testiranje) |
| 5 | Prijave problema (obrazac sa slikom, transakcija, moje prijave, detalj s tijekom), slike izvan webroota, ograničenje broja prijava, demo slike | [8](#8-prijave-problema), [9](#9-upload-slika) |

Composer ovisnosti: `vlucas/phpdotenv` (faza 2), `ezyang/htmlpurifier` (faza 4). Faza 5 nije dodala nove ovisnosti.

---

## 15. Korištenje AI alata

Tijekom razvoja korišten je **Claude Code** (Anthropic):

| Faza | Za što |
|---|---|
| 1 | prijedlog strukture baze, SQL skripte, dokumentacija |
| 2 | struktura koda, router, autentifikacija, automatski cURL testovi |
| 3 | javni dio, profil, stilovi, testovi, provjera licence grba |
| 4 | CMS (kontroleri, servisi, pogledi, Quill i HTMLPurifier), sigurnosna analiza i testovi napada, zaštita od pogađanja lozinke |
| 5 | prijave problema (servis s transakcijom, pogledi, posluživanje slika izvan webroota), generator demo slika, testovi |

Sav generirani kod pregledan je i ručno isproban u pregledniku, a automatski testovi pokrenuti su nakon svake faze.
