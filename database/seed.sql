-- =====================================================================
-- Zagreb Prijave – testni podaci
-- Pokrenuti NAKON schema.sql.
--
-- Svi podaci su izmišljeni. E-mail adrese koriste domenu .test koja je
-- rezervirana (RFC 2606) i ne može pripadati stvarnoj osobi.
-- Lozinke su spremljene isključivo kao bcrypt hash (password_hash).
-- Stvarne testne lozinke NISU u repozitoriju – predaju se kroz LMS.
--
-- ID-jevi su navedeni eksplicitno da bi veze (strani ključevi) bile
-- čitljive. AUTO_INCREMENT nakon toga nastavlja od najvećeg ID-a.
-- =====================================================================

USE zagreb_prijave;
SET NAMES utf8mb4;

-- Sve ili ništa: ako bilo koji INSERT padne, ništa se ne sprema.
START TRANSACTION;

-- ---------------------------------------------------------------------
-- ULOGE
-- ---------------------------------------------------------------------
INSERT INTO uloge (id, kod, naziv, opis) VALUES
  (1, 'korisnik', 'Registrirani korisnik', 'Prijava problema, pregled vlastitih prijava, profil'),
  (2, 'radnik',   'Radnik',                'Rad na dodijeljenim zadacima, slanje na provjeru'),
  (3, 'urednik',  'Urednik',               'Izrada i uređivanje vlastitih vijesti'),
  (4, 'admin',    'Administrator',         'Puna administracija sustava');

-- ---------------------------------------------------------------------
-- KORISNICI (sve uloge + jedan deaktivirani račun za testiranje)
-- ---------------------------------------------------------------------
INSERT INTO korisnici (id, ime, prezime, email, lozinka_hash, uloga_id, aktivan, kreirano_u) VALUES
  (1, 'Iva',   'Admin',     'admin@zagreb-prijave.test',        '$2y$10$tJUyPtBpRBHvNtY68CSDdOaGKdeMAapyXpWTiV6kffbuKKlwosedO', 4, 1, '2026-09-25 10:00:00'),
  (2, 'Lea',   'Urednik',   'urednik.lea@zagreb-prijave.test',  '$2y$10$ruweTzg59HhO8ZmnyD1GzOKyENmARxUFRpPM57fkNmbMxyfMsis06', 3, 1, '2026-09-25 10:05:00'),
  (3, 'Tin',   'Urednik',   'urednik.tin@zagreb-prijave.test',  '$2y$10$z/900S.Q1/wtAjGqgZ1lw.yBIGf3WC/n1Up6l2ykTcd6nMk1nm/vu', 3, 1, '2026-09-25 10:06:00'),
  (4, 'Ivan',  'Radnik',    'radnik.ivan@zagreb-prijave.test',  '$2y$10$C6s9AMmTYLfObOUjO.JEiuGCWQQNofpvGmGsNeJ/9/kyefFC6GGcO', 2, 1, '2026-09-25 10:10:00'),
  (5, 'Marko', 'Radnik',    'radnik.marko@zagreb-prijave.test', '$2y$10$hFZ4NmUvmHKX/HKyg7q6QuJZkHBz7iXIgEMH5suT5NbRoaaxkeo76', 2, 1, '2026-09-25 10:11:00'),
  (6, 'Ana',   'Građanka',  'ana@zagreb-prijave.test',          '$2y$10$C2p4p.QcUDuM8wdmh5eCleYvPNKaBeaJ8RKmJy8Z3.7gZ5BttnvMy', 1, 1, '2026-09-28 18:20:00'),
  (7, 'Luka',  'Građanin',  'luka@zagreb-prijave.test',         '$2y$10$IsuBE.dkNprjP4MgcKlxBeXVT0HXP93bCabGn.Iw//Yt40cLDZiD.', 1, 1, '2026-09-30 09:45:00'),
  (8, 'Petra', 'Neaktivna', 'neaktivna@zagreb-prijave.test',    '$2y$10$RYWbCCQRkfiG06mkNBy7CujfTcIKyTeJSSZ7Eb0iMVAB.C4zblgxO', 1, 0, '2026-10-01 14:00:00');

-- ---------------------------------------------------------------------
-- KATEGORIJE VIJESTI ('Arhiva' je namjerno prazna → nju je dopušteno
-- obrisati, ostale imaju vijesti → brisanje mora biti odbijeno)
-- ---------------------------------------------------------------------
INSERT INTO kategorije_vijesti (id, naziv, opis) VALUES
  (1, 'Obavijesti Grada',         'Opće obavijesti za građane'),
  (2, 'Promet',                   'Regulacija prometa, zatvaranja i obilasci'),
  (3, 'Komunalne usluge',         'Odvoz otpada, čišćenje, zelene površine'),
  (4, 'Radovi i infrastruktura',  'Radovi na cestama, vodovodu, rasvjeti'),
  (5, 'Događanja',                'Akcije i događanja u četvrtima'),
  (6, 'Arhiva',                   'Prazna kategorija za test brisanja');

-- ---------------------------------------------------------------------
-- VIJESTI: 6 objavljenih, 2 nacrta, 1 povučena (nacrt s datumom objave)
-- Autori: Lea (2) i Tin (3) → test "urednik ne smije mijenjati tuđu vijest"
-- ---------------------------------------------------------------------
INSERT INTO vijesti (id, kategorija_id, autor_id, naslov, sazetak, sadrzaj, slika, status, datum_objave, kreirano_u, azurirano_u) VALUES
  (1, 1, 1, 'Pokrenuta aplikacija Zagreb Prijave',
   'Građani od danas mogu prijaviti komunalne probleme putem nove web aplikacije.',
   '<p>Nova aplikacija omogućuje prijavu komunalnih problema uz fotografiju i odabir gradske četvrti.</p><p>Status prijave moguće je pratiti na korisničkom profilu.</p><p><em>Testni sadržaj.</em></p>',
   NULL, 'objavljeno', '2026-10-01 09:00:00', '2026-09-30 15:00:00', '2026-10-01 09:00:00'),
  (2, 2, 2, 'Privremena regulacija prometa zbog radova na vodovodu u Trnju',
   'Zbog zamjene vodovodne cijevi promet se odvija jednim prometnim trakom.',
   '<p>Radovi traju do sredine studenog. Vozače molimo da poštuju privremenu signalizaciju.</p><ul><li>jedan prometni trak u svakom smjeru</li><li>ograničenje brzine 30 km/h</li></ul><p><em>Testni sadržaj.</em></p>',
   NULL, 'objavljeno', '2026-10-05 08:00:00', '2026-10-04 16:30:00', '2026-10-05 08:00:00'),
  (3, 3, 2, 'Kako prijaviti komunalni problem',
   'Kratke upute za prijavu oštećenja, otpada ili neispravne rasvjete.',
   '<p>Nakon registracije odaberite <strong>Nova prijava</strong>, priložite fotografiju, odaberite četvrt i opišite problem.</p><p><em>Testni sadržaj.</em></p>',
   NULL, 'objavljeno', '2026-10-08 10:00:00', '2026-10-07 12:00:00', '2026-10-08 10:00:00'),
  (4, 4, 3, 'Obnova javne rasvjete u Maksimiru',
   'Stara rasvjetna tijela zamjenjuju se LED rasvjetom.',
   '<p>Zamjena rasvjete provodi se postupno, ulicu po ulicu, tijekom listopada.</p><p><em>Testni sadržaj.</em></p>',
   NULL, 'objavljeno', '2026-10-12 11:30:00', '2026-10-12 09:00:00', '2026-10-12 11:30:00'),
  (5, 5, 3, 'Akcija čišćenja zelenih površina u Stenjevcu',
   'Pozivamo građane da se pridruže subotnjoj akciji čišćenja.',
   '<p>Okupljanje u 9 sati. Rukavice i vreće osigurane su na mjestu okupljanja.</p><p><em>Testni sadržaj.</em></p>',
   NULL, 'objavljeno', '2026-10-20 14:00:00', '2026-10-19 10:00:00', '2026-10-20 14:00:00'),
  (6, 3, 2, 'Raspored odvoza glomaznog otpada za studeni',
   'Objavljen je raspored odvoza glomaznog otpada po gradskim četvrtima.',
   '<p>Glomazni otpad iznosi se večer prije termina odvoza. Raspored po četvrtima nalazi se u prilogu.</p><p><em>Testni sadržaj.</em></p>',
   NULL, 'objavljeno', '2026-10-25 09:00:00', '2026-10-24 13:00:00', '2026-10-25 09:00:00'),
  (7, 2, 2, 'Izmjene prometa za blagdane (NACRT)',
   'Nacrt – ne smije biti vidljiv javnosti.',
   '<p>Tekst je u pripremi.</p>',
   NULL, 'nacrt', NULL, '2026-10-28 10:00:00', '2026-10-28 10:00:00'),
  (8, 4, 3, 'Plan radova za 2027. (NACRT)',
   'Nacrt – ne smije biti vidljiv javnosti.',
   '<p>Popis planiranih radova bit će objavljen nakon usvajanja plana.</p>',
   NULL, 'nacrt', NULL, '2026-10-29 15:00:00', '2026-10-29 15:00:00'),
  (9, 1, 1, 'Povučena obavijest o radnom vremenu',
   'Vijest je bila objavljena pa povučena – ne smije biti vidljiva javnosti.',
   '<p>Obavijest je povučena zbog netočnih podataka.</p>',
   NULL, 'nacrt', '2026-10-10 08:00:00', '2026-10-09 17:00:00', '2026-10-11 09:00:00');

-- ---------------------------------------------------------------------
-- GRADSKE ČETVRTI (17)
-- ---------------------------------------------------------------------
INSERT INTO gradske_cetvrti (id, naziv) VALUES
  (1,  'Donji grad'),
  (2,  'Gornji grad – Medveščak'),
  (3,  'Trnje'),
  (4,  'Maksimir'),
  (5,  'Peščenica – Žitnjak'),
  (6,  'Novi Zagreb – istok'),
  (7,  'Novi Zagreb – zapad'),
  (8,  'Trešnjevka – sjever'),
  (9,  'Trešnjevka – jug'),
  (10, 'Črnomerec'),
  (11, 'Gornja Dubrava'),
  (12, 'Donja Dubrava'),
  (13, 'Stenjevec'),
  (14, 'Podsused – Vrapče'),
  (15, 'Podsljeme'),
  (16, 'Sesvete'),
  (17, 'Brezovica');

-- ---------------------------------------------------------------------
-- VRSTE PROBLEMA
-- ---------------------------------------------------------------------
INSERT INTO vrste_problema (id, naziv, opis) VALUES
  (1, 'Oštećenje kolnika',          'Rupe, pukotine, oštećeni nogostup'),
  (2, 'Javna rasvjeta',             'Neispravna ili oštećena rasvjeta'),
  (3, 'Otpad i nečistoća',          'Divlja odlagališta, prepuni spremnici'),
  (4, 'Prometna signalizacija',     'Oštećeni znakovi, semafori, oznake na kolniku'),
  (5, 'Zelene površine i drveće',   'Polomljene grane, neodržavane površine'),
  (6, 'Grafiti i vandalizam',       'Oštećena javna imovina'),
  (7, 'Komunalna oprema',           'Klupe, koševi, dječja igrališta'),
  (8, 'Vodovod i odvodnja',         'Curenje vode, začepljeni slivnici'),
  (9, 'Ostalo',                     'Problem koji ne pripada drugim vrstama');

-- ---------------------------------------------------------------------
-- PRIJAVE – pokrivaju SVE statuse i sve razine hitnosti
--   1, 2, 10  PRIJAVLJENO (2 bez koordinata, 10 od deaktiviranog korisnika)
--   3         DODIJELJENO
--   4, 5      U_RADU
--   6         CEKA_PROVJERU
--   7         VRACENO
--   8, 9      ZAVRSENO
-- ---------------------------------------------------------------------
INSERT INTO prijave (id, korisnik_id, cetvrt_id, vrsta_id, naslov, opis, adresa, lat, lng, hitnost, status, kreirano_u, azurirano_u) VALUES
  (1,  6, 1,  1, 'Rupa na kolniku',             'Duboka rupa u desnom prometnom traku, opasna za bicikliste.', 'Ilica, kod raskrižja s Frankopanskom', 45.812800, 15.970000, 'srednja',  'PRIJAVLJENO',   '2026-10-27 09:15:00', '2026-10-27 09:15:00'),
  (2,  7, 16, 3, 'Divlje odlagalište otpada',   'Uz rub šume netko je odložio građevinski otpad.',              NULL,                                    NULL,      NULL,      'srednja',  'PRIJAVLJENO',   '2026-10-29 18:40:00', '2026-10-29 18:40:00'),
  (3,  6, 4,  2, 'Ne radi ulična rasvjeta',     'Tri uzastopne svjetiljke ne svijetle, ulica je potpuno mračna.','Maksimirska cesta',                     45.823000, 16.015000, 'visoka',   'DODIJELJENO',   '2026-10-22 11:00:00', '2026-10-23 08:30:00'),
  (4,  7, 3,  4, 'Srušen prometni znak STOP',   'Znak STOP leži na nogostupu, raskrižje je bez oznake prednosti.','Vukovarska ulica',                     45.800000, 15.980000, 'kriticna', 'U_RADU',        '2026-10-20 07:45:00', '2026-10-20 10:15:00'),
  (5,  6, 8,  7, 'Polomljena klupa u parku',    'Daska na klupi je slomljena, strše čavli.',                    NULL,                                    45.804000, 15.950000, 'niska',    'U_RADU',        '2026-10-18 16:20:00', '2026-10-24 08:00:00'),
  (6,  7, 6,  1, 'Udarna rupa na parkiralištu', 'Rupa ispunjena vodom, ne vidi se koliko je duboka.',            NULL,                                    45.776000, 15.990000, 'visoka',   'CEKA_PROVJERU', '2026-10-14 12:00:00', '2026-10-28 14:00:00'),
  (7,  6, 10, 5, 'Grana pala na nogostup',      'Velika grana nakon nevremena blokira prolaz pješacima.',       NULL,                                    45.817000, 15.935000, 'srednja',  'VRACENO',       '2026-10-10 10:00:00', '2026-10-18 09:30:00'),
  (8,  7, 5,  3, 'Prepuni spremnici za otpad',  'Spremnici nisu ispražnjeni tjedan dana.',                      NULL,                                    45.796000, 16.025000, 'srednja',  'ZAVRSENO',      '2026-10-02 09:00:00', '2026-10-04 08:15:00'),
  (9,  6, 13, 6, 'Grafiti na autobusnoj stanici','Nadstrešnica je išarana sprejem.',                            NULL,                                    45.807000, 15.885000, 'niska',    'ZAVRSENO',      '2026-10-05 19:00:00', '2026-10-09 16:00:00'),
  (10, 8, 12, 8, 'Začepljen slivnik',           'Nakon kiše voda se zadržava na cijelom raskrižju.',            NULL,                                    45.816000, 16.055000, 'srednja',  'PRIJAVLJENO',   '2026-10-08 12:30:00', '2026-10-08 12:30:00');

-- ---------------------------------------------------------------------
-- ZADACI (samo za prijave koje su dodijeljene radniku)
-- Ivan = 4, Marko = 5; dodijelila administratorica Iva (1)
-- ---------------------------------------------------------------------
INSERT INTO zadaci (id, prijava_id, radnik_id, dodijelio_id, napomena_radnika, dodijeljeno_u, azurirano_u) VALUES
  (1, 3, 4, 1, NULL, '2026-10-23 08:30:00', '2026-10-23 08:30:00'),
  (2, 4, 4, 1, NULL, '2026-10-20 09:00:00', '2026-10-20 10:15:00'),
  (3, 5, 5, 1, NULL, '2026-10-19 09:00:00', '2026-10-24 08:00:00'),
  (4, 6, 5, 1, 'Rupa sanirana hladnim asfaltom, površina poravnata.',          '2026-10-15 08:00:00', '2026-10-28 14:00:00'),
  (5, 7, 4, 1, 'Grana izrezana i odvezena.',                                    '2026-10-11 08:00:00', '2026-10-17 15:00:00'),
  (6, 8, 5, 1, 'Spremnici ispražnjeni, prostor oko njih očišćen.',             '2026-10-02 13:00:00', '2026-10-03 12:00:00'),
  (7, 9, 4, 1, 'Grafiti uklonjeni, nadstrešnica oprana.',                       '2026-10-06 08:00:00', '2026-10-09 11:00:00');

-- ---------------------------------------------------------------------
-- SLIKE PRIJAVA
-- 'prije' za svaku prijavu (učitao građanin), 'poslije' za prijave koje su
-- bile poslane na provjeru (učitao radnik). Datoteke se dodaju u fazi 5.
-- ---------------------------------------------------------------------
INSERT INTO prijava_slike (prijava_id, ucitao_id, tip, datoteka, mime_tip, velicina, kreirano_u) VALUES
  (1,  6, 'prije',   'demo-p01-prije.jpg',   'image/jpeg', 245760, '2026-10-27 09:15:00'),
  (2,  7, 'prije',   'demo-p02-prije.jpg',   'image/jpeg', 312400, '2026-10-29 18:40:00'),
  (3,  6, 'prije',   'demo-p03-prije.jpg',   'image/jpeg', 198300, '2026-10-22 11:00:00'),
  (4,  7, 'prije',   'demo-p04-prije.jpg',   'image/jpeg', 280100, '2026-10-20 07:45:00'),
  (5,  6, 'prije',   'demo-p05-prije.jpg',   'image/jpeg', 150900, '2026-10-18 16:20:00'),
  (6,  7, 'prije',   'demo-p06-prije.jpg',   'image/jpeg', 301000, '2026-10-14 12:00:00'),
  (6,  5, 'poslije', 'demo-p06-poslije.jpg', 'image/jpeg', 287500, '2026-10-28 14:00:00'),
  (7,  6, 'prije',   'demo-p07-prije.jpg',   'image/jpeg', 330200, '2026-10-10 10:00:00'),
  (7,  4, 'poslije', 'demo-p07-poslije.jpg', 'image/jpeg', 120400, '2026-10-17 15:00:00'),
  (8,  7, 'prije',   'demo-p08-prije.jpg',   'image/png',  410000, '2026-10-02 09:00:00'),
  (8,  5, 'poslije', 'demo-p08-poslije.jpg', 'image/jpeg', 265000, '2026-10-03 12:00:00'),
  (9,  6, 'prije',   'demo-p09-prije.jpg',   'image/jpeg', 222000, '2026-10-05 19:00:00'),
  (9,  4, 'poslije', 'demo-p09-poslije.jpg', 'image/jpeg', 230500, '2026-10-09 11:00:00'),
  (10, 8, 'prije',   'demo-p10-prije.jpg',   'image/jpeg', 199000, '2026-10-08 12:30:00');

-- ---------------------------------------------------------------------
-- POVIJEST STATUSA – kronološki tragovi koji odgovaraju trenutnom
-- statusu svake prijave (tko, kada, iz, u, komentar)
-- ---------------------------------------------------------------------
INSERT INTO povijest_statusa (prijava_id, korisnik_id, iz_statusa, u_status, komentar, kreirano_u) VALUES
  -- P1
  (1, 6, NULL,            'PRIJAVLJENO',   NULL, '2026-10-27 09:15:00'),
  -- P2
  (2, 7, NULL,            'PRIJAVLJENO',   NULL, '2026-10-29 18:40:00'),
  -- P3
  (3, 6, NULL,            'PRIJAVLJENO',   NULL, '2026-10-22 11:00:00'),
  (3, 1, 'PRIJAVLJENO',   'DODIJELJENO',   'Dodijeljeno radniku Ivanu.', '2026-10-23 08:30:00'),
  -- P4
  (4, 7, NULL,            'PRIJAVLJENO',   NULL, '2026-10-20 07:45:00'),
  (4, 1, 'PRIJAVLJENO',   'DODIJELJENO',   'Hitnost podignuta na kritičnu – raskrižje bez oznake.', '2026-10-20 09:00:00'),
  (4, 4, 'DODIJELJENO',   'U_RADU',        NULL, '2026-10-20 10:15:00'),
  -- P5
  (5, 6, NULL,            'PRIJAVLJENO',   NULL, '2026-10-18 16:20:00'),
  (5, 1, 'PRIJAVLJENO',   'DODIJELJENO',   NULL, '2026-10-19 09:00:00'),
  (5, 5, 'DODIJELJENO',   'U_RADU',        NULL, '2026-10-24 08:00:00'),
  -- P6
  (6, 7, NULL,            'PRIJAVLJENO',   NULL, '2026-10-14 12:00:00'),
  (6, 1, 'PRIJAVLJENO',   'DODIJELJENO',   NULL, '2026-10-15 08:00:00'),
  (6, 5, 'DODIJELJENO',   'U_RADU',        NULL, '2026-10-16 07:30:00'),
  (6, 5, 'U_RADU',        'CEKA_PROVJERU', 'Rupa sanirana hladnim asfaltom, površina poravnata.', '2026-10-28 14:00:00'),
  -- P7 (vraćeno radniku)
  (7, 6, NULL,            'PRIJAVLJENO',   NULL, '2026-10-10 10:00:00'),
  (7, 1, 'PRIJAVLJENO',   'DODIJELJENO',   NULL, '2026-10-11 08:00:00'),
  (7, 4, 'DODIJELJENO',   'U_RADU',        NULL, '2026-10-13 08:00:00'),
  (7, 4, 'U_RADU',        'CEKA_PROVJERU', 'Grana izrezana i odvezena.', '2026-10-17 15:00:00'),
  (7, 1, 'CEKA_PROVJERU', 'VRACENO',       'Na slici se ne vidi da je nogostup očišćen. Molim provjeru i jasniju sliku.', '2026-10-18 09:30:00'),
  -- P8
  (8, 7, NULL,            'PRIJAVLJENO',   NULL, '2026-10-02 09:00:00'),
  (8, 1, 'PRIJAVLJENO',   'DODIJELJENO',   NULL, '2026-10-02 13:00:00'),
  (8, 5, 'DODIJELJENO',   'U_RADU',        NULL, '2026-10-03 07:30:00'),
  (8, 5, 'U_RADU',        'CEKA_PROVJERU', 'Spremnici ispražnjeni, prostor oko njih očišćen.', '2026-10-03 12:00:00'),
  (8, 1, 'CEKA_PROVJERU', 'ZAVRSENO',      NULL, '2026-10-04 08:15:00'),
  -- P9
  (9, 6, NULL,            'PRIJAVLJENO',   NULL, '2026-10-05 19:00:00'),
  (9, 1, 'PRIJAVLJENO',   'DODIJELJENO',   NULL, '2026-10-06 08:00:00'),
  (9, 4, 'DODIJELJENO',   'U_RADU',        NULL, '2026-10-07 07:30:00'),
  (9, 4, 'U_RADU',        'CEKA_PROVJERU', 'Grafiti uklonjeni, nadstrešnica oprana.', '2026-10-09 11:00:00'),
  (9, 1, 'CEKA_PROVJERU', 'ZAVRSENO',      NULL, '2026-10-09 16:00:00'),
  -- P10
  (10, 8, NULL,           'PRIJAVLJENO',   NULL, '2026-10-08 12:30:00');

-- ---------------------------------------------------------------------
-- RADOVI: aktivni, budući, završeni i bez poznatog kraja
-- ---------------------------------------------------------------------
INSERT INTO radovi (id, naslov, opis, vrsta, utjecaj_na_promet, cetvrt_id, adresa, lat, lng, pocetak, kraj, unio_id) VALUES
  (1, 'Zamjena vodovodne cijevi',       'Zamjena dotrajale glavne cijevi.',            'vodovod',            'djelomicna_zatvorenost', 3,  'Ulica grada Vukovara',  45.799500, 15.979000, '2026-10-15 07:00:00', '2026-11-15 17:00:00', 1),
  (2, 'Obnova kolnika',                 'Skidanje starog i polaganje novog asfalta.',  'cesta',              'potpuna_zatvorenost',    9,  NULL,                    45.793000, 15.945000, '2026-10-26 07:00:00', '2026-11-08 18:00:00', 1),
  (3, 'Održavanje tramvajske pruge',    'Zamjena dotrajalih tračnica.',                'tramvajska_pruga',   'djelomicna_zatvorenost', 1,  NULL,                    45.805000, 15.978000, '2026-11-10 22:00:00', '2026-11-20 05:00:00', 1),
  (4, 'Popravak toplovoda',             'Sanacija puknuća toplovoda.',                 'toplovod',           'djelomicna_zatvorenost', 7,  NULL,                    45.780000, 15.950000, '2026-09-01 07:00:00', '2026-09-30 16:00:00', 1),
  (5, 'Polaganje elektroinstalacija',   'Polaganje podzemnog kabela uz nogostup.',     'elektroinstalacije', 'nema',                   16, NULL,                    45.828000, 16.108000, '2026-10-20 07:00:00', NULL,                  1);

COMMIT;
