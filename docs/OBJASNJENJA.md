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
