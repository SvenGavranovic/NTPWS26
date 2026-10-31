# Upute za korištenje – Zagreb Prijave

Aplikacija služi za prijavu komunalnih problema u Gradu Zagrebu i praćenje njihova rješavanja. Ovaj dokument opisuje što koja uloga može raditi i kako.

> Aplikacija je studentski projekt i **nije službena aplikacija Grada Zagreba**. Prijave se ne prosljeđuju gradskim službama.

Upute za instalaciju i pokretanje nalaze se u [README.md](../README.md).

## Uloge

| Uloga | Što može |
|---|---|
| Posjetitelj (bez prijave) | čitati objavljene vijesti, pretraživati ih i filtrirati po kategoriji; pregledati kartu s prijavama, radovima i stanjem prometa |
| Korisnik | sve što i posjetitelj; prijaviti problem s fotografijom; pratiti vlastite prijave; urediti profil i lozinku |
| Radnik | sve što i korisnik; vidjeti dodijeljene zadatke na kanban ploči; započeti rad; poslati obavljeni posao na provjeru |
| Urednik | sve što i korisnik; pisati, objavljivati i uređivati **vlastite** vijesti |
| Administrator | sve vijesti, kategorije i korisnike; dodjelu prijava radnicima, hitnost, odobravanje ili vraćanje obavljenog posla |

Registracijom se uvijek dobiva uloga **korisnik**. Ostale uloge dodjeljuje administrator.

---

## Posjetitelj

### Vijesti
- **Vijesti** u izborniku prikazuju objavljene vijesti, najnovije prve, po 4 na stranici.
- **Pretraga** traži u naslovu, sažetku i tekstu vijesti. Rezultati se osvježavaju već dok tipkate; velika i mala slova nisu bitna.
- Klik na **kategoriju** s lijeve strane prikazuje samo vijesti iz nje. Pretraga i kategorija mogu se kombinirati.
- Klik na naslov otvara cijelu vijest.

### Karta
**Karta** u izborniku prikazuje Zagreb sa slojevima koje uključujete i isključujete u gornjem desnom kutu:

| Sloj | Što prikazuje |
|---|---|
| Prijave problema | prijave s označenom lokacijom: sivo prijavljeno, plavo u obradi, zeleno riješeno |
| Radovi | radovi na infrastrukturi: narančasto u tijeku, ljubičasto planirani |
| Promet: incidenti | nesreće, radovi na cesti, zatvorene ceste i gužve (žuto manji, crveno veći zastoji) |
| Promet: protok | brzina na glavnim prometnicama: zeleno slobodno, narančasto usporeno, crveno gužva |

Klik na oznaku otvara detalje. Kod incidenata u prometu prikazuje se i od kada traju, najavljeni kraj i (ako postoji) zadnja potvrda; događaji koji traju dulje od 30 dana bez najavljenog kraja crtkani su i označeni kao moguće zastarjeli. Sloj **Radovi: završeni** (sivo) isključen je po zadanom i pokazuje povijest radova.

Iznad karte piše vrijeme zadnjeg uspješnog dohvata podataka o prometu, koji se osvježavaju automatski (oko svakih 90 sekundi). Ako servis za promet trenutno nije dostupan, prikazuju se zadnji dohvaćeni podaci uz upozorenje; **testni podaci** uvijek su jasno označeni žutim upozorenjem. Podaci o prometu: © TomTom; karta: © OpenStreetMap suradnici.

### Registracija i prijava
1. **Registracija** (gornji desni kut): ime, prezime, e-mail i lozinka.
   Lozinka mora imati najmanje 8 znakova, barem jedno slovo i jednu znamenku.
2. **Prijava**: e-mail i lozinka.
   Nakon 5 neuspješnih pokušaja za isti e-mail prijava je blokirana 15 minuta.
3. **Odjava**: gumb **Odjava** u zaglavlju.

---

## Korisnik

### Prijava problema
1. Kliknite **+ Prijavi problem** u zaglavlju.
2. Odaberite **fotografiju** problema (JPG, PNG ili WebP, najviše 5 MB). Pregled se prikaže ispod polja.
3. Odaberite **gradsku četvrt** i **vrstu problema**.
4. Upišite **kratki naslov** (npr. „Rupa na kolniku”) i **opis**: što je problem i gdje se točno nalazi.
5. Po želji označite **lokaciju na karti** (klik na kartu ili gumb **Moja lokacija** na mobitelu) i upišite **adresu** ili opis lokacije. Prijave s lokacijom vide se na javnoj karti.
6. Kliknite **Pošalji prijavu**.

Datum i vrijeme prijave bilježe se automatski. Hitnost procjenjuje služba. U jednom satu moguće je poslati najviše 10 prijava.

Ako neko polje nije ispravno, obrazac se ponovno prikaže s porukom uz to polje. Fotografiju tada treba ponovno odabrati.

### Praćenje prijava
- **Moje prijave** (izbornik ili profil) prikazuju sve vaše prijave sa statusom. Popis se može filtrirati po statusu.
- Klik na prijavu otvara detalj: fotografije, podatke i **tijek prijave** (kada se status promijenio).

| Status | Značenje |
|---|---|
| Prijavljeno | prijava je zaprimljena i čeka obradu |
| Dodijeljeno | prijava je dodijeljena radniku |
| U radu | radnik rješava problem |
| Čeka provjeru | radnik je završio, služba provjerava obavljeni posao |
| Vraćeno na doradu | služba je vratila posao radniku |
| Završeno | problem je riješen; prikazuje se i fotografija nakon popravka |

Opis, fotografije i tijek prijave vide samo podnositelj, administrator i radnik kojem je prijava dodijeljena. Na javnoj karti prikazuju se samo naslov, vrsta problema, četvrt, status i lokacija – bez opisa, fotografija i podataka o podnositelju.

### Profil
**Moj profil** (klik na ime u zaglavlju):
- promjena imena i prezimena;
- promjena lozinke (traži se trenutna lozinka);
- broj poslanih prijava i poveznica na njih.

E-mail i ulogu može promijeniti samo administrator.

---

## Radnik

### Kanban ploča
**Moji zadaci** u izborniku otvaraju ploču sa stupcima:

| Stupac | Što je u njemu |
|---|---|
| Novi zadaci | prijave koje su vam dodijeljene |
| Vraćeno na doradu | posao koji administrator nije prihvatio (razlog je na detalju prijave) |
| U radu | zadaci na kojima radite |
| Čeka provjeru | posao poslan administratoru na provjeru |
| Završeno (14 dana) | odobreni poslovi iz zadnja dva tjedna |

Kartice su poredane po **hitnosti** (kritično na vrhu); boja lijevog ruba označava hitnost.

### Početak rada
- Povucite karticu iz „Novi zadaci” ili „Vraćeno na doradu” u stupac **U radu**, ili
- kliknite **Započni rad** na kartici ili na detalju prijave.

### Slanje na provjeru
1. Povucite karticu u **Čeka provjeru** ili kliknite **Pošalji na provjeru**. Otvara se detalj prijave.
2. Odaberite **fotografiju stanja nakon popravka** (obavezno).
3. Upišite **napomenu** o obavljenom poslu (obavezno, najmanje 10 znakova).
4. Kliknite **Pošalji na provjeru**.

Radnik ne može sam zatvoriti prijavu. Prijavu zatvara administrator nakon provjere. Ako posao vrati, zadatak se pojavljuje u stupcu „Vraćeno na doradu” s razlogom vraćanja.

Radnik vidi i mijenja samo zadatke koji su dodijeljeni njemu.

---

## Urednik

**Moje vijesti** u izborniku:
- **+ Nova vijest**: naslov, sažetak (za popis vijesti), sadržaj u uređivaču teksta, kategorija i naslovna slika (JPG, PNG ili WebP, najviše 2 MB).
- Uređivač nudi naslove, podebljano, kurziv, podcrtano, precrtano, liste, citat i poveznice.
- **Status**:
  - *Nacrt*: vijest nije vidljiva javnosti;
  - *Objavljeno*: vijest je javna. Datum objave bilježi se pri prvoj objavi.
- **Povlačenje** objavljene vijesti: vratite status na *Nacrt*.
- **Slika**: nova slika zamjenjuje staru; kvačica *Ukloni sliku* briše je.
- **Brisanje**: gumb **Obriši** (traži potvrdu, ne može se poništiti).

Urednik vidi i uređuje samo vlastite vijesti.

---

## Administrator

**Administracija** u izborniku otvara pregled s brojem novih prijava, prijava koje čekaju provjeru, vijesti i korisnika.

### Prijave
**Administracija → Prijave**:
- gumbi na vrhu filtriraju po statusu i pokazuju broj prijava u svakom;
- pretraga po naslovu te filtri po statusu, hitnosti i četvrti;
- na vrhu popisa su prijave koje čekaju provjeru, zatim neriješene po hitnosti.

Na detalju prijave nalazi se okvir **Radnje administratora**:

| Radnja | Kada je dostupna |
|---|---|
| **Dodijeli** radniku | prijava je tek prijavljena; uz ime radnika piše broj njegovih otvorenih zadataka |
| **Preraspodijeli** drugom radniku | prijava je dodijeljena, u radu ili vraćena |
| **Hitnost** (niska, srednja, visoka, kritična) | dok prijava nije završena |
| **Odobri** | prijava čeka provjeru; nakon pregleda fotografije i napomene radnika |
| **Vrati radniku na doradu** | prijava čeka provjeru; razlog je obavezan i vidi ga radnik |

Svaka promjena statusa bilježi se u tijeku prijave (tko, kada, iz kojeg u koji status, komentar). Podnositelj vidi tijek statusa, ali ne i interne komentare.

### Vijesti
**Administracija → Vijesti**: isto kao kod urednika, ali za **sve** vijesti. Autor vijesti se pri uređivanju ne mijenja.

### Radovi
**Administracija → Radovi**:
- popis svih radova sa stanjem (planirano, u tijeku, završeno); filtar „Samo u tijeku”;
- **+ Novi radovi**: naslov, vrsta, utjecaj na promet, početak i (ako je poznat) kraj, četvrt, adresa, opis i **lokacija** – klik na kartu postavlja oznaku, koju je moguće i povući;
- lokacija mora biti na području Grada Zagreba, a kraj ne smije biti prije početka;
- planirani radovi i radovi u tijeku prikazuju se na javnoj karti.

### Promet
**Administracija → Promet** prikazuje trenutne incidente iz TomToma, od najstarijeg. Podatke TomToma aplikacija ne može ispraviti, ali incident za koji pouzdano znate da ne odgovara stvarnosti (npr. cesta je ponovno otvorena) možete **sakriti**: upišite razlog i kliknite **Sakrij**. Skriveni incident ne prikazuje se na karti ni u API-ju, a karta navodi koliko je incidenata skriveno. Popis skrivenih (s razlogom, osobom i vremenom) nalazi se ispod; **Vrati na kartu** poništava skrivanje.

### Kategorije
**Administracija → Kategorije**:
- dodavanje nove kategorije (naziv mora biti jedinstven);
- **Uredi** popunjava obrazac podacima kategorije;
- **Obriši** je moguće samo za kategoriju bez vijesti (ni objavljenih ni nacrta).

### Programski pristup (REST API)
Svi podaci dostupni su i preko JSON API-ja na `/api/v1/` (vijesti, kategorije, prijave, radovi). Opis krajnjih točaka, prijave i primjeri su u [API.md](API.md).

### Korisnici
**Administracija → Korisnici**:
- pretraga po imenu ili e-mailu, filtri po ulozi i statusu računa;
- u retku korisnika: odabir **uloge** i prekidač **aktivan**, zatim **Spremi**;
- deaktiviran korisnik odmah je odjavljen na svim uređajima i ne može se prijaviti;
- vlastiti račun nije moguće mijenjati (sustav uvijek ima barem jednog administratora).
