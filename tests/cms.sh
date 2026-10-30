#!/usr/bin/env bash
# =====================================================================
# Funkcionalni testovi faze 4 – CMS: vijesti, kategorije, korisnici.
# Pokretanje: bash tests/cms.sh   (VRAĆA BAZU NA POČETNE PODATKE!)
# =====================================================================
source "$(dirname "$0")/lib.sh"

reset_baze || { echo "Baza nije dostupna – je li MySQL pokrenut?"; exit 1; }
UPL="$KORIJEN/public/uploads/vijesti"
napravi_png "$T/slika.png"

ADMIN=$(prijava admin@zagreb-prijave.test)
LEA=$(prijava urednik.lea@zagreb-prijave.test)

naslov "Pristup i izbornik"
check "admin → /admin" "$(code -b "$ADMIN" "$B/admin")" "200"
has   "  → broj objavljenih i nacrta" '6 objavljeno · 3 u nacrtu'
check "urednica → /admin/vijesti" "$(code -b "$LEA" "$B/admin/vijesti")" "200"
has   "  → izbornik 'Moje vijesti'" 'Moje vijesti'
hasnt "  → nema poveznice na korisnike" '/admin/korisnici'
check "admin → /admin/vijesti (sve)" "$(code -b "$ADMIN" "$B/admin/vijesti")" "200"
has   "  → stupac autora" '<th scope="col">Autor</th>'
code -b "$ADMIN" "$B/admin/vijesti?status=nacrt" >/dev/null
eq    "  → filtar status=nacrt (3 retka)" "$(grep -c 'text-bg-secondary">Nacrt' "$T/body")" "3"
has   "  → povučena vijest označena" 'povučeno'

naslov "Nova vijest: validacija"
t=$(tok "$LEA" /admin/vijesti/nova)
check "obrazac nove vijesti" "$(code -b "$LEA" "$B/admin/vijesti/nova")" "200"
has   "  → Quill se učitava (SRI)" 'quill@2.0.3/dist/quill.js'
has   "  → multipart obrazac" 'enctype="multipart/form-data"'
check "prazan obrazac" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" "$B/admin/vijesti")" "422"
has   "  → poruka za naslov" 'Naslov mora imati'
has   "  → poruka za sadržaj" 'Sadržaj je obavezan'
check "sadržaj samo <p><br></p> (prazan uređivač)" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Naslov vijesti' --data-urlencode 'sazetak=Dovoljno dug sažetak' --data-urlencode 'sadrzaj=<p><br></p>' -d 'kategorija_id=1&status=nacrt' "$B/admin/vijesti")" "422"
check "nepostojeća kategorija 999" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Naslov vijesti' --data-urlencode 'sazetak=Dovoljno dug sažetak' --data-urlencode 'sadrzaj=<p>Tekst</p>' -d 'kategorija_id=999&status=nacrt' "$B/admin/vijesti")" "422"
has   "  → poruka za kategoriju" 'Odaberite kategoriju'
check "nepoznat status 'arhiva'" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Naslov vijesti' --data-urlencode 'sazetak=Dovoljno dug sažetak' --data-urlencode 'sadrzaj=<p>Tekst</p>' -d 'kategorija_id=1&status=arhiva' "$B/admin/vijesti")" "422"
has   "  → upisani naslov ostaje u obrascu" 'value="Naslov vijesti"'

naslov "Nova vijest sa slikom → objava → povlačenje"
check "nacrt sa slikom" "$(code -b "$LEA" -X POST -F "_csrf=$t" -F 'naslov=Testna vijest CMS' -F 'sazetak=Sažetak testne vijesti iz CMS-a' \
    --form-string 'sadrzaj=<h2>Podnaslov</h2><p>Prvi <strong>odlomak</strong>.</p><ul><li>stavka</li></ul>' -F kategorija_id=3 -F status=nacrt -F "slika=@$TW/slika.png" "$B/admin/vijesti")" "303"
ID=$(echo "SELECT MAX(id) FROM vijesti" | SQL)
SLIKA=$(echo "SELECT slika FROM vijesti WHERE id=$ID" | SQL)
eq    "  → autorica je Lea, datum objave prazan" "$(echo "SELECT CONCAT(autor_id,'/',IFNULL(datum_objave,'NULL')) FROM vijesti WHERE id=$ID" | SQL)" "2/NULL"
if [[ -f "$UPL/$SLIKA" ]]; then ok "  → slika spremljena ($SLIKA)"; else fail "  → slika nije na disku"; fi
check "  → nacrt NIJE javno vidljiv" "$(code "$B/vijesti/$ID")" "404"
check "  → obrazac za uređivanje" "$(code -b "$LEA" "$B/admin/vijesti/$ID/uredi")" "200"
has   "  → prikazuje trenutnu sliku" "uploads/vijesti/$SLIKA"

t=$(tok "$LEA" "/admin/vijesti/$ID/uredi")
check "objava" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Testna vijest CMS' --data-urlencode 'sazetak=Sažetak testne vijesti iz CMS-a' \
    --data-urlencode 'sadrzaj=<h2>Podnaslov</h2><p>Prvi <strong>odlomak</strong>.</p>' -d 'kategorija_id=3&status=objavljeno' "$B/admin/vijesti/$ID")" "303 $B/admin/vijesti/$ID/uredi"
DATUM=$(echo "SELECT datum_objave FROM vijesti WHERE id=$ID" | SQL)
if [[ "$DATUM" != "NULL" && -n "$DATUM" ]]; then ok "  → datum objave postavljen ($DATUM)"; else fail "  → datum objave nije postavljen"; fi
eq    "  → slika zadržana kad se ne šalje nova" "$(echo "SELECT slika FROM vijesti WHERE id=$ID" | SQL)" "$SLIKA"
check "  → javno vidljiva" "$(code "$B/vijesti/$ID")" "200"
has   "  → HTML sadržaj prikazan" '<h2>Podnaslov</h2>'
has   "  → slika prikazana" "uploads/vijesti/$SLIKA"
code "$B/" >/dev/null; has "  → na početnoj kao najnovija" 'Testna vijest CMS'

check "zamjena slike" "$(code -b "$LEA" -X POST -F "_csrf=$t" -F 'naslov=Testna vijest CMS' -F 'sazetak=Sažetak testne vijesti iz CMS-a' \
    --form-string 'sadrzaj=<p>Tekst</p>' -F kategorija_id=3 -F status=objavljeno -F "slika=@$TW/slika.png" "$B/admin/vijesti/$ID")" "303"
NOVA=$(echo "SELECT slika FROM vijesti WHERE id=$ID" | SQL)
if [[ "$NOVA" != "$SLIKA" && -f "$UPL/$NOVA" && ! -f "$UPL/$SLIKA" ]]; then ok "  → nova slika spremljena, stara obrisana s diska"; else fail "  → zamjena slike ($SLIKA → $NOVA)"; fi
sleep 1
check "povlačenje (natrag u nacrt) + uklanjanje slike" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Testna vijest CMS' --data-urlencode 'sazetak=Sažetak testne vijesti iz CMS-a' \
    --data-urlencode 'sadrzaj=<p>Tekst</p>' -d 'kategorija_id=3&status=nacrt&ukloni_sliku=1' "$B/admin/vijesti/$ID")" "303"
check "  → više nije javno vidljiva" "$(code "$B/vijesti/$ID")" "404"
eq    "  → datum prve objave sačuvan" "$(echo "SELECT datum_objave FROM vijesti WHERE id=$ID" | SQL)" "$DATUM"
if [[ ! -f "$UPL/$NOVA" ]] && [[ "$(echo "SELECT IFNULL(slika,'NULL') FROM vijesti WHERE id=$ID" | SQL)" == "NULL" ]]; then ok "  → slika uklonjena iz baze i s diska"; else fail "  → slika nije uklonjena"; fi

naslov "Admin uređuje tuđu vijest, brisanje"
t=$(tok "$ADMIN" /admin/vijesti)
check "admin → uređivanje Tinove vijesti 4" "$(code -b "$ADMIN" "$B/admin/vijesti/4/uredi")" "200"
check "admin → sprema izmjenu vijesti 4" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Obnova javne rasvjete u Maksimiru (ažurirano)' --data-urlencode 'sazetak=Stara rasvjetna tijela zamjenjuju se LED rasvjetom.' \
    --data-urlencode 'sadrzaj=<p>Ažurirano.</p>' -d 'kategorija_id=4&status=objavljeno' "$B/admin/vijesti/4")" "303"
eq    "  → autor ostaje Tin (3)" "$(echo 'SELECT autor_id FROM vijesti WHERE id=4' | SQL)" "3"
eq    "  → datum objave nepromijenjen" "$(echo 'SELECT datum_objave FROM vijesti WHERE id=4' | SQL)" "2026-10-12 11:30:00"
t=$(tok "$LEA" /admin/vijesti)
check "urednica briše svoju vijest" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" "$B/admin/vijesti/$ID/obrisi")" "303 $B/admin/vijesti"
eq    "  → obrisana iz baze" "$(echo "SELECT COUNT(*) FROM vijesti WHERE id=$ID" | SQL)" "0"
check "  → ponovno brisanje → 404" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" "$B/admin/vijesti/$ID/obrisi")" "404"

naslov "Kategorije"
t=$(tok "$ADMIN" /admin/kategorije)
check "popis kategorija" "$(code -b "$ADMIN" "$B/admin/kategorije")" "200"
check "nova kategorija 'Kultura'" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naziv=Kultura' --data-urlencode 'opis=Kulturna događanja' "$B/admin/kategorije")" "303"
check "duplikat 'kultura' (mala slova)" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naziv=kultura' "$B/admin/kategorije")" "422"
has   "  → poruka o duplikatu" 'već postoji'
check "prekratak naziv" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naziv=K' "$B/admin/kategorije")" "422"
KID=$(echo "SELECT id FROM kategorije_vijesti WHERE naziv='Kultura'" | SQL)
check "uređivanje (?uredi=$KID)" "$(code -b "$ADMIN" "$B/admin/kategorije?uredi=$KID")" "200"
has   "  → obrazac popunjen" 'value="Kultura"'
check "preimenovanje u 'Kultura i sport'" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naziv=Kultura i sport' "$B/admin/kategorije/$KID")" "303"
check "preimenovanje u postojeći naziv 'Promet'" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naziv=Promet' "$B/admin/kategorije/$KID")" "422"
check "brisanje kategorije S vijestima (Promet, ima nacrt)" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" "$B/admin/kategorije/2/obrisi")" "303"
eq    "  → kategorija i dalje postoji" "$(echo 'SELECT COUNT(*) FROM kategorije_vijesti WHERE id=2' | SQL)" "1"
code -b "$ADMIN" "$B/admin/kategorije" >/dev/null; has "  → poruka korisniku" 'ne može se obrisati'
check "brisanje prazne kategorije 'Arhiva'" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" "$B/admin/kategorije/6/obrisi")" "303"
eq    "  → obrisana" "$(echo 'SELECT COUNT(*) FROM kategorije_vijesti WHERE id=6' | SQL)" "0"
check "brisanje nepostojeće kategorije" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" "$B/admin/kategorije/999/obrisi")" "404"

naslov "Korisnici i uloge"
check "popis korisnika" "$(code -b "$ADMIN" "$B/admin/korisnici")" "200"
has   "  → 8 korisnika" '8 korisnika'
has   "  → vlastiti račun bez obrasca" 'vlastiti račun'
code -b "$ADMIN" "$B/admin/korisnici?uloga=urednik" >/dev/null; has "filtar uloga=urednik" '2 korisnika'
code -b "$ADMIN" "$B/admin/korisnici?aktivan=0" >/dev/null; has "filtar deaktivirani" '1 korisnika'
code -b "$ADMIN" "$B/admin/korisnici?q=luka" >/dev/null; has "pretraga 'luka'" '1 korisnika'
check "Luka postaje urednik" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$(tok "$ADMIN" /admin/korisnici)" -d 'uloga=urednik&aktivan=1&povratak=/admin/korisnici?uloga=korisnik' "$B/admin/korisnici/7")" "303 $B/admin/korisnici?uloga=korisnik"
LUKA=$(prijava luka@zagreb-prijave.test)
check "  → Luka sad smije u CMS" "$(code -b "$LUKA" "$B/admin/vijesti")" "200"
check "aktivacija računa Petre (neaktivna)" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$(tok "$ADMIN" /admin/korisnici)" -d 'uloga=korisnik&aktivan=1' "$B/admin/korisnici/8")" "303"
J="$T/petra.jar"; tp=$(tok "$J" /racun/prijava)
check "  → Petra se može prijaviti" "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$tp" --data-urlencode email=neaktivna@zagreb-prijave.test --data-urlencode "lozinka=$LOZ" "$B/racun/prijava")" "303"
check "nepostojeći korisnik 999" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$(tok "$ADMIN" /admin/korisnici)" -d 'uloga=korisnik&aktivan=1' "$B/admin/korisnici/999")" "404"

reset_baze
sazetak
