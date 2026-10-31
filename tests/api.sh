#!/usr/bin/env bash
# =====================================================================
# Testovi REST API-ja (/api/v1). Pokretanje: bash tests/api.sh  (VRAĆA BAZU!)
# Hrvatski znakovi u JSON-u šalju se kao \uXXXX (ASCII), zbog Git Basha.
# =====================================================================
source "$(dirname "$0")/lib.sh"

reset_baze || { echo "Baza nije dostupna – je li MySQL pokrenut?"; exit 1; }
A="$B/api/v1"
napravi_png "$T/foto.png"
JSON=(-H 'Accept: application/json' -H 'Content-Type: application/json')
st() { echo "SELECT status FROM prijave WHERE id=$1" | SQL; }

naslov "Format odgovora i straničenje"
check "GET /vijesti" "$(code "$A/vijesti")" "200"
header_has "  → Content-Type: application/json" 'Content-Type: application/json; charset=utf-8'
eq    "  → meta.ukupno (samo objavljene)" "$(jv meta.ukupno)" "6"
eq    "  → data (zadano 10 po stranici)" "$(jv data)" "6"
hasnt "  → popis bez punog sadržaja" '"sadrzaj"'
has   "  → datum u ISO 8601" '"datum_objave":"2026-10-25T09:00:00+01:00"'
code "$A/vijesti?po_stranici=2" >/dev/null
eq    "po_stranici=2 → 2 vijesti, 3 stranice" "$(jv data)/$(jv meta.ukupno_stranica)" "2/3"
has   "  → poveznica na sljedeću stranicu" "\"sljedeca\":\"$BP/api/v1/vijesti?po_stranici=2&stranica=2\""
code "$A/vijesti?po_stranici=2&stranica=99" >/dev/null; eq "stranica=99 → zadnja" "$(jv meta.stranica)" "3"
code "$A/vijesti?po_stranici=5000" >/dev/null;           eq "po_stranici=5000 → najviše 50" "$(jv meta.po_stranici)" "50"
code "$A/vijesti?po_stranici=abc" >/dev/null;            eq "po_stranici=abc → zadano 10" "$(jv meta.po_stranici)" "10"
code "$A/vijesti?kategorija=3" >/dev/null;               eq "filtar kategorija=3" "$(jv meta.ukupno)" "2"
code "$A/vijesti?q=odvoz" >/dev/null;                    eq "filtar q=odvoz" "$(jv meta.ukupno)" "1"
check "GET /vijesti/1" "$(code "$A/vijesti/1")" "200"
has   "  → detalj sa sadržajem" '"sadrzaj":"<p>'
check "GET /kategorije" "$(code "$A/kategorije")" "200"
eq    "  → 6 kategorija" "$(jv data)" "6"
has   "  → Promet: 1 objavljena (nacrt se ne broji)" '"naziv":"Promet","broj_vijesti":1'

naslov "Neobjavljene vijesti ne izlaze kroz API"
code "$A/vijesti?po_stranici=50" >/dev/null
hasnt "popis bez nacrta 7, 8 i povučene 9" '"id":[789],'
check "GET /vijesti/7 (nacrt)" "$(code "$A/vijesti/7")" "404"
eq    "  → greska.status" "$(jv greska.status)" "404"
check "GET /vijesti/9 (povučena)" "$(code "$A/vijesti/9")" "404"
code "$A/vijesti?q=NACRT" >/dev/null; eq "pretraga riječi iz nacrta" "$(jv meta.ukupno)" "0"
code "$A/vijesti?kategorija=2" >/dev/null; eq "kategorija s nacrtom (Promet)" "$(jv meta.ukupno)" "1"

naslov "Pogreške: 404, 405, 400"
check "nepostojeći resurs" "$(code "$A/nepostoji")" "404"
has   "  → JSON pogreška" '"greska":{"status":404'
check "PUT /vijesti" "$(code -X PUT "$A/vijesti")" "405"
header_has "  → Allow" 'Allow: GET, POST'
check "GET /vijesti/abc" "$(code "$A/vijesti/abc")" "404"

naslov "Sesija (prijava preko API-ja)"
J="$T/api_anon.jar"
check "GET /sesija (gost)" "$(code -b "$J" -c "$J" "$A/sesija")" "200"
eq    "  → prijavljen=false" "$(jv data.prijavljen)" "false"
tG=$(jv data.csrf)
if [[ ${#tG} -eq 64 ]]; then ok "  → CSRF token (64 heksa znaka)"; else fail "  → CSRF token: '$tG'"; fi
check "POST /sesija bez CSRF tokena" "$(code -b "$J" "${JSON[@]}" --data '{"email":"ana@zagreb-prijave.test","lozinka":"x"}' "$A/sesija")" "403"
check "POST /sesija bez lozinke (shema)" "$(code -b "$J" "${JSON[@]}" -H "X-CSRF-Token: $tG" --data '{"email":"ana@zagreb-prijave.test"}' "$A/sesija")" "400"
has   "  → detalji: lozinka" '"lozinka"'
check "POST /sesija kriva lozinka" "$(code -b "$J" "${JSON[@]}" -H "X-CSRF-Token: $tG" --data '{"email":"ana@zagreb-prijave.test","lozinka":"kriva123"}' "$A/sesija")" "401"
check "POST /sesija bez tijela" "$(code -b "$J" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tG" -X POST "$A/sesija")" "400"
check "POST /sesija neispravan JSON" "$(code -b "$J" "${JSON[@]}" -H "X-CSRF-Token: $tG" --data '{"email":' "$A/sesija")" "400"
has   "  → poruka" 'nije ispravan JSON'
check "POST /sesija kao form-data (ne JSON)" "$(code -b "$J" -H "X-CSRF-Token: $tG" -d 'email=a&lozinka=b' "$A/sesija")" "400"
LEA=$(api_prijava urednik.lea@zagreb-prijave.test); tL=$(cat "$LEA.csrf")
code -b "$LEA" "$A/sesija" >/dev/null
eq    "urednica prijavljena preko API-ja" "$(jv data.korisnik.uloga)" "urednik"
check "DELETE /sesija bez prijave" "$(code -b "$J" -H "X-CSRF-Token: $tG" -X DELETE "$A/sesija")" "401"

naslov "POST /vijesti – JSON Schema (valjan i nevaljan primjer)"
# \xc4\x87 = "ć" u UTF-8; datoteka se šalje s --data-binary @ (bez pretvorbe argumenata)
printf '{"naslov":"Zatvaranje Savske ceste","sazetak":"Savska cesta bit \xc4\x87e zatvorena u subotu.","sadrzaj":"<p>Obilazak preko Vukovarske.</p><script>alert(1)</script>","kategorija_id":2}' > "$T/valjan.json"
VALJAN="@$TW/valjan.json"
NEVALJAN='{"naslov":"Ab","sazetak":42,"kategorija_id":"dva","status":"arhiva","autor_id":1}'
check "valjan primjer" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" --data-binary "$VALJAN" "$A/vijesti")" "201"
NID=$(jv data.id)
header_has "  → Location" "Location: $BP/api/v1/vijesti/$NID"
eq    "  → status nacrt (zadano), autor = Lea" "$(jv data.status)/$(jv data.autor_id)" "nacrt/2"
eq    "  → sažetak s č (UTF-8)" "$(echo "SELECT sazetak FROM vijesti WHERE id=$NID" | SQL)" "Savska cesta bit će zatvorena u subotu."
hasnt "  → <script> uklonjen (HTMLPurifier)" '<script>'
check "  → nacrt nije javan" "$(code "$A/vijesti/$NID")" "404"
check "nevaljan primjer" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" --data "$NEVALJAN" "$A/vijesti")" "400"
has   "  → naslov prekratak" '"naslov":"Must be at least 3 characters long"'
has   "  → sažetak nije string" '"sazetak":"Integer value found, but a string'
has   "  → nedostaje sadržaj" '"sadrzaj":"The property sadrzaj is required"'
has   "  → kategorija_id nije integer" '"kategorija_id":"String value found, but an integer'
has   "  → status izvan enuma" '"status":"Does not have a value in the enumeration'
has   "  → autor_id nije dopušten (mass assignment)" 'autor_id is not defined'
check "nepostojeća kategorija 999 (shema prolazi, servis ne)" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" --data '{"naslov":"Test vijest","sazetak":"Dovoljno dug sazetak.","sadrzaj":"<p>x</p>","kategorija_id":999}' "$A/vijesti")" "400"
has   "  → detalji: kategorija_id" '"kategorija_id":"Odaberite kategoriju."'
eq    "  → ništa nije spremljeno" "$(echo "SELECT COUNT(*) FROM vijesti" | SQL)" "10"
tA=$(curl -s -o "$T/body" -b "$J" -c "$J" "$A/sesija"; jv data.csrf)
check "gost s ispravnim tokenom → 401" "$(code -b "$J" "${JSON[@]}" -H "X-CSRF-Token: $tA" --data-binary "$VALJAN" "$A/vijesti")" "401"
ANA=$(api_prijava ana@zagreb-prijave.test); tAna=$(cat "$ANA.csrf")
check "korisnica Ana → 403" "$(code -b "$ANA" "${JSON[@]}" -H "X-CSRF-Token: $tAna" --data-binary "$VALJAN" "$A/vijesti")" "403"

naslov "PATCH i DELETE /vijesti/{id}"
check "objava vlastite vijesti" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" -X PATCH --data '{"status":"objavljeno"}' "$A/vijesti/$NID")" "200"
eq    "  → status, naslov nepromijenjen" "$(jv data.status)/$(jv data.naslov)" "objavljeno/Zatvaranje Savske ceste"
if [[ "$(jv data.datum_objave)" == 2026-* ]]; then ok "  → datum objave postavljen"; else fail "  → datum objave: $(jv data.datum_objave)"; fi
check "  → sad je javna" "$(code "$A/vijesti/$NID")" "200"
check "prazan PATCH {}" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" -X PATCH --data '{}' "$A/vijesti/$NID")" "400"
check "PATCH s autor_id" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" -X PATCH --data '{"autor_id":1}' "$A/vijesti/$NID")" "400"
check "Lea → Tinova vijest 4 (IDOR)" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" -X PATCH --data '{"naslov":"Preuzeto"}' "$A/vijesti/4")" "403"
check "Lea → brisanje Tinove vijesti 4" "$(code -b "$LEA" -H "X-CSRF-Token: $tL" -X DELETE "$A/vijesti/4")" "403"
check "PATCH bez CSRF tokena" "$(code -b "$LEA" "${JSON[@]}" -X PATCH --data '{"naslov":"Bez tokena"}' "$A/vijesti/$NID")" "403"
check "PATCH nepostojeće vijesti" "$(code -b "$LEA" "${JSON[@]}" -H "X-CSRF-Token: $tL" -X PATCH --data '{"naslov":"Nema je"}' "$A/vijesti/999")" "404"
check "DELETE vlastite vijesti" "$(code -b "$LEA" -H "X-CSRF-Token: $tL" -X DELETE "$A/vijesti/$NID")" "204"
if [[ ! -s "$T/body" ]]; then ok "  → 204 bez tijela"; else fail "  → 204 ima tijelo"; fi
check "  → nakon brisanja 404" "$(code "$A/vijesti/$NID")" "404"

naslov "Prijave: javni sažetak i detalj"
check "GET /prijave (gost)" "$(code "$A/prijave")" "200"
eq    "  → 10 prijava" "$(jv meta.ukupno)" "10"
hasnt "  → bez opisa" '"opis"'
hasnt "  → bez podataka o prijavitelju" 'prijavitelj'
hasnt "  → bez e-mailova" '@zagreb-prijave.test'
code "$A/prijave?status=PRIJAVLJENO" >/dev/null
eq    "prijava 2 (najnovija prijavljena) nema lokaciju" "$(jv data.0.id)/$(jv data.0.lokacija)" "2/null"
code "$A/prijave?status=ZAVRSENO" >/dev/null; eq "filtar status=ZAVRSENO" "$(jv meta.ukupno)" "2"
code "$A/prijave?cetvrt=1" >/dev/null;        eq "filtar cetvrt=1" "$(jv meta.ukupno)" "1"
check "?moje=1 bez prijave" "$(code "$A/prijave?moje=1")" "401"
code -b "$ANA" "$A/prijave?moje=1" >/dev/null; eq "Ana ?moje=1" "$(jv meta.ukupno)" "5"
check "GET /prijave/1 bez prijave" "$(code "$A/prijave/1")" "401"
LUKA=$(api_prijava luka@zagreb-prijave.test); tLuka=$(cat "$LUKA.csrf")
check "Luka → Anina prijava 1" "$(code -b "$LUKA" "$A/prijave/1")" "403"
check "Ana → svoja prijava 7" "$(code -b "$ANA" "$A/prijave/7")" "200"
eq    "  → opis" "$(jv data.opis)" "Velika grana nakon nevremena blokira prolaz pješacima."
eq    "  → bez imena prijavitelja i radnika" "$(jv data.prijavitelj)/$(jv data.radnik)" "null/null"
hasnt "  → bez internog komentara" 'Na slici se ne vidi'
eq    "  → slika 'poslije' skrivena (samo 1 slika)" "$(jv data.slike)" "1"
ADMIN=$(api_prijava admin@zagreb-prijave.test); tAd=$(cat "$ADMIN.csrf")
code -b "$ADMIN" "$A/prijave/7" >/dev/null
has   "admin → komentar u povijesti" 'Na slici se ne vidi'
eq    "  → radnik" "$(jv data.radnik.id)" "4"
eq    "  → 2 slike" "$(jv data.slike)" "2"

naslov "POST /prijave (multipart)"
check "nova prijava sa slikom" "$(code -b "$ANA" -H 'Accept: application/json' -H "X-CSRF-Token: $tAna" -F cetvrt_id=4 -F vrsta_id=2 -F 'naslov=Ne radi lampa' -F 'opis=Lampa ne svijetli tjedan dana.' -F "slika=@$TW/foto.png" "$A/prijave")" "201"
PID=$(jv data.id)
eq    "  → status/hitnost postavlja sustav" "$(jv data.status)/$(jv data.hitnost)" "PRIJAVLJENO/srednja"
header_has "  → Location" "Location: $BP/api/v1/prijave/$PID"
check "bez slike" "$(code -b "$ANA" -H 'Accept: application/json' -H "X-CSRF-Token: $tAna" -F cetvrt_id=4 -F vrsta_id=2 -F 'naslov=Ne radi lampa' -F 'opis=Lampa ne svijetli tjedan dana.' "$A/prijave")" "400"
has   "  → detalji: slika" '"slika":"Fotografija problema je obavezna."'
check "kao JSON umjesto multiparta" "$(code -b "$ANA" "${JSON[@]}" -H "X-CSRF-Token: $tAna" --data '{"naslov":"x"}' "$A/prijave")" "400"

naslov "PATCH /prijave/{id} – tok rada"
IVAN=$(api_prijava radnik.ivan@zagreb-prijave.test); tI=$(cat "$IVAN.csrf")
check "Ivan: 3 → U_RADU" "$(code -b "$IVAN" "${JSON[@]}" -H "X-CSRF-Token: $tI" -X PATCH --data '{"status":"U_RADU"}' "$A/prijave/3")" "200"
eq    "  → status" "$(jv data.status)" "U_RADU"
check "Ivan: ponovno U_RADU → 409" "$(code -b "$IVAN" "${JSON[@]}" -H "X-CSRF-Token: $tI" -X PATCH --data '{"status":"U_RADU"}' "$A/prijave/3")" "409"
check "Ivan: Markova prijava 5 → 403" "$(code -b "$IVAN" "${JSON[@]}" -H "X-CSRF-Token: $tI" -X PATCH --data '{"status":"U_RADU"}' "$A/prijave/5")" "403"
check "Ivan: sam ZAVRSENO → 403" "$(code -b "$IVAN" "${JSON[@]}" -H "X-CSRF-Token: $tI" -X PATCH --data '{"status":"ZAVRSENO"}' "$A/prijave/3")" "403"
check "status CEKA_PROVJERU preko JSON-a (treba sliku) → 400" "$(code -b "$IVAN" "${JSON[@]}" -H "X-CSRF-Token: $tI" -X PATCH --data '{"status":"CEKA_PROVJERU"}' "$A/prijave/3")" "400"
check "admin: odobri prijavu U RADU → 409" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"status":"ZAVRSENO"}' "$A/prijave/3")" "409"
check "admin: vrati bez komentara → 400 (shema if/then)" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"status":"VRACENO"}' "$A/prijave/6")" "400"
check "admin: vrati s komentarom" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"status":"VRACENO","komentar":"Rub nije poravnat."}' "$A/prijave/6")" "200"
eq    "  → VRACENO" "$(st 6)" "VRACENO"
check "admin: dodjela prijave 1 Ivanu" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"radnik_id":4}' "$A/prijave/1")" "200"
eq    "  → DODIJELJENO, radnik 4" "$(jv data.status)/$(jv data.radnik.id)" "DODIJELJENO/4"
check "admin: hitnost" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"hitnost":"kriticna"}' "$A/prijave/2")" "200"
eq    "  → kritična" "$(jv data.hitnost)" "kriticna"
check "dvije radnje odjednom (oneOf) → 400" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"radnik_id":4,"hitnost":"niska"}' "$A/prijave/2")" "400"
check "dodjela korisnici (nije radnik) → 400" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"radnik_id":6}' "$A/prijave/2")" "400"
check "dodjela završene prijave → 409" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"radnik_id":5}' "$A/prijave/9")" "409"
check "Ana mijenja hitnost → 403" "$(code -b "$ANA" "${JSON[@]}" -H "X-CSRF-Token: $tAna" -X PATCH --data '{"hitnost":"kriticna"}' "$A/prijave/1")" "403"
check "nepostojeća prijava → 404" "$(code -b "$ADMIN" "${JSON[@]}" -H "X-CSRF-Token: $tAd" -X PATCH --data '{"hitnost":"niska"}' "$A/prijave/999")" "404"

naslov "Radovi"
check "GET /radovi" "$(code "$A/radovi")" "200"
eq    "  → 5 radova" "$(jv meta.ukupno)" "5"
has   "  → lokacija" '"lokacija":{"lat":45.7995,"lng":15.979}'
code "$A/radovi?aktivni=1" >/dev/null
eq    "aktivni=1" "$(jv meta.ukupno)" "$(echo 'SELECT COUNT(*) FROM radovi WHERE pocetak <= NOW() AND (kraj IS NULL OR kraj >= NOW())' | SQL)"
code "$A/radovi?vrsta=cesta" >/dev/null; eq "vrsta=cesta" "$(jv meta.ukupno)" "1"
code -G --data-urlencode "vrsta=x' OR '1'='1" "$A/radovi" >/dev/null; eq "nepoznata vrsta (SQLi) ignorirana" "$(jv meta.ukupno)" "5"

naslov "Frontend koristi API"
code "$B/vijesti" >/dev/null
has   "stranica vijesti učitava vijesti.js" 'js/vijesti.js'
has   "  → s adresom API-ja" "data-api=\"$BP/api/v1/vijesti\""
if grep -q "api/v1/prijave" "$KORIJEN/public/assets/js/kanban.js"; then ok "kanban mijenja status preko PATCH /api/v1/prijave/{id}"; else fail "kanban ne koristi API"; fi
code -b "$ANA" -H "X-CSRF-Token: $tAna" -X DELETE "$A/sesija" >/dev/null
check "odjava preko API-ja" "$(code -b "$ANA" "$A/prijave/7")" "401"

reset_baze
sazetak
