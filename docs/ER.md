# ER dijagram – Zagreb Prijave

Dijagram je napisan u Mermaid formatu (GitHub i VS Code ga prikazuju kao sliku).
Za predaju se može izvesti u `docs/ER.png` preko https://mermaid.live (Actions → PNG).

Oznake: `PK` primarni ključ, `FK` strani ključ, `UK` jedinstveni ključ.
Kardinalnost: `||` točno jedan, `o|` nula ili jedan, `o{` nula ili više, `|{` jedan ili više.

```mermaid
erDiagram
    uloge ||--o{ korisnici : "ima"
    korisnici ||--o{ vijesti : "autor"
    kategorije_vijesti ||--o{ vijesti : "sadrži"

    korisnici ||--o{ prijave : "prijavljuje"
    gradske_cetvrti ||--o{ prijave : "lokacija"
    vrste_problema ||--o{ prijave : "vrsta"

    prijave ||--o{ prijava_slike : "ima slike"
    korisnici ||--o{ prijava_slike : "učitao"

    prijave ||--o| zadaci : "dodjela"
    korisnici ||--o{ zadaci : "radnik"
    korisnici ||--o{ zadaci : "dodijelio (admin)"

    prijave ||--|{ povijest_statusa : "povijest"
    korisnici ||--o{ povijest_statusa : "promijenio"

    gradske_cetvrti |o--o{ radovi : "lokacija"
    korisnici ||--o{ radovi : "unio (admin)"

    uloge {
        tinyint id PK
        varchar kod UK "korisnik, radnik, urednik, admin"
        varchar naziv
        varchar opis
    }
    korisnici {
        int id PK
        varchar ime
        varchar prezime
        varchar email UK
        varchar lozinka_hash
        tinyint uloga_id FK
        tinyint aktivan "0 ili 1"
        datetime zadnja_prijava
        datetime kreirano_u
        datetime azurirano_u
    }
    kategorije_vijesti {
        smallint id PK
        varchar naziv UK
        varchar opis
        datetime kreirano_u
        datetime azurirano_u
    }
    vijesti {
        int id PK
        smallint kategorija_id FK
        int autor_id FK
        varchar naslov
        varchar sazetak
        mediumtext sadrzaj "pročišćeni HTML"
        varchar slika
        enum status "nacrt, objavljeno"
        datetime datum_objave
        datetime kreirano_u
        datetime azurirano_u
    }
    gradske_cetvrti {
        tinyint id PK
        varchar naziv UK
    }
    vrste_problema {
        smallint id PK
        varchar naziv UK
        varchar opis
    }
    prijave {
        int id PK
        int korisnik_id FK
        tinyint cetvrt_id FK
        smallint vrsta_id FK
        varchar naslov
        text opis
        varchar adresa
        decimal lat
        decimal lng
        enum hitnost "niska ... kriticna"
        enum status "PRIJAVLJENO ... ZAVRSENO"
        datetime kreirano_u
        datetime azurirano_u
    }
    prijava_slike {
        int id PK
        int prijava_id FK
        int ucitao_id FK
        enum tip "prije, poslije"
        varchar datoteka UK
        varchar mime_tip
        int velicina
        datetime kreirano_u
    }
    zadaci {
        int id PK
        int prijava_id FK,UK
        int radnik_id FK
        int dodijelio_id FK
        text napomena_radnika
        datetime dodijeljeno_u
        datetime azurirano_u
    }
    povijest_statusa {
        int id PK
        int prijava_id FK
        int korisnik_id FK
        enum iz_statusa
        enum u_status
        text komentar
        datetime kreirano_u
    }
    radovi {
        int id PK
        varchar naslov
        text opis
        enum vrsta
        enum utjecaj_na_promet
        tinyint cetvrt_id FK
        varchar adresa
        decimal lat
        decimal lng
        datetime pocetak
        datetime kraj
        int unio_id FK
        datetime kreirano_u
        datetime azurirano_u
    }
```

## Pravila brisanja (ON DELETE)

| Strani ključ | Pravilo | Zašto |
|---|---|---|
| `korisnici.uloga_id → uloge` | RESTRICT | Uloga koju netko ima ne smije nestati. |
| `vijesti.kategorija_id → kategorije_vijesti` | RESTRICT | **Kategorija s vijestima se ne može obrisati** (zahtjev kolegija). |
| `vijesti.autor_id`, `prijave.korisnik_id`, `zadaci.radnik_id`, `zadaci.dodijelio_id`, `povijest_statusa.korisnik_id`, `prijava_slike.ucitao_id`, `radovi.unio_id → korisnici` | RESTRICT | Korisnici se **deaktiviraju, a ne brišu**, kako se ne bi izgubio trag tko je što napravio. |
| `prijave.cetvrt_id`, `prijave.vrsta_id`, `radovi.cetvrt_id` | RESTRICT | Šifrarnik koji se koristi ne smije se obrisati. |
| `prijava_slike`, `zadaci`, `povijest_statusa → prijave` | CASCADE | Ti zapisi nemaju smisla bez prijave. Ako admin obriše prijavu (npr. spam), brišu se i oni. Datoteke slika s diska briše PHP. |

Za sve strane ključeve vrijedi `ON UPDATE CASCADE`. Primarni ključevi se u praksi ne mijenjaju, pa je to samo sigurnosna mreža.

## Tok statusa prijave (state machine)

```mermaid
stateDiagram-v2
    [*] --> PRIJAVLJENO : korisnik kreira prijavu
    PRIJAVLJENO --> DODIJELJENO : admin dodjeljuje radniku
    DODIJELJENO --> U_RADU : dodijeljeni radnik
    U_RADU --> CEKA_PROVJERU : radnik (obavezna slika "poslije" + napomena)
    CEKA_PROVJERU --> ZAVRSENO : admin odobrava
    CEKA_PROVJERU --> VRACENO : admin vraća (obavezan komentar)
    VRACENO --> U_RADU : dodijeljeni radnik
    ZAVRSENO --> [*]
```
