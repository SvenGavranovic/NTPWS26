# Testiranje

Automatski testovi šalju stvarne HTTP zahtjeve aplikaciji, isto kao preglednik ili napadač, i provjeravaju odgovore i stanje baze.

## Pokretanje

Potrebno je:
- pokrenuti Apache i MySQL u XAMPP-u;
- imati Git Bash (dolazi s Gitom za Windows).

```
bash tests/sigurnost.sh     # napadi (OWASP Top 10)
bash tests/cms.sh           # funkcionalnost CMS-a (faza 4)
bash tests/prijave.sh       # prijave problema (faza 5)
```

> **Upozorenje:** skripte na početku i na kraju vraćaju bazu na početne podatke (`schema.sql` + `seed.sql`). Pokreću se samo na razvojnoj bazi.

Svaka skripta na kraju ispisuje `Rezultat: N prošlo, M palo` i završava kodom 0 samo ako ništa nije palo.

Svaka sljedeća faza (prijave, kanban, API, TomTom) dodaje nove slučajeve u `tests/sigurnost.sh`.

## Sigurnosni testovi – `tests/sigurnost.sh`

Stanje nakon faze 5: **138 / 138 prošlo** (30. 10. 2026.)

| # | Napad | Primjer zahtjeva | Očekivano | Dobiveno |
|---|---|---|---|---|
| 1 | SQL injection u prijavi | `email=admin@...' -- `, `lozinka=' OR '1'='1` | 401, nema prijave | ✔ |
| 1 | SQL injection u pretrazi | `q=' OR 1=1 -- `, `q=%' UNION SELECT ... lozinka_hash ...` | 0 rezultata, nema hasheva ni e-mailova | ✔ |
| 1 | Time-based blind SQLi | `q=' AND SLEEP(5) -- ` | odgovor odmah (< 3 s) | ✔ |
| 1 | SQLi u brojčanim parametrima | `kategorija=1 OR 1=1`, `stranica=1;DROP TABLE vijesti`, `/vijesti/1 OR 1=1` | filtar ignoriran / 404; tablica postoji | ✔ |
| 1 | SQLi u administraciji | pretraga i filtar uloge s `' OR '1'='1`, CMS `' UNION SELECT` | 0 rezultata / filtar ignoriran | ✔ |
| 2 | Reflektirani XSS | `q=<script>alert(1)</script>`, `q="><svg onload=...>` | escapeano | ✔ |
| 2 | Pohranjeni XSS – profil | ime `<script>alert(1)</script>` | 422 | ✔ |
| 2 | Pohranjeni XSS – vijest | `<script>`, `onclick`, `javascript:`, `<img onerror>`, `<iframe>`, `style` | uklonjeno HTMLPurifierom (i u bazi); naslov i sažetak escapeani | ✔ |
| 3 | CSRF | POST bez tokena, s tokenom iz tuđe sesije, s krivim `X-CSRF-Token` | 403, podaci nepromijenjeni | ✔ |
| 3 | CSRF odjava preko GET-a | `GET /racun/odjava` (npr. `<img src>`) | 405 | ✔ |
| 3 | Krađa kolačića | zaglavlje `Set-Cookie` | `HttpOnly`, `SameSite=Lax` | ✔ |
| 4 | Session fixation | podmetnut `ZGPRIJAVE=napadacevid...` | poslužitelj izdaje novi ID | ✔ |
| 4 | Session fixation pri prijavi | ID prije i poslije prijave | različit | ✔ |
| 4 | Ponovna upotreba sesije | stari kolačić nakon odjave | preusmjeravanje na prijavu | ✔ |
| 4 | Deaktivirani korisnik | otvorena sesija nakon deaktivacije | odmah odjavljen | ✔ |
| 5 | Neovlašten pristup | gost / korisnik / urednik → `/admin`, `/admin/korisnici`, `/admin/kategorije` | 303 na prijavu / 403 | ✔ |
| 5 | Eskalacija ovlasti | korisnik šalje `POST /admin/korisnici/6 uloga=admin` | 403, uloga ista | ✔ |
| 5 | IDOR | urednica Lea: prikaz, izmjena i brisanje Tinove vijesti 4 | 403, vijest netaknuta | ✔ |
| 5 | Mass assignment | `autor_id=1&datum_objave=...` u vijesti; `uloga=admin&uloga_id=4` u registraciji | ignorirano | ✔ |
| 5 | Admin protiv sebe / nepostojeća uloga | `POST /admin/korisnici/1`, `uloga=superadmin` | odbijeno | ✔ |
| 5 | Forced browsing | `/vijesti/7` (nacrt) | 404 | ✔ |
| 6 | Web shell | `shell.php`; `shell.jpg` s `Content-Type: image/jpeg`; `shell.php.png`; SVG sa `<script>`; 3 MB | 422, ništa nije spremljeno | ✔ |
| 6 | Poliglot (PNG + PHP) | ispravna slika s PHP kodom, ime `poliglot.php` | spremljeno kao `<nasumično>.png`, ne izvršava se, `image/png` | ✔ |
| 6 | PHP podmetnut u `uploads/` | `uploads/vijesti/podmetnuto.php` | 403, ne izvršava se | ✔ |
| 6 | Directory listing / niz datoteka | `/uploads/vijesti/`, `slika[]=` | 403 / bez pogreške | ✔ |
| 7 | Osjetljive datoteke i path traversal | `/.env`, `/.git/config`, `/database/seed.sql`, `/storage/logs/app.log`, `/vendor/...`, `/public/../.env`, `..%2f..%2f.env` … (14 putanja) | 404/403, bez sadržaja | ✔ |
| 8 | Otkrivanje informacija | stranica 404; zaglavlja | bez putanja i stack tracea; nema `X-Powered-By`; ima `X-Frame-Options`, `nosniff`, `Referrer-Policy` | ✔ |
| 8 | Enumeracija korisnika | prijava s nepostojećim e-mailom vs. kriva lozinka | identičan status i poruka | ✔ |
| 9 | Nedopuštene HTTP metode | `PUT /racun/profil`, `DELETE /admin/vijesti/1` | 405 + `Allow` | ✔ |
| 9 | Open redirect | `povratak=https://napadac.example`, `//napadac.example/...` | preusmjeravanje unutar aplikacije | ✔ |
| 9 | CRLF injection | `povratak=/admin/korisnici\r\nSet-Cookie: zlo=1` | bez podmetnutog zaglavlja | ✔ |
| 10 | IDOR na prijavama | Luka otvara Aninu prijavu i njezinu sliku; Ana traži tuđu sliku kroz svoju prijavu (`/prijave/1/slike/<slika prijave 2>`) | 403 / 403 / 404 | ✔ |
| 10 | Izravan pristup privatnim slikama | `/storage/uploads/prijave/...`, `/uploads/prijave/...`, `/public/../storage/...`; gost na `/prijave/1/slike/..` | 404/403, bez sadržaja; gost na prijavu | ✔ |
| 10 | Upload uz prijavu | bez CSRF tokena; `shell.php`; `shell.jpg` s lažnim tipom; SVG; > 5 MB | 403 / 422, ništa spremljeno | ✔ |
| 10 | Pohranjeni XSS u prijavi | `<img onerror>` u naslovu, `<script>` u opisu, `"><svg onload>` u adresi | spremljeno, ali escapeano (i kod admina) | ✔ |
| 10 | SQLi u filtru statusa | `status=PRIJAVLJENO' OR '1'='1` | filtar ignoriran | ✔ |
| 11 | Brute force | 5 krivih lozinki pa ispravna | 429 + `Retry-After: 900` | ✔ |
| 11 | Brute force na nepostojeći račun | 5 pokušaja | 429 (ne otkriva postoji li račun) | ✔ |
| 11 | Password spraying | 20 pokušaja s iste IP adrese na razne račune | 429 i za ostale račune | ✔ |
| – | Stabilnost | svi gornji napadi | nijedna pogreška 500 u `storage/logs/app.log` | ✔ |

### Poznata ograničenja (svjesno prihvaćena)

- **Zaglavlje `Server`** otkriva verzije Apachea i PHP-a. Podešava se u konfiguraciji poslužitelja (`ServerTokens Prod`, `expose_php = Off`), a ne u aplikaciji.
- **Registracija otkriva postoji li e-mail** („Račun s ovom e-mail adresom već postoji”). To je uobičajen kompromis radi jasne poruke korisniku. Potpuna zaštita traži potvrdu e-mailom.
- **Kolačić nema `Secure`** na `http://localhost`. Na HTTPS-u se postavlja automatski (`Session::isHttps()`).
- **Namjerna blokada tuđeg računa** (5 krivih lozinki) traje 15 minuta. U produkciji bi CAPTCHA bila bolji izbor.
- **Ponovno kodiranje slika** (uklanjanje EXIF-a) nije uključeno, jer XAMPP nema GD.

### Ručno testiranje alatima (preporuka)

- **OWASP ZAP** (besplatan): „Automated Scan” na `http://localhost/zagreb-prijave` pronalazi XSS, nedostajuća zaglavlja i slično.
- **sqlmap**: `sqlmap -u "http://localhost/zagreb-prijave/vijesti?q=test" --batch` treba javiti da parametar nije ranjiv.
- **Alati za razvojne programere u pregledniku (F12)**: u kartici Application provjeriti kolačić `ZGPRIJAVE` (HttpOnly, SameSite), a u kartici Network statusne kodove.

## Funkcionalni testovi CMS-a – `tests/cms.sh`

Stanje nakon faze 4: **72 / 72 prošlo** (30. 10. 2026.)

| Područje | Scenariji | Dobiveno |
|---|---|---|
| Pristup i izbornik | admin vidi pregled s brojevima; urednica vidi „Moje vijesti” bez korisnika; admin vidi stupac autora; filtar nacrta; oznaka „povučeno” | ✔ |
| Validacija vijesti | prazan obrazac, prazan uređivač (`<p><br></p>`), kategorija 999, status `arhiva` → 422; upisane vrijednosti ostaju | ✔ |
| Životni ciklus vijesti | nacrt sa slikom (nije javan) → objava (datum postavljen, javno vidljiva, na početnoj) → zamjena slike (stara obrisana s diska) → povlačenje + uklanjanje slike (404, datum prve objave sačuvan, datoteka obrisana) | ✔ |
| Admin i tuđa vijest | admin uređuje Tinovu vijest; autor i datum objave ostaju; brisanje; ponovno brisanje → 404 | ✔ |
| Kategorije | dodavanje; duplikat (i malim slovima) i prekratak naziv → 422; preimenovanje; brisanje kategorije s vijestima odbijeno; prazna obrisana; nepostojeća → 404 | ✔ |
| Korisnici | filtri (uloga, deaktivirani, pretraga); promjena uloge (Luka postaje urednik i odmah ulazi u CMS); povratak na filtrirani popis; aktivacija računa; nepostojeći korisnik → 404 | ✔ |

## Funkcionalni testovi prijava – `tests/prijave.sh`

Stanje nakon faze 5: **62 / 62 prošlo** (30. 10. 2026.)

| Područje | Scenariji | Dobiveno |
|---|---|---|
| Pristup | gost na obrazac, popis i detalj → preusmjeravanje na prijavu | ✔ |
| Moje prijave | Ana vidi točno svojih 5 prijava, bez Lukinih; sličice idu kroz PHP; filtri `ZAVRSENO`, `DODIJELJENO`; nepoznat status ignoriran; poruka za korisnika bez prijava | ✔ |
| Detalj | podaci, hitnost, „Vi” u tijeku; slika `image/png` s `Cache-Control: private`; građanin NE vidi interni komentar, imena zaposlenika ni neodobrenu sliku „poslije” (ni izravnim URL-om → 404); kod ZAVRŠENE prijave vidi sliku „poslije”; admin vidi sve; radnik vidi svoju, a ne tuđu prijavu (403); nepostojeća prijava/slika → 404 | ✔ |
| Validacija | 17 četvrti + 9 vrsta u izbornicima; prazan obrazac, bez slike, četvrt 99 → 422 s porukama; upisano ostaje; ništa se ne sprema | ✔ |
| Uspješna prijava | status `PRIJAVLJENO`, hitnost `srednja`, vlasnik iz sesije (podmetnuto ignorirano); vrijeme sada; zapis slike i prvi zapis povijesti; datoteka izvan webroota s nasumičnim imenom; opis s `<br>`, `<script>` escapean; profil broji 6 prijava | ✔ |
| Transakcija | okidač u bazi ruši 3. upit → 500, a u bazi nema prijave ni slike, na disku nema datoteke (ROLLBACK) | ✔ |
| Zatrpavanje | 10. prijava u sat vremena prolazi, 11. → 429 s porukom | ✔ |

## Testovi prethodnih faza

Faze 2 i 3 testirane su privremenim cURL skriptama tijekom razvoja. Ponovno su pokrenute nakon faze 4 i nema regresija. Najvažniji sigurnosni slučajevi iz njih preneseni su u `tests/sigurnost.sh`.

### Faza 2 – prijava, registracija, uloge

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

### Faza 3 – javni dio i profil

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
