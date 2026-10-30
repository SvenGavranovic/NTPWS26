# REST API – Zagreb Prijave

Osnovna adresa: `http://localhost/zagreb-prijave/api/v1`

Sve putanje u nastavku navedene su relativno na tu adresu (npr. `GET /vijesti` = `GET http://localhost/zagreb-prijave/api/v1/vijesti`).

## Sadržaj
1. [Općenito](#općenito)
2. [Autentifikacija](#autentifikacija)
3. [Pregled krajnjih točaka](#pregled-krajnjih-točaka)
4. [Sesija](#sesija)
5. [Vijesti](#vijesti)
6. [Kategorije](#kategorije)
7. [Prijave problema](#prijave-problema)
8. [Radovi](#radovi)
9. [JSON Schema – valjan i nevaljan primjer](#json-schema--valjan-i-nevaljan-primjer)
10. [Statusni kodovi](#statusni-kodovi)

---

## Općenito

- Odgovori su uvijek JSON: `Content-Type: application/json; charset=utf-8`.
- Tijelo zahtjeva za `POST` i `PATCH` je JSON (`Content-Type: application/json`). Iznimka je `POST /prijave`, koji šalje datoteku (`multipart/form-data`).
- Datumi su u formatu ISO 8601 s vremenskom zonom: `2026-10-25T09:00:00+01:00`.
- URL-ovi u odgovorima su relativni na poslužitelj (`/zagreb-prijave/...`).

### Struktura odgovora

Uspjeh:
```json
{ "data": { ... } }
```
Popis (sa straničenjem):
```json
{
  "data": [ ... ],
  "meta": { "stranica": 1, "po_stranici": 10, "ukupno": 6, "ukupno_stranica": 1 }
}
```
Pogreška:
```json
{
  "greska": {
    "status": 400,
    "poruka": "Podaci ne odgovaraju shemi vijest-nova.",
    "detalji": { "naslov": "Must be at least 3 characters long" }
  }
}
```
`detalji` postoji samo kad se pogreška odnosi na pojedina polja.

### Straničenje

| Parametar | Opis |
|---|---|
| `stranica` | broj stranice, od 1; nepostojeća stranica daje najbližu postojeću |
| `po_stranici` | broj zapisa po stranici; zadano i najveće vrijednosti navedene su uz svaku krajnju točku |

---

## Autentifikacija

API koristi istu **sesiju** kao web aplikacija (kolačić `ZGPRIJAVE`, `HttpOnly`, `SameSite=Lax`) i **CSRF token** za svaki zahtjev koji mijenja podatke.

1. `GET /sesija` – stvara sesiju i vraća CSRF token.
2. `POST /sesija` s e-mailom i lozinkom i zaglavljem `X-CSRF-Token` – prijava. Odgovor sadrži **novi** CSRF token.
3. Svaki `POST`, `PATCH` i `DELETE` šalje kolačić sesije i zaglavlje `X-CSRF-Token: <token>`.
4. `DELETE /sesija` – odjava.

Primjer (Git Bash ili Linux, `curl`):
```bash
API=http://localhost/zagreb-prijave/api/v1

# 1. sesija i token
curl -c kolacici.txt $API/sesija
# → {"data":{"prijavljen":false,"korisnik":null,"csrf":"9f2c..."}}

# 2. prijava
curl -b kolacici.txt -c kolacici.txt \
     -H "Content-Type: application/json" -H "X-CSRF-Token: 9f2c..." \
     -d '{"email":"urednik.lea@zagreb-prijave.test","lozinka":"..."}' \
     $API/sesija
# → {"data":{"korisnik":{...,"uloga":"urednik"},"csrf":"5b71..."}}

# 3. zahtjev koji mijenja podatke
curl -b kolacici.txt -H "Content-Type: application/json" -H "X-CSRF-Token: 5b71..." \
     -X PATCH -d '{"status":"objavljeno"}' $API/vijesti/10
```

Pravila:
- Bez prijave: `401`. Prijavljen, ali bez ovlasti: `403`.
- Bez ispravnog CSRF tokena: `403` (i kad je kolačić sesije ispravan).
- Nakon 5 neuspjelih prijava za isti e-mail (ili 20 s iste IP adrese) u 15 minuta: `429` i `Retry-After: 900`.
- API nema CORS zaglavlja: preglednik ne dopušta drugim domenama čitanje odgovora.

---

## Pregled krajnjih točaka

| Metoda i putanja | Opis | Pristup |
|---|---|---|
| `GET /sesija` | stanje sesije i CSRF token | svi |
| `POST /sesija` | prijava | svi |
| `DELETE /sesija` | odjava | prijavljeni |
| `GET /vijesti` | objavljene vijesti (filtri, straničenje) | svi |
| `POST /vijesti` | nova vijest | urednik, admin |
| `GET /vijesti/{id}` | jedna objavljena vijest | svi |
| `PATCH /vijesti/{id}` | izmjena vijesti | autor (urednik), admin |
| `DELETE /vijesti/{id}` | brisanje vijesti | autor (urednik), admin |
| `GET /kategorije` | kategorije s brojem objavljenih vijesti | svi |
| `GET /prijave` | javni sažetak prijava (filtri, straničenje) | svi; `?moje=1` prijavljeni |
| `POST /prijave` | nova prijava sa slikom | prijavljeni |
| `GET /prijave/{id}` | detalj prijave | podnositelj, dodijeljeni radnik, admin |
| `PATCH /prijave/{id}` | dodjela, hitnost ili promjena statusa | ovisno o radnji |
| `GET /radovi` | radovi na infrastrukturi | svi |

`GET /promet` (TomTom) dodaje se uz kartu.

---

## Sesija

### `GET /sesija`
```json
{
  "data": {
    "prijavljen": true,
    "korisnik": { "id": 2, "ime": "Lea", "prezime": "Urednik", "email": "urednik.lea@zagreb-prijave.test", "uloga": "urednik" },
    "csrf": "f571e23c7ee059eca55b08f5f53cb4b996f06e4f46382663d36b51b9246f58f8"
  }
}
```

### `POST /sesija`
Tijelo (shema `schemas/sesija.json`):
```json
{ "email": "urednik.lea@zagreb-prijave.test", "lozinka": "..." }
```
Odgovori: `200` (`korisnik`, novi `csrf`), `400` (shema), `401` (neispravni podaci ili deaktiviran račun), `403` (CSRF), `429` (previše pokušaja).

### `DELETE /sesija`
`204 No Content`.

---

## Vijesti

API vraća **samo objavljene** vijesti. Nacrt i povučena vijest daju `404`, isto kao nepostojeća.

### `GET /vijesti`

| Parametar | Opis |
|---|---|
| `q` | pretraga u naslovu, sažetku i sadržaju (najviše 100 znakova) |
| `kategorija` | ID kategorije |
| `stranica`, `po_stranici` | zadano 10, najviše 50 |

```json
{
  "data": [
    {
      "id": 6,
      "naslov": "Raspored odvoza glomaznog otpada za studeni",
      "sazetak": "Objavljen je raspored odvoza glomaznog otpada po gradskim četvrtima.",
      "kategorija": { "id": 3, "naziv": "Komunalne usluge" },
      "autor": "Lea Urednik",
      "datum_objave": "2026-10-25T09:00:00+01:00",
      "slika": null,
      "url": "/zagreb-prijave/vijesti/6"
    }
  ],
  "meta": {
    "stranica": 1, "po_stranici": 1, "ukupno": 6, "ukupno_stranica": 6,
    "linkovi": {
      "prethodna": null,
      "sljedeca": "/zagreb-prijave/api/v1/vijesti?po_stranici=1&stranica=2"
    }
  }
}
```

### `GET /vijesti/{id}`
Isti oblik kao element popisa, uz polje `sadrzaj` (HTML, očišćen pri spremanju). Nepostojeća ili neobjavljena vijest:
```json
{ "greska": { "status": 404, "poruka": "Vijest ne postoji ili nije objavljena." } }
```

### `POST /vijesti`
Tijelo prema shemi [`schemas/vijest-nova.json`](../schemas/vijest-nova.json):

| Polje | Tip | Obavezno | Pravilo |
|---|---|---|---|
| `naslov` | string | da | 3–200 znakova |
| `sazetak` | string | da | 10–500 znakova |
| `sadrzaj` | string (HTML) | da | čisti se HTMLPurifierom (dopuštene oznake: `p, br, strong, em, u, s, h2, h3, ul, ol, li, blockquote, a`) |
| `kategorija_id` | integer | da | postojeća kategorija |
| `status` | `"nacrt"` \| `"objavljeno"` | ne | zadano `nacrt` |

Druga polja (npr. `autor_id`) nisu dopuštena. Autor je uvijek prijavljeni korisnik.

Odgovor `201 Created`, zaglavlje `Location: /zagreb-prijave/api/v1/vijesti/10`:
```json
{
  "data": {
    "id": 10, "naslov": "Zatvaranje Savske ceste", "sazetak": "Savska cesta bit će zatvorena u subotu.",
    "sadrzaj": "<p>Obilazak preko Vukovarske.</p>", "kategorija_id": 2, "autor_id": 2,
    "status": "nacrt", "datum_objave": null, "slika": null
  }
}
```
Pogreške: `400` (shema, nepostojeća kategorija, prazan sadržaj nakon čišćenja), `401`, `403` (uloga ili CSRF).

### `PATCH /vijesti/{id}`
Šalju se samo polja koja se mijenjaju (shema [`schemas/vijest-izmjena.json`](../schemas/vijest-izmjena.json), barem jedno polje). Urednik smije mijenjati samo vlastite vijesti (`403`). Prva objava postavlja `datum_objave`; vraćanje u `nacrt` povlači vijest s javnih stranica i iz API-ja.

```json
{ "status": "objavljeno" }
```
Odgovor `200` s istim oblikom kao kod `POST`.

### `DELETE /vijesti/{id}`
`204 No Content`. Briše se i naslovna slika. Tuđa vijest: `403`; nepostojeća: `404`.

---

## Kategorije

### `GET /kategorije`
```json
{
  "data": [
    { "id": 6, "naziv": "Arhiva", "broj_vijesti": 0 },
    { "id": 5, "naziv": "Događanja", "broj_vijesti": 1 }
  ]
}
```
`broj_vijesti` broji samo objavljene vijesti.

---

## Prijave problema

### `GET /prijave`
Javni sažetak **bez osobnih podataka** (nema opisa, prijavitelja ni slika); koristi ga karta.

| Parametar | Opis |
|---|---|
| `status` | `PRIJAVLJENO`, `DODIJELJENO`, `U_RADU`, `CEKA_PROVJERU`, `VRACENO`, `ZAVRSENO` |
| `cetvrt`, `vrsta` | ID gradske četvrti / vrste problema |
| `moje=1` | samo prijave prijavljenog korisnika (bez prijave `401`) |
| `stranica`, `po_stranici` | zadano 20, najviše 100 |

```json
{
  "data": [
    {
      "id": 2, "naslov": "Divlje odlagalište otpada", "status": "PRIJAVLJENO", "hitnost": "srednja",
      "vrsta": { "id": 3, "naziv": "Otpad i nečistoća" },
      "cetvrt": { "id": 16, "naziv": "Sesvete" },
      "lokacija": null,
      "kreirano_u": "2026-10-29T18:40:00+01:00"
    }
  ],
  "meta": { "stranica": 1, "po_stranici": 1, "ukupno": 10, "ukupno_stranica": 10 }
}
```
`lokacija` je `{"lat": ..., "lng": ...}` ili `null`.

### `GET /prijave/{id}`
Samo za podnositelja, dodijeljenog radnika i administratora (inače `403`; bez prijave `401`). Polja: `opis`, `adresa`, `slike` (`id`, `tip`, `url`), `povijest` (`iz`, `u`, `vrijeme`, `uloga`) i ostala polja sažetka.

Isto kao na webu:
- `prijavitelj`, `radnik`, `napomena_radnika` i `komentar` u povijesti vidi samo služba (admin i dodijeljeni radnik); građaninu su `null`;
- slika `poslije` vidljiva je građaninu tek kad je prijava `ZAVRSENO`.

### `POST /prijave`
`multipart/form-data` (kao web obrazac), uz zaglavlje `X-CSRF-Token` ili polje `_csrf`.

| Polje | Opis |
|---|---|
| `slika` | datoteka JPG, PNG ili WebP, najviše 5 MB (obavezno) |
| `cetvrt_id`, `vrsta_id` | ID iz šifrarnika (obavezno) |
| `naslov` | 5–150 znakova (obavezno) |
| `opis` | 10–2000 znakova, običan tekst (obavezno) |
| `adresa` | do 255 znakova |

Datum, hitnost (`srednja`) i status (`PRIJAVLJENO`) postavlja sustav. Odgovor `201` s detaljem prijave i zaglavljem `Location`. Pogreške: `400` (s `detalji`), `401`, `403` (CSRF), `429` (više od 10 prijava u sat vremena).

```bash
curl -b kolacici.txt -H "X-CSRF-Token: 5b71..." \
     -F cetvrt_id=4 -F vrsta_id=2 -F "naslov=Ne radi lampa" \
     -F "opis=Lampa ne svijetli tjedan dana." -F "slika=@foto.jpg" \
     $API/prijave
```

### `PATCH /prijave/{id}`
Jedna radnja po zahtjevu (shema [`schemas/prijava-izmjena.json`](../schemas/prijava-izmjena.json)):

| Tijelo | Radnja | Tko | Dopušteno iz statusa |
|---|---|---|---|
| `{"radnik_id": 4}` | dodjela ili preraspodjela | admin | PRIJAVLJENO, DODIJELJENO, U_RADU, VRACENO |
| `{"hitnost": "kriticna"}` | hitnost (`niska`, `srednja`, `visoka`, `kriticna`) | admin | sve osim ZAVRSENO |
| `{"status": "U_RADU"}` | početak rada | dodijeljeni radnik | DODIJELJENO, VRACENO |
| `{"status": "ZAVRSENO"}` | odobrenje | admin | CEKA_PROVJERU |
| `{"status": "VRACENO", "komentar": "..."}` | vraćanje na doradu (komentar 5–1000 znakova) | admin | CEKA_PROVJERU |

Slanje na provjeru (`CEKA_PROVJERU`) traži sliku pa ide kroz obrazac na stranici prijave, a ne kroz JSON.

Odgovori: `200` (detalj), `400` (shema, neispravan radnik), `403` (uloga ili tuđi zadatak), `404`, `409` (prijelaz nije dopušten iz trenutnog statusa ili se status u međuvremenu promijenio).

---

## Radovi

### `GET /radovi`

| Parametar | Opis |
|---|---|
| `aktivni=1` | samo radovi koji su u tijeku (počeli, a nisu završili) |
| `cetvrt` | ID gradske četvrti |
| `vrsta` | `cesta`, `vodovod`, `kanalizacija`, `plinovod`, `toplovod`, `elektroinstalacije`, `tramvajska_pruga`, `ostalo` |
| `stranica`, `po_stranici` | zadano 50, najviše 100 |

```json
{
  "data": [
    {
      "id": 3, "naslov": "Održavanje tramvajske pruge", "opis": "Zamjena dotrajalih tračnica.",
      "vrsta": "tramvajska_pruga", "utjecaj_na_promet": "djelomicna_zatvorenost",
      "cetvrt": { "id": 1, "naziv": "Donji grad" }, "adresa": null,
      "lokacija": { "lat": 45.805, "lng": 15.978 },
      "pocetak": "2026-11-10T22:00:00+01:00", "kraj": "2026-11-20T05:00:00+01:00"
    }
  ],
  "meta": { "stranica": 1, "po_stranici": 1, "ukupno": 5, "ukupno_stranica": 5 }
}
```

---

## JSON Schema – valjan i nevaljan primjer

Sheme su u mapi [`schemas/`](../schemas) (JSON Schema draft-07), a provjerava ih biblioteka `justinrainbow/json-schema` prije bilo kakve obrade:

| Shema | Za |
|---|---|
| `vijest-nova.json` | `POST /vijesti` |
| `vijest-izmjena.json` | `PATCH /vijesti/{id}` |
| `prijava-izmjena.json` | `PATCH /prijave/{id}` |
| `sesija.json` | `POST /sesija` |

### Valjan primjer (`POST /vijesti`)
```json
{
  "naslov": "Zatvaranje Savske ceste",
  "sazetak": "Savska cesta bit će zatvorena u subotu.",
  "sadrzaj": "<p>Obilazak preko Vukovarske.</p>",
  "kategorija_id": 2
}
```
Odgovor: `201 Created` (vidi gore).

### Nevaljan primjer
```json
{ "naslov": "Ab", "sazetak": 42, "kategorija_id": "dva", "status": "arhiva", "autor_id": 1 }
```
Odgovor `400 Bad Request`:
```json
{
  "greska": {
    "status": 400,
    "poruka": "Podaci ne odgovaraju shemi vijest-nova.",
    "detalji": {
      "sadrzaj": "The property sadrzaj is required",
      "naslov": "Must be at least 3 characters long",
      "sazetak": "Integer value found, but a string is required",
      "kategorija_id": "String value found, but an integer is required",
      "status": "Does not have a value in the enumeration [\"nacrt\",\"objavljeno\"]",
      "(tijelo)": "The property autor_id is not defined and the definition does not allow additional properties"
    }
  }
}
```
Poruke dolaze iz biblioteke i na engleskom su; ključ je naziv polja, a `(tijelo)` označava pogrešku na razini cijelog objekta.

Oba primjera automatski se provjeravaju u `tests/api.sh`.

---

## Statusni kodovi

| Kod | Kada |
|---|---|
| `200 OK` | uspješan GET, PATCH ili prijava |
| `201 Created` | stvoren resurs (`POST /vijesti`, `POST /prijave`); zaglavlje `Location` |
| `204 No Content` | uspješan DELETE |
| `400 Bad Request` | tijelo nije JSON, ne odgovara shemi ili ne prolazi validaciju |
| `401 Unauthorized` | potrebna je prijava ili su podaci za prijavu neispravni |
| `403 Forbidden` | nema ovlasti (uloga, tuđi zapis) ili CSRF token nije ispravan |
| `404 Not Found` | resurs ne postoji (ili je vijest neobjavljena) |
| `405 Method Not Allowed` | metoda nije podržana za putanju; zaglavlje `Allow` |
| `409 Conflict` | prijelaz statusa nije dopušten ili se status u međuvremenu promijenio |
| `429 Too Many Requests` | previše pokušaja prijave ili previše novih prijava; zaglavlje `Retry-After` |
| `500 Internal Server Error` | neočekivana pogreška; detalji samo u zapisniku poslužitelja |
