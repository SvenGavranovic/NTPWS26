-- =====================================================================
-- Zagreb Prijave – shema baze podataka
-- Ciljni sustav: MariaDB 10.11 (XAMPP), kompatibilno s MySQL 8.0.16+
--
-- UPOZORENJE: skripta BRIŠE postojeće tablice i ponovno ih stvara.
-- Namijenjena je razvoju i demonstraciji. Redoslijed: schema.sql → seed.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS zagreb_prijave
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_croatian_ci;

USE zagreb_prijave;

SET NAMES utf8mb4;

-- Privremeno isključujemo provjeru stranih ključeva samo da bi se tablice
-- mogle obrisati bez obzira na redoslijed. Na kraju se ponovno uključuje.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pokusaji_prijave;
DROP TABLE IF EXISTS povijest_statusa;
DROP TABLE IF EXISTS prijava_slike;
DROP TABLE IF EXISTS zadaci;
DROP TABLE IF EXISTS prijave;
DROP TABLE IF EXISTS radovi;
DROP TABLE IF EXISTS vrste_problema;
DROP TABLE IF EXISTS gradske_cetvrti;
DROP TABLE IF EXISTS vijesti;
DROP TABLE IF EXISTS kategorije_vijesti;
DROP TABLE IF EXISTS korisnici;
DROP TABLE IF EXISTS uloge;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. ULOGE
-- Šifrarnik uloga. Aplikacija u kodu provjerava stupac `kod`
-- (npr. 'admin'), a ne `id`, kako bi provjere bile čitljive.
-- ---------------------------------------------------------------------
CREATE TABLE uloge (
  id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kod   VARCHAR(20)      NOT NULL,
  naziv VARCHAR(50)      NOT NULL,
  opis  VARCHAR(255)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uloge_kod (kod)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. KORISNICI
-- Jedan korisnik ima točno jednu ulogu.
-- E-mail je UNIQUE; collation *_ci je neosjetljiv na velika/mala slova,
-- pa su 'Ana@x.hr' i 'ana@x.hr' duplikat i baza ih odbija.
-- Korisnici se ne brišu nego deaktiviraju (aktivan = 0), jer su vezani
-- uz vijesti, prijave i povijest statusa koje moraju ostati sačuvane.
-- ---------------------------------------------------------------------
CREATE TABLE korisnici (
  id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  ime            VARCHAR(50)      NOT NULL,
  prezime        VARCHAR(50)      NOT NULL,
  email          VARCHAR(255)     NOT NULL,
  lozinka_hash   VARCHAR(255)     NOT NULL,   -- rezultat password_hash()
  uloga_id       TINYINT UNSIGNED NOT NULL,
  aktivan        TINYINT(1)       NOT NULL DEFAULT 1,
  zadnja_prijava DATETIME         NULL,
  kreirano_u     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  azurirano_u    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_korisnici_email (email),
  KEY idx_korisnici_uloga (uloga_id),
  CONSTRAINT fk_korisnici_uloga
    FOREIGN KEY (uloga_id) REFERENCES uloge (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,     -- ne može se obrisati uloga koju netko ima
  CONSTRAINT chk_korisnici_aktivan CHECK (aktivan IN (0, 1))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. KATEGORIJE VIJESTI
-- ---------------------------------------------------------------------
CREATE TABLE kategorije_vijesti (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  naziv       VARCHAR(80)       NOT NULL,
  opis        VARCHAR(255)      NULL,
  kreirano_u  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  azurirano_u DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kategorije_naziv (naziv)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. VIJESTI
-- status: 'nacrt' (nije javno) ili 'objavljeno' (javno).
-- "Povlačenje" vijesti = vraćanje statusa u 'nacrt'.
-- sadrzaj sadrži HTML iz rich-text uređivača, ALI tek nakon što ga
-- HTMLPurifier očisti (sanitizacija se radi u PHP-u prije spremanja).
-- slika: samo naziv datoteke (nasumično ime), datoteka je na disku.
-- ---------------------------------------------------------------------
CREATE TABLE vijesti (
  id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  kategorija_id SMALLINT UNSIGNED NOT NULL,
  autor_id      INT UNSIGNED      NOT NULL,
  naslov        VARCHAR(200)      NOT NULL,
  sazetak       VARCHAR(500)      NOT NULL,
  sadrzaj       MEDIUMTEXT        NOT NULL,
  slika         VARCHAR(255)      NULL,
  status        ENUM('nacrt','objavljeno') NOT NULL DEFAULT 'nacrt',
  datum_objave  DATETIME          NULL,       -- postavlja se pri prvoj objavi
  kreirano_u    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  azurirano_u   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vijesti_kategorija (kategorija_id),
  KEY idx_vijesti_autor (autor_id),
  KEY idx_vijesti_status_datum (status, datum_objave),  -- javni popis: WHERE status='objavljeno' ORDER BY datum_objave
  CONSTRAINT fk_vijesti_kategorija
    FOREIGN KEY (kategorija_id) REFERENCES kategorije_vijesti (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,     -- kategorija s vijestima se NE MOŽE obrisati
  CONSTRAINT fk_vijesti_autor
    FOREIGN KEY (autor_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  -- objavljena vijest mora imati datum objave
  CONSTRAINT chk_vijesti_objava CHECK (status <> 'objavljeno' OR datum_objave IS NOT NULL)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. GRADSKE ČETVRTI (šifrarnik, 17 službenih četvrti Grada Zagreba)
-- ---------------------------------------------------------------------
CREATE TABLE gradske_cetvrti (
  id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  naziv VARCHAR(60)      NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cetvrti_naziv (naziv)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. VRSTE PROBLEMA (šifrarnik: rupa na cesti, javna rasvjeta, ...)
-- ---------------------------------------------------------------------
CREATE TABLE vrste_problema (
  id    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  naziv VARCHAR(80)       NOT NULL,
  opis  VARCHAR(255)      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vrste_problema_naziv (naziv)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PRIJAVE PROBLEMA
-- Status je zapisan SAMO ovdje (jedan izvor istine).
-- Redoslijed vrijednosti u ENUM-u hitnosti je namjeran: MySQL sortira
-- ENUM po indeksu, pa ORDER BY hitnost DESC daje kriticna → niska.
-- lat/lng su opcionalni (korisnik može označiti točku na karti).
-- CHECK na koordinatama je široka "zadnja linija obrane" – preciznu
-- provjeru granica Zagreba radi PHP.
-- ---------------------------------------------------------------------
CREATE TABLE prijave (
  id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  korisnik_id INT UNSIGNED      NOT NULL,   -- tko je prijavio
  cetvrt_id   TINYINT UNSIGNED  NOT NULL,
  vrsta_id    SMALLINT UNSIGNED NOT NULL,
  naslov      VARCHAR(150)      NOT NULL,   -- kratki naslov (kartica na kanbanu)
  opis        TEXT              NOT NULL,   -- običan tekst, NE HTML
  adresa      VARCHAR(255)      NULL,
  lat         DECIMAL(9,6)      NULL,
  lng         DECIMAL(9,6)      NULL,
  hitnost     ENUM('niska','srednja','visoka','kriticna') NOT NULL DEFAULT 'srednja',
  status      ENUM('PRIJAVLJENO','DODIJELJENO','U_RADU','CEKA_PROVJERU','VRACENO','ZAVRSENO')
                                NOT NULL DEFAULT 'PRIJAVLJENO',
  kreirano_u  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  azurirano_u DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prijave_korisnik (korisnik_id),
  KEY idx_prijave_cetvrt (cetvrt_id),
  KEY idx_prijave_vrsta (vrsta_id),
  KEY idx_prijave_status_hitnost (status, hitnost),
  CONSTRAINT fk_prijave_korisnik
    FOREIGN KEY (korisnik_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_prijave_cetvrt
    FOREIGN KEY (cetvrt_id) REFERENCES gradske_cetvrti (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_prijave_vrsta
    FOREIGN KEY (vrsta_id) REFERENCES vrste_problema (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  -- obje koordinate ili nijedna
  CONSTRAINT chk_prijave_koord_par CHECK ((lat IS NULL) = (lng IS NULL)),
  CONSTRAINT chk_prijave_koord_zg  CHECK (lat IS NULL OR (lat BETWEEN 45.60 AND 46.00 AND lng BETWEEN 15.75 AND 16.30))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. SLIKE PRIJAVE
-- tip 'prije'  = slika koju šalje građanin uz prijavu
-- tip 'poslije' = obavezna slika radnika pri slanju na provjeru
-- Slike nemaju smisla bez prijave → ON DELETE CASCADE
-- (fizičke datoteke s diska briše PHP).
-- ---------------------------------------------------------------------
CREATE TABLE prijava_slike (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  prijava_id   INT UNSIGNED  NOT NULL,
  ucitao_id    INT UNSIGNED  NOT NULL,      -- tko je učitao sliku
  tip          ENUM('prije','poslije') NOT NULL,
  datoteka     VARCHAR(255)  NOT NULL,      -- nasumično generirano ime
  mime_tip     VARCHAR(50)   NOT NULL,      -- provjeren na serveru (finfo)
  velicina     INT UNSIGNED  NOT NULL,      -- u bajtovima
  kreirano_u   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prijava_slike_datoteka (datoteka),
  KEY idx_prijava_slike_prijava (prijava_id, tip),
  KEY idx_prijava_slike_ucitao (ucitao_id),
  CONSTRAINT fk_prijava_slike_prijava
    FOREIGN KEY (prijava_id) REFERENCES prijave (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_prijava_slike_ucitao
    FOREIGN KEY (ucitao_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. ZADACI
-- Dodjela prijave radniku. Jedna prijava ima najviše jedan zadatak
-- (UNIQUE prijava_id). Ako admin preraspodijeli posao, mijenja se
-- radnik_id u istom retku; trag ostaje u povijest_statusa.
-- Status se NE duplicira ovdje – čita se iz prijave (JOIN).
-- ---------------------------------------------------------------------
CREATE TABLE zadaci (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prijava_id       INT UNSIGNED NOT NULL,
  radnik_id        INT UNSIGNED NOT NULL,
  dodijelio_id     INT UNSIGNED NOT NULL,   -- administrator koji je dodijelio
  napomena_radnika TEXT         NULL,       -- obavezna pri prijelazu u CEKA_PROVJERU (provjera u PHP-u)
  dodijeljeno_u    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  azurirano_u      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_zadaci_prijava (prijava_id),
  KEY idx_zadaci_radnik (radnik_id),
  KEY idx_zadaci_dodijelio (dodijelio_id),
  CONSTRAINT fk_zadaci_prijava
    FOREIGN KEY (prijava_id) REFERENCES prijave (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_zadaci_radnik
    FOREIGN KEY (radnik_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_zadaci_dodijelio
    FOREIGN KEY (dodijelio_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. POVIJEST STATUSA (revizijski trag)
-- Svaki prijelaz statusa = jedan novi redak. Retci se nikad ne mijenjaju.
-- iz_statusa je NULL za prvi zapis (nastanak prijave).
-- ---------------------------------------------------------------------
CREATE TABLE povijest_statusa (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prijava_id  INT UNSIGNED NOT NULL,
  korisnik_id INT UNSIGNED NOT NULL,        -- TKO je napravio promjenu
  iz_statusa  ENUM('PRIJAVLJENO','DODIJELJENO','U_RADU','CEKA_PROVJERU','VRACENO','ZAVRSENO') NULL,
  u_status    ENUM('PRIJAVLJENO','DODIJELJENO','U_RADU','CEKA_PROVJERU','VRACENO','ZAVRSENO') NOT NULL,
  komentar    TEXT         NULL,            -- obavezan kod VRACENO (provjera u PHP-u)
  kreirano_u  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_povijest_prijava (prijava_id, kreirano_u),
  KEY idx_povijest_korisnik (korisnik_id),
  CONSTRAINT fk_povijest_prijava
    FOREIGN KEY (prijava_id) REFERENCES prijave (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_povijest_korisnik
    FOREIGN KEY (korisnik_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. RADOVI NA INFRASTRUKTURI (unosi administrator, prikaz na karti)
-- Vrsta radova i utjecaj na promet su fiksni popisi → ENUM.
-- kraj je NULL ako završetak još nije poznat.
-- ---------------------------------------------------------------------
CREATE TABLE radovi (
  id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  naslov            VARCHAR(150)     NOT NULL,
  opis              TEXT             NULL,
  vrsta             ENUM('cesta','vodovod','kanalizacija','plinovod','toplovod','elektroinstalacije','tramvajska_pruga','ostalo') NOT NULL,
  utjecaj_na_promet ENUM('nema','djelomicna_zatvorenost','potpuna_zatvorenost') NOT NULL DEFAULT 'nema',
  cetvrt_id         TINYINT UNSIGNED NULL,
  adresa            VARCHAR(255)     NULL,
  lat               DECIMAL(9,6)     NOT NULL,
  lng               DECIMAL(9,6)     NOT NULL,
  pocetak           DATETIME         NOT NULL,
  kraj              DATETIME         NULL,
  unio_id           INT UNSIGNED     NOT NULL,   -- administrator koji je unio radove
  kreirano_u        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  azurirano_u       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_radovi_cetvrt (cetvrt_id),
  KEY idx_radovi_unio (unio_id),
  KEY idx_radovi_razdoblje (pocetak, kraj),     -- filtar "aktivni radovi"
  CONSTRAINT fk_radovi_cetvrt
    FOREIGN KEY (cetvrt_id) REFERENCES gradske_cetvrti (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_radovi_unio
    FOREIGN KEY (unio_id) REFERENCES korisnici (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_radovi_razdoblje CHECK (kraj IS NULL OR kraj >= pocetak),
  CONSTRAINT chk_radovi_koord_zg  CHECK (lat BETWEEN 45.60 AND 46.00 AND lng BETWEEN 15.75 AND 16.30)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. NEUSPJELI POKUŠAJI PRIJAVE (zaštita od pogađanja lozinke)
-- Svaki neuspjeli login upisuje redak. Ako je u zadnjih 15 minuta bilo
-- previše pokušaja za isti e-mail ili s iste IP adrese, prijava se
-- privremeno odbija (HTTP 429). Uspješna prijava briše retke za taj e-mail.
-- Tablica nije povezana stranim ključem s korisnicima namjerno: bilježe
-- se i pokušaji za e-mailove koji ne postoje.
-- ---------------------------------------------------------------------
CREATE TABLE pokusaji_prijave (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email   VARCHAR(255)    NOT NULL,
  ip      VARCHAR(45)     NOT NULL,           -- IPv6 adresa ima najviše 45 znakova
  vrijeme DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pokusaji_email (email, vrijeme),
  KEY idx_pokusaji_ip (ip, vrijeme)
) ENGINE=InnoDB;
