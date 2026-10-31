#!/usr/bin/env bash
# =====================================================================
# Testovi faze 8 – karta, radovi, lokacija prijava, promet (TomTom proxy).
# Pokretanje: bash tests/karta.sh   (VRAĆA BAZU NA POČETNE PODATKE!)
# Ne troši TomTom kvotu: kvarovi se simuliraju (?simulacija=...), a stvarni
# poziv se radi najviše jednom.
# =====================================================================
source "$(dirname "$0")/lib.sh"

reset_baze || { echo "Baza nije dostupna – je li MySQL pokrenut?"; exit 1; }
A="$B/api/v1"
CACHE="$KORIJEN/storage/cache"
napravi_png "$T/foto.png"
broj_radova() { echo "SELECT COUNT(*) FROM radovi" | SQL; }
# cache/kvotu iz razvoja čuvamo i vraćamo na kraju
for f in promet.json promet-kvota.json; do [[ -f "$CACHE/$f" ]] && cp "$CACHE/$f" "$T/$f.bak"; done

ADMIN=$(prijava admin@zagreb-prijave.test)
ANA=$(prijava ana@zagreb-prijave.test)
LEA=$(prijava urednik.lea@zagreb-prijave.test)
tA=$(tok "$ADMIN" /admin/radovi)

naslov "Javna karta"
check "GET /karta (gost)" "$(code "$B/karta")" "200"
has   "  → Leaflet sa SRI" 'leaflet@1.9.4/dist/leaflet.js'
has   "  → karta.js" 'js/karta.js'
has   "  → adresa API-ja" 'data-api="/zagreb-prijave/api/v1"'
has   "  → granice Zagreba" 'data-granice="\[\[45.7,15.8\],\[45.95,16.25\]\]"'
has   "  → atribucija OSM i TomTom" 'OpenStreetMap suradnici. Promet: © TomTom'
has   "  → poveznica u izborniku" '>Karta</a>'

naslov "Radovi: pristup"
check "gost → /admin/radovi" "$(code "$B/admin/radovi")" "303 $B/racun/prijava"
check "korisnica → /admin/radovi" "$(code -b "$ANA" "$B/admin/radovi")" "403"
check "urednica → /admin/radovi" "$(code -b "$LEA" "$B/admin/radovi")" "403"
check "urednica → POST /admin/radovi" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$(tok "$LEA" /admin/vijesti)" -d 'naslov=X' "$B/admin/radovi")" "403"
check "admin → /admin/radovi" "$(code -b "$ADMIN" "$B/admin/radovi")" "200"
has   "  → 5 radova" '5 radova'
code -b "$ADMIN" "$B/admin/radovi?aktivni=1" >/dev/null
has   "  → filtar u tijeku" "$(echo 'SELECT COUNT(*) FROM radovi WHERE pocetak <= NOW() AND (kraj IS NULL OR kraj >= NOW())' | SQL) radova"
check "admin → /admin/radovi/novi" "$(code -b "$ADMIN" "$B/admin/radovi/novi")" "200"
has   "  → odabir lokacije na karti" 'data-odabir-lokacije'

naslov "Radovi: validacija"
ISPRAVNO=(--data-urlencode "_csrf=$tA" -d 'naslov=Sanacija kolnika' -d 'vrsta=cesta' -d 'utjecaj_na_promet=djelomicna_zatvorenost' -d 'cetvrt_id=1' -d 'lat=45.8130' -d 'lng=15.9770' -d 'pocetak=2026-11-02T07:00' -d 'kraj=2026-11-05T18:00')
check "prazan obrazac" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tA" "$B/admin/radovi")" "422"
has   "  → naslov" 'Naslov mora imati'
has   "  → vrsta" 'Odaberite vrstu radova'
has   "  → lokacija obavezna" 'Odaberite lokaciju na karti'
has   "  → početak" 'Unesite ispravan početak radova'
check "lokacija izvan Zagreba (Sisak)" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" -d 'lat=45.4850' -d 'lng=16.3730' "$B/admin/radovi")" "422"
has   "  → poruka" 'području Grada Zagreba'
check "lat = '45.8 OR 1=1'" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" --data-urlencode 'lat=45.8 OR 1=1' "$B/admin/radovi")" "422"
check "kraj prije početka" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" -d 'kraj=2026-11-01T07:00' "$B/admin/radovi")" "422"
has   "  → poruka" 'Kraj ne može biti prije početka'
check "nepostojeća četvrt 99" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" -d 'cetvrt_id=99' "$B/admin/radovi")" "422"
check "nepoznata vrsta 'most'" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" -d 'vrsta=most' "$B/admin/radovi")" "422"
eq    "  → ništa nije spremljeno" "$(broj_radova)" "5"

naslov "Radovi: unos, izmjena, brisanje"
check "ispravan unos" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" "$B/admin/radovi")" "303 $B/admin/radovi"
RID=$(echo "SELECT MAX(id) FROM radovi" | SQL)
eq    "  → u bazi (unio admin, koordinate, razdoblje)" "$(echo "SELECT CONCAT(unio_id,'/',lat,'/',lng,'/',pocetak,'/',kraj) FROM radovi WHERE id=$RID" | SQL)" "1/45.813000/15.977000/2026-11-02 07:00:00/2026-11-05 18:00:00"
code "$A/radovi?po_stranici=100" >/dev/null
eq    "  → vidljivo u API-ju" "$(jv meta.ukupno)" "6"
has   "  → s nazivom vrste i stanjem" '"naslov":"Sanacija kolnika"'
check "obrazac za uređivanje" "$(code -b "$ADMIN" "$B/admin/radovi/$RID/uredi")" "200"
has   "  → datum u formatu datetime-local" 'value="2026-11-02T07:00"'
check "izmjena (bez kraja)" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" -d 'kraj=' -d 'naslov=Sanacija kolnika Ilica' "$B/admin/radovi/$RID")" "303"
eq    "  → kraj NULL, novi naslov" "$(echo "SELECT CONCAT(naslov,'/',IFNULL(kraj,'NULL')) FROM radovi WHERE id=$RID" | SQL)" "Sanacija kolnika Ilica/NULL"
check "izmjena nepostojećih radova" "$(code -b "$ADMIN" -X POST "${ISPRAVNO[@]}" "$B/admin/radovi/999")" "404"
check "brisanje" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tA" "$B/admin/radovi/$RID/obrisi")" "303"
eq    "  → obrisano" "$(broj_radova)" "5"
check "brisanje bez CSRF tokena" "$(code -b "$ADMIN" -X POST "$B/admin/radovi/1/obrisi")" "403"

naslov "Prijava s lokacijom na karti"
tAna=$(tok "$ANA" /prijave/nova)
check "obrazac prijave ima odabir lokacije" "$(code -b "$ANA" "$B/prijave/nova")" "200"
has   "  → karta" 'data-odabir-lokacije'
prijava_s() { code -b "$ANA" -X POST -F "_csrf=$tAna" -F cetvrt_id=1 -F vrsta_id=1 -F 'naslov=Rupa na cesti' -F 'opis=Rupa kod tramvajske stanice.' -F "slika=@$TW/foto.png" "$@" "$B/prijave"; }
check "lokacija izvan Zagreba" "$(prijava_s -F lat=46.3050 -F lng=16.3360)" "422"
has   "  → poruka" 'području Grada Zagreba'
check "samo širina bez dužine" "$(prijava_s -F lat=45.81)" "422"
check "ispravna lokacija" "$(prijava_s -F lat=45.812345 -F lng=15.976543)" "303"
PID=$(echo "SELECT MAX(id) FROM prijave" | SQL)
eq    "  → spremljeno" "$(echo "SELECT CONCAT(lat,',',lng) FROM prijave WHERE id=$PID" | SQL)" "45.812345,15.976543"
code -b "$ANA" "$B/prijave/$PID" >/dev/null
has   "  → detalj prikazuje kartu" 'data-prikaz-lokacije'
code "$A/prijave?po_stranici=100" >/dev/null
has   "  → na javnoj karti (API)" '"lokacija":{"lat":45.812345,"lng":15.976543}'
check "bez lokacije (neobavezna)" "$(prijava_s)" "303"

naslov "Promet: testni odgovor"
check "?simulacija=testni" "$(code "$A/promet?simulacija=testni")" "200"
eq    "  → označen kao testni" "$(jv data.testni)/$(jv data.izvor)" "true/testni"
has   "  → upozorenje TESTNI PODACI" 'TESTNI PODACI'
eq    "  → incidenti i protok" "$(jv data.incidenti)/$(jv data.protok)" "2/6"
eq    "  → koordinate [lat, lng] u Zagrebu" "$(jv data.incidenti.0.geometrija.koordinate.0.0)" "45.799"
eq    "  → kategorija na hrvatskom" "$(jv data.incidenti.0.kategorija_naziv)" "Radovi na cesti"
has   "  → atribucija" 'Podaci o prometu © TomTom'

naslov "Promet: kvar servisa i neočekivana struktura (simulacija)"
rm -f "$CACHE/promet.json"
check "nedostupan, nema cachea" "$(code "$A/promet?simulacija=nedostupan")" "503"
eq    "  → JSON pogreška" "$(jv greska.status)" "503"
has   "  → poruka" 'nije dostupan'
header_has "  → Retry-After" 'Retry-After: 60'
check "neočekivana struktura, nema cachea" "$(code "$A/promet?simulacija=neispravno")" "503"
has   "  → poruka" 'neočekivane strukture'
# zastarjeli cache (dohvaćen prije 1 sat)
STARO=$(date -d '-1 hour' +%Y-%m-%dT%H:%M:%S%:z)
printf '{"dohvaceno_u":"%s","podaci":{"incidenti":[],"protok":[],"upozorenje":null}}' "$STARO" > "$CACHE/promet.json"
check "nedostupan, postoji stari cache" "$(code "$A/promet?simulacija=nedostupan")" "200"
eq    "  → zastarjeli podaci iz cachea" "$(jv data.izvor)/$(jv data.zastarjelo)" "cache/true"
eq    "  → vrijeme zadnjeg uspješnog dohvata" "$(jv data.zadnji_uspjesni_dohvat)" "$STARO"
has   "  → upozorenje" 'Prikazani su zadnji uspješno dohvaćeni podaci'
check "neispravna struktura, postoji stari cache" "$(code "$A/promet?simulacija=neispravno")" "200"
eq    "  → zastarjelo" "$(jv data.zastarjelo)" "true"
eq    "  → simulacija ne prepisuje cache" "$(grep -c "$STARO" "$CACHE/promet.json")" "1"

naslov "Promet: stvarni poziv (bez trošenja kvote ako ključ nije postavljen)"
SVJEZE=$(date +%Y-%m-%dT%H:%M:%S%:z)
printf '{"dohvaceno_u":"%s","podaci":{"incidenti":[],"protok":[],"upozorenje":null}}' "$SVJEZE" > "$CACHE/promet.json"
check "GET /promet" "$(code "$A/promet")" "200"
hasnt "  → ključ se ne vraća klijentu" 'key='
hasnt "  → ni adresa TomToma" 'api.tomtom.com'
if [[ "$(jv data.izvor)" == "testni" ]]; then
    echo "  INFO  TOMTOM_API_KEY nije postavljen → testni odgovor (cache se ne koristi)"
    has "  → razlog u upozorenju" 'ključ nije postavljen'
else
    eq "  → svježi cache se koristi (bez poziva TomToma)" "$(jv data.izvor)" "cache"
    # kvota potrošena + stari cache → stari podaci s upozorenjem, bez poziva TomToma
    printf '{"dohvaceno_u":"%s","podaci":{"incidenti":[],"protok":[],"upozorenje":null}}' "$STARO" > "$CACHE/promet.json"
    printf '{"datum":"%s","broj":80}' "$(date +%Y-%m-%d)" > "$CACHE/promet-kvota.json"
    code "$A/promet" >/dev/null
    has "  → dnevna kvota potrošena → stari podaci" 'Dnevna kvota osvježavanja'
fi
check "nepoznata simulacija = običan poziv" "$(code "$A/promet?simulacija=sve&bbox=0,0,1,1&key=x")" "200"
check "POST /promet nije dopušten" "$(code -X POST "$A/promet")" "405"

naslov "Promet: skrivanje zastarjelih incidenata (admin)"
check "gost → /admin/promet" "$(code "$B/admin/promet")" "303 $B/racun/prijava"
check "urednica → /admin/promet" "$(code -b "$LEA" "$B/admin/promet")" "403"
check "urednica → skrivanje" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$(tok "$LEA" /admin/vijesti)" -d 'incident_id=TEST-1&razlog=Pokusaj' "$B/admin/promet/sakrij")" "403"
check "admin → /admin/promet" "$(code -b "$ADMIN" "$B/admin/promet")" "200"
tP=$(tok "$ADMIN" /admin/promet)
check "skrivanje bez CSRF tokena" "$(code -b "$ADMIN" -X POST -d 'incident_id=TEST-1&razlog=Bez tokena' "$B/admin/promet/sakrij")" "403"
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tP" -d 'incident_id=nepostoji&razlog=Test razlog' "$B/admin/promet/sakrij" >/dev/null
code -b "$ADMIN" "$B/admin/promet" >/dev/null; has "nepostojeći incident → poruka" 'više nije u podacima'
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tP" -d 'incident_id=x&razlog=a' "$B/admin/promet/sakrij" >/dev/null
code -b "$ADMIN" "$B/admin/promet" >/dev/null; has "prekratak razlog → poruka" 'Upišite razlog skrivanja'
eq    "  → ništa nije skriveno" "$(echo 'SELECT COUNT(*) FROM skriveni_incidenti' | SQL)" "0"
# testni podaci imaju stalne ID-jeve (TEST-1, TEST-2): skrivanje upisano izravno kao da ga je napravio admin
echo "INSERT INTO skriveni_incidenti (incident_hash, opis, razlog, sakrio_id) VALUES (SHA2('TEST-1', 256), 'Radovi na cesti: test', 'Test skrivanja', 1)" | SQL
code "$A/promet?simulacija=testni" >/dev/null
eq    "skriveni incident se ne vraća (testni podaci)" "$(jv data.incidenti)/$(jv data.skriveno_incidenata)" "1/1"
hasnt "  → TEST-1 nije u odgovoru" '"id":"TEST-1"'
SID=$(echo "SELECT id FROM skriveni_incidenti" | SQL)
code -b "$ADMIN" "$B/admin/promet" >/dev/null; has "admin vidi popis skrivenih s razlogom" 'Test skrivanja'
check "vraćanje na kartu" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tP" "$B/admin/promet/skriveni/$SID/vrati")" "303"
code "$A/promet?simulacija=testni" >/dev/null
eq    "  → ponovno vidljiv" "$(jv data.incidenti)/$(jv data.skriveno_incidenata)" "2/0"
check "vraćanje nepostojećeg" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tP" "$B/admin/promet/skriveni/999/vrati")" "404"

# vrati cache i kvotu iz razvoja
rm -f "$CACHE/promet.json" "$CACHE/promet-kvota.json"
for f in promet.json promet-kvota.json; do [[ -f "$T/$f.bak" ]] && cp "$T/$f.bak" "$CACHE/$f"; done

reset_baze
sazetak
