# NTPWS26 – Zagreb Prijave

GitHub repo za projekt iz predmeta Napredne tehnike projektiranja web servisa.
Web aplikacija za prijavu komunalnih problema, pregled radova i stanja prometa u Gradu Zagrebu.

## Zahtjevi

- PHP 8.1+ (razvijano na 8.3) s ekstenzijama `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`
- MariaDB 10.4+ ili MySQL 8.0.16+
- Apache s `mod_rewrite` i `AllowOverride All` (XAMPP to ima zadano)
- [Composer](https://getcomposer.org/)

## Pokretanje (XAMPP)

1. Projekt smjestiti u `C:\xampp\htdocs\zagreb-prijave`.
2. U XAMPP Control Panelu pokrenuti **Apache** i **MySQL**.
3. Instalirati ovisnosti:
   ```
   composer install
   ```
4. Napraviti konfiguraciju i po potrebi je urediti:
   ```
   copy .env.example .env
   ```
5. Napraviti bazu i testne podatke:
   ```
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql"
   cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < database\seed.sql"
   C:\xampp\php\php.exe database\demo_slike.php
   ```
   Zadnja naredba stvara zamjenske slike za testne prijave (`storage/uploads/prijave/`).
6. Otvoriti http://localhost/zagreb-prijave

Ako projekt nije u `htdocs/zagreb-prijave`, u `.env` treba promijeniti `APP_URL`.

Pristupni podaci za testne račune predaju se kroz LMS.

Nakon promjene sheme (npr. nova tablica u novoj fazi) bazu treba ponovno uvesti
naredbama iz koraka 5. Time se brišu svi podaci i vraćaju početni testni podaci.

## Testovi

Uz pokrenuti Apache i MySQL, u Git Bashu:
```
bash tests/sigurnost.sh   # sigurnosni testovi (SQLi, XSS, CSRF, IDOR, upload, brute force...)
bash tests/cms.sh         # funkcionalni testovi CMS-a
bash tests/prijave.sh     # funkcionalni testovi prijava problema
bash tests/zadaci.sh      # dodjela, kanban, tok statusa i provjera završetka
bash tests/api.sh         # REST API (/api/v1) i JSON Schema
```
Skripte vraćaju bazu na početne podatke. Opis i rezultati: [docs/TESTOVI.md](docs/TESTOVI.md).

## Dokumentacija

- [Prijedlog projekta](docs/PRIJEDLOG_PROJEKTA.md)
- [ER dijagram i pravila brisanja](docs/ER.md)
- [Upute za korištenje (po ulogama)](docs/UPUTE.md)
- [REST API](docs/API.md) i [JSON Schema](schemas/)
- [Testovi i rezultati](docs/TESTOVI.md)

## Korištenje AI alata

Tijekom razvoja korišten je **Claude Code** (Anthropic), uz pregled i ručno testiranje svake izmjene:

| Faza | Za što |
|---|---|
| 1 | prijedlog strukture baze, SQL skripte, dokumentacija |
| 2 | struktura koda, router, autentifikacija, automatski testovi |
| 3 | javni dio, profil, stilovi, testovi, provjera licence grba |
| 4 | CMS (vijesti s uređivačem, kategorije, korisnici), sigurnosna analiza i testovi napada, zaštita od pogađanja lozinke |
| 5 | prijave problema (transakcija, slike izvan webroota), generator demo slika, testovi |
| 6 | kanban radnika, state machine statusa, dodjela i provjera završetka, testovi |
| 7 | REST API s JSON Schemom, dokumentacija API-ja, pretraga vijesti preko API-ja, testovi |
