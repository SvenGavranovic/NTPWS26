# Prijedlog semestralnog projekta

**Kolegij:** Napredno web projektiranje web servisa
**Student:** [Ime i prezime], [JMBAG]
**Oblik rada:** samostalno
**Repozitorij:** [poveznica na GitHub repozitorij]

---

## 1. Naziv aplikacije

**Zagreb Prijave** – web aplikacija za prijavu komunalnih problema i praćenje radova i prometa u Gradu Zagrebu

## 2. Opis problema

Građani svakodnevno uočavaju komunalne probleme: oštećen kolnik, neispravnu javnu rasvjetu, divlja odlagališta otpada, srušenu prometnu signalizaciju. Nemaju jednostavan način da ih prijave i prate što se s prijavom događa. Istodobno nemaju pregled na jednom mjestu gdje se izvode radovi na gradskoj infrastrukturi i kakvo je stanje u prometu.

Aplikacija rješava oba problema za područje **Grada Zagreba**:

- građanin prijavljuje problem s fotografijom, gradskom četvrti i opisom te prati njegov status;
- gradska služba (administrator) određuje hitnost i dodjeljuje prijavu radniku;
- radnik rješava zadatak i šalje dokaz (fotografiju „poslije”) na provjeru;
- administrator odobrava završetak ili vraća zadatak na doradu. Svaka promjena se bilježi;
- na karti su prikazane prijave, radovi i trenutno stanje prometa.

Kontrolom kvalitete (radnik ne može sam zatvoriti zadatak) i potpunom povijesti promjena postupak je transparentan i provjerljiv.

## 3. Ciljani korisnici i uloge

| Uloga | Opis i ovlasti |
|---|---|
| Posjetitelj | Pregled objavljenih vijesti, karte prijava i radova te stanja prometa |
| Registrirani korisnik (građanin) | Prijava problema s fotografijom, pregled vlastitih prijava i njihova statusa, uređivanje profila, promjena lozinke |
| Radnik | Pregled dodijeljenih zadataka po hitnosti (kanban), promjena statusa, slanje završetka na provjeru |
| Urednik | Izrada i uređivanje vlastitih vijesti |
| Administrator | Upravljanje svim vijestima, kategorijama, korisnicima i ulogama, aktivacija i deaktivacija računa, dodjela zadataka i hitnosti, unos radova, odobrenje ili vraćanje završenih zadataka |

Registracijom se dobiva samo uloga registriranog korisnika. Ostale uloge dodjeljuje administrator.

## 4. Planirane funkcionalnosti

**Javni dio**
- početna stranica s izdvojenim vijestima i kartom;
- popis objavljenih vijesti s filtriranjem po kategoriji, pretraživanjem i straničenjem;
- detaljni prikaz vijesti;
- interaktivna karta Zagreba (prijave, radovi na infrastrukturi, promet);
- stranica o projektu, registracija, prijava i profil korisnika.

**Prijave problema**
- obrazac za prijavu: fotografija, gradska četvrt (17 službenih četvrti), vrsta problema, opis, opcionalno točka na karti;
- automatsko bilježenje datuma, vremena i zadane hitnosti;
- tok statusa: *Prijavljeno → Dodijeljeno → U radu → Čeka provjeru → Završeno* (uz mogućnost *Vraćeno* radniku);
- prijelazi statusa provjeravaju se na poslužitelju, a svaka promjena se zapisuje (tko, kada, iz kojeg u koji status, komentar).

**CMS i administracija**
- vijesti: uređivač obogaćenog teksta, slika, nacrt i objava, povlačenje, brisanje uz potvrdu;
- kategorije vijesti (kategorija s vijestima ne može se obrisati);
- korisnici: promjena uloge, aktivacija i deaktivacija (prekida i otvorene sesije);
- kanban ploča za radnike i administratora;
- unos radova na infrastrukturi (lokacija, vrsta, razdoblje, utjecaj na promet).

**Vlastiti REST API (JSON, `/api/v1/`)**
- vijesti (GET, POST, PATCH, DELETE), kategorije, prijave, radovi i promet;
- straničenje, filtriranje, validacija, autentifikacija i autorizacija za izmjene, dosljedni HTTP statusni kodovi;
- JSON Schema za unos vijesti s primjerom valjanog i nevaljanog zahtjeva;
- javni dio aplikacije stvarno koristi API (popis vijesti, markeri na karti).

## 5. Tehnologije

| Sloj | Tehnologija |
|---|---|
| Backend | PHP 8.3, vlastita MVC struktura (front controller, router), PDO |
| Baza podataka | MariaDB 10.11 (XAMPP) |
| Biblioteke (Composer) | `vlucas/phpdotenv` (konfiguracija), `ezyang/htmlpurifier` (sanitizacija HTML-a), `justinrainbow/json-schema` (validacija API zahtjeva) |
| Frontend | Bootstrap 5, vanilla JavaScript (Fetch API), Quill ili TinyMCE, SortableJS (kanban), Leaflet + OpenStreetMap (karta) |
| Razvojno okruženje | VS Code, XAMPP, Git i GitHub |

Sigurnosne mjere: `password_hash()`/`password_verify()`, pripremljeni SQL upiti, CSRF tokeni, obnova ID-a sesije pri prijavi, escaping izlaza, HTMLPurifier, provjera MIME tipa i veličine prenesenih datoteka, tajne isključivo u `.env` datoteci.

## 6. Vanjski servis

**TomTom Traffic API** (incidenti u prometu i protok prometa) za prikaz stanja prometa na karti Zagreba.

- Poziv ide isključivo preko PHP proxyja na poslužitelju (`/api/v1/promet`), pa API ključ nikad ne dolazi u preglednik ni u repozitorij.
- Zahtjev ima vremensko ograničenje, a odgovor se privremeno sprema (60–120 s) kako se ne bi prekoračila ograničenja besplatnog plana.
- Obrađuju se uspješan odgovor, nedostupnost servisa i odgovor neočekivane strukture. Korisniku se prikazuje vrijeme posljednjeg uspješnog dohvata.
- Prije implementacije provjerit ću aktualne uvjete korištenja i ograničenja besplatnog plana.

**Opcionalno:** Nominatim (OpenStreetMap) za pretvaranje adrese u koordinate, uz poštivanje ograničenja od jednog zahtjeva u sekundi.

---

*U izradi projekta koristi se AI alat (Claude Code) kao pomoć pri planiranju i pisanju koda. Sav kod student pregledava, razumije i prilagođava. Detalji su navedeni u dokumentaciji projekta.*
