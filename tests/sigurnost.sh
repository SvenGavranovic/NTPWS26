#!/usr/bin/env bash
# =====================================================================
# Sigurnosni testovi: poznate metode napada na web aplikaciju (OWASP Top 10)
# izvedene izvana, kao što bi ih izveo napadač – samo HTTP zahtjevima.
#
# Pokretanje (Git Bash, uz pokrenuti Apache i MySQL):
#     bash tests/sigurnost.sh
#
# UPOZORENJE: skripta na početku i na kraju VRAĆA BAZU NA POČETNE PODATKE
# (schema.sql + seed.sql). Pokrećite je samo na razvojnoj bazi.
#
# Skripta se proširuje u svakoj fazi (prijave, API, upload, TomTom...).
# =====================================================================
source "$(dirname "$0")/lib.sh"

reset_baze || { echo "Baza nije dostupna – je li MySQL pokrenut?"; exit 1; }
LOG="$KORIJEN/storage/logs/app.log"
LOG_PRIJE=$(cat "$LOG" 2>/dev/null | wc -l)

ADMIN=$(prijava admin@zagreb-prijave.test)
LEA=$(prijava urednik.lea@zagreb-prijave.test)
ANA=$(prijava ana@zagreb-prijave.test)

# ---------------------------------------------------------------------
naslov "1. SQL injection (A03 Injection)"
# Napadač u polja upisuje SQL u nadi da će ga baza izvršiti.
# Obrana: PDO prepared statements – vrijednost NIKAD nije dio SQL teksta.
# ---------------------------------------------------------------------
J="$T/anon.jar"; t=$(tok "$J" /racun/prijava)
check "login: email = admin@...' -- (zaobilazak lozinke)" \
    "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" --data-urlencode "email=admin@zagreb-prijave.test' -- " --data-urlencode "lozinka=bilošto" "$B/racun/prijava")" "401"
check "login: email = ' OR '1'='1' -- " \
    "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" --data-urlencode "email=' OR '1'='1' -- " --data-urlencode "lozinka=x" "$B/racun/prijava")" "401"
check "login: lozinka = ' OR '1'='1" \
    "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" --data-urlencode "email=admin@zagreb-prijave.test" --data-urlencode "lozinka=' OR '1'='1" "$B/racun/prijava")" "401"

check "pretraga: ' OR 1=1 -- " "$(code -G --data-urlencode "q=' OR 1=1 -- " "$B/vijesti")" "200"
has   "  → 0 rezultata (upit nije promijenjen)" 'Nema pronađenih vijesti'
check "pretraga: UNION SELECT lozinki" "$(code -G --data-urlencode "q=%' UNION SELECT id,email,lozinka_hash,4,5,6,7,8,9 FROM korisnici -- " "$B/vijesti")" "200"
hasnt "  → nema hasheva lozinki u odgovoru" '\$2y\$'
hasnt "  → nema e-mailova korisnika" 'admin@zagreb-prijave.test'

pocetak=$(date +%s)
code -G --data-urlencode "q=' AND SLEEP(5) -- " "$B/vijesti" >/dev/null
trajanje=$(( $(date +%s) - pocetak ))
if (( trajanje < 3 )); then ok "time-based blind SQLi (SLEEP(5)) se ne izvršava ($trajanje s)"; else fail "SLEEP se izvršio ($trajanje s)"; fi

check "kategorija=1 OR 1=1" "$(code "$B/vijesti?kategorija=1%20OR%201=1")" "200"
hasnt "  → nacrti nisu procurili" 'NACRT'
check "stranica=1;DROP TABLE vijesti" "$(code "$B/vijesti?stranica=1;DROP%20TABLE%20vijesti")" "200"
eq    "  → tablica vijesti i dalje postoji" "$(echo 'SELECT COUNT(*) FROM vijesti' | SQL)" "9"
check "/vijesti/1 OR 1=1" "$(code "$B/vijesti/1%20OR%201=1")" "404"
check "/vijesti/7' (nacrt + navodnik)" "$(code "$B/vijesti/7%27")" "404"
check "admin pretraga korisnika: x' OR '1'='1" "$(code -b "$ADMIN" -G --data-urlencode "q=x' OR '1'='1" "$B/admin/korisnici")" "200"
has   "  → 0 korisnika" '0 korisnika'
check "admin filtar uloge: admin' OR '1'='1" "$(code -b "$ADMIN" -G --data-urlencode "uloga=admin' OR '1'='1" "$B/admin/korisnici")" "200"
has   "  → filtar ignoriran (svih 8 korisnika)" '8 korisnika'
check "CMS pretraga vijesti: ' UNION SELECT" "$(code -b "$LEA" -G --data-urlencode "q=' UNION SELECT 1,2,3,4,5,6,7,8 -- " "$B/admin/vijesti")" "200"
hasnt "  → bez SQL pogreške u odgovoru" 'SQLSTATE'

# ---------------------------------------------------------------------
naslov "2. Cross-site scripting – XSS (A03 Injection)"
# Napadač ubacuje JavaScript koji bi se izvršio u pregledniku drugog korisnika.
# Obrana: e() (htmlspecialchars) na svakom ispisu, HTMLPurifier za rich-text.
# ---------------------------------------------------------------------
code -G --data-urlencode "q=<script>alert(1)</script>" "$B/vijesti" >/dev/null
hasnt "reflektirani XSS u pretrazi" '<script>alert(1)'
code -G --data-urlencode 'q="><svg onload=alert(1)>' "$B/vijesti" >/dev/null
hasnt "izlazak iz atributa value=\"...\"" '"><svg'
code "$B/vijesti/%3Cscript%3Ealert(1)%3C%2Fscript%3E" >/dev/null
hasnt "XSS kroz URL na stranici 404" '<script>alert'

t=$(tok "$ANA" /racun/profil)
check "pohranjeni XSS u imenu profila → odbijeno" \
    "$(code -b "$ANA" -X POST --data-urlencode "_csrf=$t" --data-urlencode "ime=<script>alert(1)</script>" --data-urlencode "prezime=Test" "$B/racun/profil")" "422"

ZLO='<p>Siguran tekst</p><script>alert("xss")</script><p onclick="ukradi()">Klik</p><a href="javascript:alert(1)">poveznica</a><img src=x onerror="alert(1)"><iframe src="https://napadac.example"></iframe><p style="position:fixed">stil</p><a href="https://zagreb.hr">ispravna</a>'
t=$(tok "$LEA" /admin/vijesti/nova)
check "urednica sprema vijest sa zlonamjernim HTML-om" \
    "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=<b>XSS</b> naslov "test"' \
        --data-urlencode "sazetak=Sažetak s <script>alert(2)</script> oznakom" --data-urlencode "sadrzaj=$ZLO" \
        --data-urlencode kategorija_id=1 --data-urlencode status=objavljeno "$B/admin/vijesti")" "303"
XID=$(echo "SELECT MAX(id) FROM vijesti" | SQL)
code "$B/vijesti/$XID" >/dev/null
has   "  → dopušteni HTML ostaje" '<p>Siguran tekst</p>'
has   "  → ispravna poveznica ostaje (uz rel=noopener)" 'href="https://zagreb.hr"'
hasnt "  → <script> uklonjen iz sadržaja" 'alert("xss")'
hasnt "  → onclick uklonjen" 'onclick'
hasnt "  → javascript: URL uklonjen" 'javascript:'
hasnt "  → <img onerror> uklonjen" 'onerror'
hasnt "  → <iframe> uklonjen" '<iframe'
hasnt "  → style atribut uklonjen" 'position:fixed'
has   "  → naslov escapean" '&lt;b&gt;XSS&lt;/b&gt;'
hasnt "  → sažetak escapean" '<script>alert(2)'
eq    "  → u bazi je već očišćen sadržaj (bez <script>)" "$(echo "SELECT sadrzaj LIKE '%<script%' FROM vijesti WHERE id=$XID" | SQL)" "0"

# ---------------------------------------------------------------------
naslov "3. Cross-site request forgery – CSRF (A01)"
# Tuđa stranica natjera preglednik prijavljenog korisnika da pošalje zahtjev.
# Obrana: CSRF token u svakom POST-u + kolačić SameSite=Lax.
# ---------------------------------------------------------------------
check "promjena profila bez tokena" "$(code -b "$ANA" -X POST -d 'ime=Hak&prezime=Hak' "$B/racun/profil")" "403"
tL=$(tok "$LEA" /racun/profil)
check "token iz TUĐE sesije" "$(code -b "$ANA" -X POST --data-urlencode "_csrf=$tL" -d 'ime=Hak&prezime=Hak' "$B/racun/profil")" "403"
check "krivi token u zaglavlju X-CSRF-Token" "$(code -b "$ANA" -X POST -H 'X-CSRF-Token: abc' -d 'ime=Hak&prezime=Hak' "$B/racun/profil")" "403"
check "admin: promjena uloge bez tokena" "$(code -b "$ADMIN" -X POST -d 'uloga=admin&aktivan=1' "$B/admin/korisnici/6")" "403"
check "urednik: brisanje vijesti bez tokena" "$(code -b "$LEA" -X POST "$B/admin/vijesti/7/obrisi")" "403"
check "odjava GET zahtjevom (npr. <img src=.../odjava>)" "$(code -b "$ANA" "$B/racun/odjava")" "405"
eq    "  → ime Ane nije promijenjeno" "$(echo 'SELECT ime FROM korisnici WHERE id=6' | SQL)" "Ana"
code "$B/racun/prijava" >/dev/null
header_has "kolačić sesije ima HttpOnly (JS ga ne može ukrasti)" 'Set-Cookie: ZGPRIJAVE=.*HttpOnly'
header_has "kolačić sesije ima SameSite=Lax" 'Set-Cookie: ZGPRIJAVE=.*SameSite=Lax'

# ---------------------------------------------------------------------
naslov "4. Sesije: fixation, odjava, deaktivacija (A07)"
# ---------------------------------------------------------------------
code -b 'ZGPRIJAVE=napadacevid1234567890abcdef' "$B/racun/prijava" >/dev/null
if grep -qi 'Set-Cookie: ZGPRIJAVE=' "$T/headers" && ! grep -qi 'Set-Cookie: ZGPRIJAVE=napadacevid' "$T/headers"; then
    ok "podmetnuti ID sesije se odbija (strict mode izdaje novi)"
else fail "podmetnuti ID sesije je prihvaćen"; fi

J="$T/fix.jar"; t=$(tok "$J" /racun/prijava)
prije=$(grep ZGPRIJAVE "$J" | awk '{print $7}')
curl -s -o /dev/null -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" --data-urlencode email=luka@zagreb-prijave.test --data-urlencode "lozinka=$LOZ" "$B/racun/prijava"
poslije=$(grep ZGPRIJAVE "$J" | awk '{print $7}')
if [[ -n "$prije" && -n "$poslije" && "$prije" != "$poslije" ]]; then ok "nakon prijave novi ID sesije (session_regenerate_id)"; else fail "ID sesije isti prije i poslije prijave"; fi

cp "$J" "$T/stari.jar"
t=$(tok "$J" /racun/profil)
curl -s -o /dev/null -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" "$B/racun/odjava"
check "stari kolačić nakon odjave ne vrijedi" "$(code -b "$T/stari.jar" "$B/racun/profil")" "303 $B/racun/prijava"

LUKA=$(prijava luka@zagreb-prijave.test)
check "Luka prijavljen" "$(code -b "$LUKA" "$B/racun/profil")" "200"
t=$(tok "$ADMIN" /admin/korisnici)
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=korisnik' "$B/admin/korisnici/7" >/dev/null   # bez aktivan=1 → deaktivacija
check "deaktivacija prekida VEĆ OTVORENU sesiju" "$(code -b "$LUKA" "$B/racun/profil")" "303 $B/racun/prijava"
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=korisnik&aktivan=1' "$B/admin/korisnici/7" >/dev/null

# ---------------------------------------------------------------------
naslov "5. Kontrola pristupa: IDOR, eskalacija ovlasti (A01)"
# Napadač mijenja URL/ID ili šalje polja koja obrazac nema.
# ---------------------------------------------------------------------
check "gost → /admin" "$(code "$B/admin")" "303 $B/racun/prijava"
check "gost → /admin/vijesti/nova" "$(code "$B/admin/vijesti/nova")" "303 $B/racun/prijava"
check "korisnik → /admin" "$(code -b "$ANA" "$B/admin")" "403"
check "korisnik → /admin/vijesti" "$(code -b "$ANA" "$B/admin/vijesti")" "403"
t=$(tok "$ANA" /racun/profil)
check "korisnik → POST sebi uloga admin" "$(code -b "$ANA" -X POST --data-urlencode "_csrf=$t" -d 'uloga=admin&aktivan=1' "$B/admin/korisnici/6")" "403"
eq    "  → Ana je i dalje korisnik" "$(echo 'SELECT uloga_id FROM korisnici WHERE id=6' | SQL)" "1"

check "urednik → /admin" "$(code -b "$LEA" "$B/admin")" "403"
check "urednik → /admin/korisnici" "$(code -b "$LEA" "$B/admin/korisnici")" "403"
check "urednik → /admin/kategorije" "$(code -b "$LEA" "$B/admin/kategorije")" "403"
check "urednica Lea → TUĐA vijest 4 (Tin) uredi" "$(code -b "$LEA" "$B/admin/vijesti/4/uredi")" "403"
t=$(tok "$LEA" /admin/vijesti)
check "urednica Lea → POST izmjena tuđe vijesti 4" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Preuzeto' --data-urlencode 'sazetak=Hakirani sažetak vijesti' --data-urlencode 'sadrzaj=<p>x</p>' -d 'kategorija_id=1&status=objavljeno' "$B/admin/vijesti/4")" "403"
check "urednica Lea → brisanje tuđe vijesti 4" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$t" "$B/admin/vijesti/4/obrisi")" "403"
eq    "  → vijest 4 netaknuta" "$(echo 'SELECT naslov FROM vijesti WHERE id=4' | SQL)" "Obnova javne rasvjete u Maksimiru"
code -b "$LEA" "$B/admin/vijesti" >/dev/null
hasnt "urednica u popisu NE vidi tuđe vijesti" 'Obnova javne rasvjete'
has   "urednica vidi svoje nacrte" 'Izmjene prometa za blagdane'

t=$(tok "$LEA" /admin/vijesti/nova)
code -b "$LEA" -X POST --data-urlencode "_csrf=$t" --data-urlencode 'naslov=Podmetnuti autor' --data-urlencode 'sazetak=Pokušaj podmetanja autora' \
    --data-urlencode 'sadrzaj=<p>tekst</p>' -d 'kategorija_id=1&status=nacrt&autor_id=1&datum_objave=2020-01-01' "$B/admin/vijesti" >/dev/null
eq    "mass assignment: autor_id iz obrasca ignoriran" "$(echo "SELECT CONCAT(autor_id,'/',IFNULL(datum_objave,'NULL')) FROM vijesti WHERE naslov='Podmetnuti autor'" | SQL)" "2/NULL"

J="$T/reg.jar"; t=$(tok "$J" /racun/registracija)
code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d 'ime=Novi&prezime=Korisnik&email=novi@zagreb-prijave.test&lozinka=Lozinka123&lozinka_potvrda=Lozinka123&uloga=admin&uloga_id=4&aktivan=1' "$B/racun/registracija" >/dev/null
eq    "mass assignment: registracija s uloga=admin → korisnik" "$(echo "SELECT u.kod FROM korisnici k JOIN uloge u ON u.id=k.uloga_id WHERE email='novi@zagreb-prijave.test'" | SQL)" "korisnik"

t=$(tok "$ADMIN" /admin/korisnici)
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=korisnik' "$B/admin/korisnici/1" >/dev/null
eq    "admin ne može sebi oduzeti ulogu ni deaktivirati se" "$(echo 'SELECT CONCAT(uloga_id,aktivan) FROM korisnici WHERE id=1' | SQL)" "41"
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=superadmin&aktivan=1' "$B/admin/korisnici/6" >/dev/null
eq    "nepostojeća uloga 'superadmin' odbijena" "$(echo 'SELECT uloga_id FROM korisnici WHERE id=6' | SQL)" "1"
check "nacrt preko javnog URL-a (forced browsing)" "$(code "$B/vijesti/7")" "404"

# ---------------------------------------------------------------------
naslov "6. Upload zlonamjernih datoteka (A04/A05)"
# Cilj napadača: postaviti PHP skriptu (web shell) i pokrenuti je preko URL-a.
# ---------------------------------------------------------------------
broj_slika() { ls "$KORIJEN/public/uploads/vijesti" 2>/dev/null | wc -l; }
napravi_png "$T/ok.png"
printf '<?php echo strrev("DENWP"); ?>' > "$T/shell.php"
cp "$T/shell.php" "$T/shell.jpg"
cp "$T/shell.php" "$T/shell.php.png"
printf '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' > "$T/zlo.svg"
{ cat "$T/ok.png"; printf '<?php echo strrev("DENWP"); ?>'; } > "$T/poliglot.php"
{ cat "$T/ok.png"; head -c 3000000 /dev/zero; } > "$T/velika.png"

posalji() {  # posalji DATOTEKA[;type=...] → status
    local t; t=$(tok "$LEA" /admin/vijesti/nova)
    code -b "$LEA" -X POST -F "_csrf=$t" -F 'naslov=Test uploada' -F 'sazetak=Testiranje uploada datoteke' \
        --form-string 'sadrzaj=<p>tekst</p>' -F kategorija_id=1 -F status=nacrt -F "slika=@$1" "$B/admin/vijesti"
}
prije=$(broj_slika)
check "PHP skripta shell.php" "$(posalji "$TW/shell.php")" "422"
check "PHP skripta preimenovana u shell.jpg + lažni Content-Type" "$(posalji "$TW/shell.jpg;type=image/jpeg")" "422"
check "dvostruka ekstenzija shell.php.png" "$(posalji "$TW/shell.php.png;type=image/png")" "422"
check "SVG sa <script>" "$(posalji "$TW/zlo.svg;type=image/svg+xml")" "422"
check "prevelika slika (3 MB)" "$(posalji "$TW/velika.png;type=image/png")" "422"
eq    "  → nijedna odbijena datoteka nije spremljena" "$(broj_slika)" "$prije"

check "poliglot (ispravan PNG + PHP kod) pod imenom .php" "$(posalji "$TW/poliglot.php")" "303"
PIME=$(echo "SELECT slika FROM vijesti ORDER BY id DESC LIMIT 1" | SQL)
if [[ "$PIME" =~ ^[a-f0-9]{32}\.png$ ]]; then ok "  → spremljen pod nasumičnim imenom .png ($PIME)"; else fail "  → neočekivano ime: $PIME"; fi
check "  → dohvat datoteke" "$(code "$B/uploads/vijesti/$PIME")" "200"
hasnt "  → PHP kod se NE izvršava" 'PWNED'
header_has "  → poslužena kao image/png" 'Content-Type: image/png'

printf '<?php echo strrev("DENWP"); ?>' > "$KORIJEN/public/uploads/vijesti/podmetnuto.php"
code "$B/uploads/vijesti/podmetnuto.php" >/dev/null
hasnt "PHP datoteka podmetnuta u uploads/ se NE izvršava" 'PWNED'
check "  → pristup zabranjen" "$(code "$B/uploads/vijesti/podmetnuto.php")" "403"
rm -f "$KORIJEN/public/uploads/vijesti/podmetnuto.php"
s=$(code "$B/uploads/vijesti/"); if [[ "$s" == 403* || "$s" == 404* ]] && ! grep -q "Index of" "$T/body"; then ok "popis datoteka u uploads/ (directory listing) → ${s%% *}"; else fail "directory listing → $s"; fi
t=$(tok "$LEA" /admin/vijesti/nova)
check "slika[] kao niz datoteka ne ruši aplikaciju" "$(code -b "$LEA" -X POST -F "_csrf=$t" -F 'naslov=Niz' -F 'sazetak=Niz datoteka u uploadu' --form-string 'sadrzaj=<p>x</p>' -F kategorija_id=1 -F status=nacrt -F "slika[]=@$TW/ok.png" "$B/admin/vijesti")" "303"

# ---------------------------------------------------------------------
naslov "7. Osjetljive datoteke i path traversal (A05)"
# ---------------------------------------------------------------------
for p in /.env /.env.example /src/Core/Auth.php /config/routes.php /database/seed.sql /vendor/autoload.php \
         /composer.json /storage/logs/app.log /.git/config /tests/lib.sh /public/../.env /vijesti/..%2f..%2f.env \
         /uploads/../../.env /index.php/../.env; do
    s=$(code --path-as-is "$B$p")
    if [[ "$s" == 404* || "$s" == 403* || "$s" == 400* ]] && ! grep -q 'DB_PASS\|<?php\|lozinka_hash\|\[core\]' "$T/body"; then
        ok "$p → ${s%% *}"
    else fail "$p → $s (datoteka dostupna?)"; fi
done

# ---------------------------------------------------------------------
naslov "8. Otkrivanje informacija i sigurnosna zaglavlja (A05)"
# ---------------------------------------------------------------------
code "$B/vijesti/999999" >/dev/null
hasnt "stranica pogreške bez putanja na disku" 'xampp'
hasnt "stranica pogreške bez stack tracea" 'Stack trace'
code "$B/" >/dev/null
header_hasnt "nema X-Powered-By (verzija PHP-a)" '^X-Powered-By'
header_has "X-Frame-Options: DENY (clickjacking)" 'X-Frame-Options: DENY'
header_has "X-Content-Type-Options: nosniff" 'X-Content-Type-Options: nosniff'
header_has "Referrer-Policy" 'Referrer-Policy: same-origin'
echo "  INFO  zaglavlje Server: $(grep -i '^Server:' "$T/headers" | tr -d '\r') (podešava se u httpd.conf: ServerTokens Prod)"

J="$T/enum.jar"; t=$(tok "$J" /racun/prijava)
a=$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d 'email=nepostoji@zagreb-prijave.test&lozinka=kriva123' "$B/racun/prijava"); a_msg=$(grep -o 'alert-danger[^<]*>[^<]*' "$T/body")
b=$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d 'email=luka@zagreb-prijave.test&lozinka=kriva123' "$B/racun/prijava"); b_msg=$(grep -o 'alert-danger[^<]*>[^<]*' "$T/body")
if [[ "$a" == "$b" && "$a_msg" == "$b_msg" ]]; then ok "enumeracija korisnika: isti odgovor za nepostojeći e-mail i krivu lozinku"; else fail "različit odgovor: '$a' '$a_msg' vs '$b' '$b_msg'"; fi

# ---------------------------------------------------------------------
naslov "9. HTTP metode, open redirect, CRLF injection"
# ---------------------------------------------------------------------
check "PUT /racun/profil" "$(code -b "$ANA" -X PUT "$B/racun/profil")" "405"
check "DELETE /admin/vijesti/1" "$(code -b "$ADMIN" -X DELETE "$B/admin/vijesti/1")" "405"
header_has "  → zaglavlje Allow" 'Allow: POST'
t=$(tok "$ADMIN" /admin/korisnici)
check "open redirect: povratak=https://napadac.example" \
    "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=korisnik&aktivan=1' --data-urlencode 'povratak=https://napadac.example' "$B/admin/korisnici/6")" "303 $B/admin/korisnici"
check "open redirect: povratak=//napadac.example" \
    "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=korisnik&aktivan=1' --data-urlencode 'povratak=//napadac.example/admin/korisnici' "$B/admin/korisnici/6")" "303 $B/admin/korisnici"
check "CRLF injection u preusmjeravanju" \
    "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$t" -d 'uloga=korisnik&aktivan=1' --data-urlencode $'povratak=/admin/korisnici\r\nSet-Cookie: zlo=1' "$B/admin/korisnici/6")" "303 $B/admin/korisnici"
header_hasnt "  → nema podmetnutog kolačića" 'Set-Cookie: zlo'

# ---------------------------------------------------------------------
naslov "10. Prijave problema: IDOR, slike izvan webroota, upload (A01/A04)"
# Prijave sadrže osobne podatke i fotografije – vidi ih samo podnositelj,
# administrator i dodijeljeni radnik.
# ---------------------------------------------------------------------
sid() { echo "SELECT id FROM prijava_slike WHERE prijava_id=$1 AND tip='$2'" | SQL; }
ANA=$(prijava ana@zagreb-prijave.test)
LUKA=$(prijava luka@zagreb-prijave.test)
check "Luka → Anina prijava 1 (IDOR)" "$(code -b "$LUKA" "$B/prijave/1")" "403"
check "Luka → slika Anine prijave" "$(code -b "$LUKA" "$B/prijave/1/slike/$(sid 1 prije)")" "403"
check "Ana → Lukina slika preko SVOJE prijave (/prijave/1/slike/<slika prijave 2>)" "$(code -b "$ANA" "$B/prijave/1/slike/$(sid 2 prije)")" "404"
check "gost → slika prijave" "$(code "$B/prijave/1/slike/$(sid 1 prije)")" "303 $B/racun/prijava"
for p in /storage/uploads/prijave/demo-p01-prije.png /uploads/prijave/demo-p01-prije.png /public/../storage/uploads/prijave/demo-p01-prije.png; do
    s=$(code --path-as-is "$B$p")
    if [[ "$s" == 404* || "$s" == 403* || "$s" == 400* ]] && ! grep -q 'PNG' "$T/body"; then ok "izravan pristup slici: $p → ${s%% *}"; else fail "slika dostupna bez provjere: $p → $s"; fi
done

check "nova prijava bez CSRF tokena" "$(code -b "$ANA" -X POST -F cetvrt_id=1 -F vrsta_id=1 -F 'naslov=Bez tokena' -F 'opis=Prijava bez CSRF tokena' -F "slika=@$TW/ok.png" "$B/prijave")" "403"
broj_prijava_slika() { ls "$KORIJEN/storage/uploads/prijave" | wc -l; }
{ cat "$T/ok.png"; head -c 6000000 /dev/zero; } > "$T/vrlo_velika.png"
prijava_s() {  # prijava_s DATOTEKA → status
    local t; t=$(tok "$ANA" /prijave/nova)
    code -b "$ANA" -X POST -F "_csrf=$t" -F cetvrt_id=1 -F vrsta_id=1 -F 'naslov=Test uploada' -F 'opis=Testiranje uploada uz prijavu' -F "slika=@$1" "$B/prijave"
}
prije=$(broj_prijava_slika)
check "prijava: web shell shell.php" "$(prijava_s "$TW/shell.php")" "422"
check "prijava: shell.jpg s lažnim Content-Type" "$(prijava_s "$TW/shell.jpg;type=image/jpeg")" "422"
check "prijava: SVG sa <script>" "$(prijava_s "$TW/zlo.svg;type=image/svg+xml")" "422"
check "prijava: slika veća od 5 MB" "$(prijava_s "$TW/vrlo_velika.png;type=image/png")" "422"
eq    "  → nijedna odbijena datoteka nije spremljena" "$(broj_prijava_slika)" "$prije"

t=$(tok "$ANA" /prijave/nova)
code -b "$ANA" -X POST -F "_csrf=$t" -F cetvrt_id=1 -F vrsta_id=1 --form-string 'naslov=<img src=x onerror=alert(1)>' \
    --form-string 'opis=<script>alert(document.cookie)</script> opis' --form-string 'adresa="><svg onload=alert(1)>' -F "slika=@$TW/ok.png" "$B/prijave" >/dev/null
PID=$(echo "SELECT MAX(id) FROM prijave" | SQL)
eq    "prijava sa zlonamjernim naslovom/opisom/adresom je spremljena" "$(echo "SELECT naslov FROM prijave WHERE id=$PID" | SQL)" '<img src=x onerror=alert(1)>'
code -b "$ANA" "$B/prijave/$PID" >/dev/null
hasnt "pohranjeni XSS u naslovu prijave" '<img src=x'
hasnt "pohranjeni XSS u opisu prijave" '<script>alert(document.cookie)'
hasnt "pohranjeni XSS u adresi prijave" '"><svg'
code -b "$ADMIN" "$B/prijave/$PID" >/dev/null
hasnt "  → ni kod administratora (koji vidi više podataka)" '<img src=x'
code -b "$ANA" -G --data-urlencode "status=PRIJAVLJENO' OR '1'='1" "$B/prijave/moje" >/dev/null
eq    "SQLi u filtru statusa → filtar ignoriran (6 Aninih prijava)" "$(grep -c 'list-group-item-action' "$T/body")" "6"

# ---------------------------------------------------------------------
naslov "11. Tok rada: kanban, dodjela, odobrenje (A01)"
# Status smije mijenjati samo propisana uloga i samo dodijeljeni radnik.
# ---------------------------------------------------------------------
st() { echo "SELECT status FROM prijave WHERE id=$1" | SQL; }
IVAN=$(prijava radnik.ivan@zagreb-prijave.test)
MARKO=$(prijava radnik.marko@zagreb-prijave.test)
LEA=$(prijava urednik.lea@zagreb-prijave.test)
tM=$(tok "$MARKO" /radnik/zadaci); tI=$(tok "$IVAN" /radnik/zadaci)
code -b "$MARKO" -X POST --data-urlencode "_csrf=$tM" "$B/radnik/zadaci/3/zapocni" >/dev/null
eq    "radnik Marko započinje Ivanov zadatak 3" "$(st 3)" "DODIJELJENO"
check "Marko → Ivanov zadatak preko JSON-a" "$(code -b "$MARKO" -X POST -H 'Accept: application/json' -H "X-CSRF-Token: $tM" "$B/radnik/zadaci/3/zapocni")" "409"
hasnt "  → odgovor ne otkriva status tuđeg zadatka" 'Dodijeljeno'
check "korisnica Ana → radnička ruta" "$(code -b "$ANA" -X POST --data-urlencode "_csrf=$(tok "$ANA" /prijave/moje)" "$B/radnik/zadaci/3/zapocni")" "403"
check "urednica Lea → dodjela prijave" "$(code -b "$LEA" -X POST --data-urlencode "_csrf=$(tok "$LEA" /admin/vijesti)" -d radnik_id=4 "$B/admin/prijave/1/dodjela")" "403"
check "radnik Marko → sam odobrava svoj posao (6)" "$(code -b "$MARKO" -X POST --data-urlencode "_csrf=$tM" "$B/admin/prijave/6/odobri")" "403"
eq    "  → prijava 6 i dalje čeka provjeru" "$(st 6)" "CEKA_PROVJERU"
check "admin: vraćanje s krivim CSRF tokenom" "$(code -b "$ADMIN" -X POST -d '_csrf=lazni&komentar=Pokusaj' "$B/admin/prijave/6/vrati")" "403"
tA=$(tok "$ADMIN" /admin/prijave)
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tA" --data-urlencode 'radnik_id=1 OR 1=1' "$B/admin/prijave/2/dodjela" >/dev/null
eq    "dodjela s radnik_id='1 OR 1=1' (admin nije radnik)" "$(st 2)" "PRIJAVLJENO"

code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" "$B/radnik/zadaci/3/zapocni" >/dev/null
prije=$(ls "$KORIJEN/storage/uploads/prijave" | wc -l)
code -b "$IVAN" -X POST -F "_csrf=$tI" -F 'napomena=Pokušaj s web shellom.' -F "slika=@$TW/shell.jpg;type=image/jpeg" "$B/radnik/zadaci/3/zavrsi" >/dev/null
eq    "slika 'poslije': web shell odbijen, status ostaje" "$(st 3)/$(ls "$KORIJEN/storage/uploads/prijave" | wc -l)" "U_RADU/$prije"
code -b "$IVAN" -X POST -F "_csrf=$tI" --form-string 'napomena=<script>alert("napomena")</script> gotovo' \
    -F tip=prije -F ucitao_id=1 -F status=ZAVRSENO -F "slika=@$TW/ok.png" "$B/radnik/zadaci/3/zavrsi" >/dev/null
eq    "mass assignment: tip/ucitao_id/status iz obrasca ignorirani" "$(st 3)/$(echo "SELECT CONCAT(tip,'/',ucitao_id) FROM prijava_slike WHERE prijava_id=3 ORDER BY id DESC LIMIT 1" | SQL)" "CEKA_PROVJERU/poslije/4"
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tA" --data-urlencode 'komentar=<img src=x onerror=alert(1)> ponovi' "$B/admin/prijave/3/vrati" >/dev/null
code -b "$IVAN" "$B/prijave/3" >/dev/null
hasnt "XSS u napomeni radnika escapean" '<script>alert("napomena")'
hasnt "XSS u razlogu vraćanja escapean" '<img src=x'
has   "  → razlog je ipak prikazan (kao tekst)" '&lt;img src=x'

# ---------------------------------------------------------------------
naslov "12. REST API (/api/v1)"
# Isti napadi kao na web, ali preko JSON API-ja, plus napadi specifični za API.
# ---------------------------------------------------------------------
A="$B/api/v1"
code -G --data-urlencode "q=%' UNION SELECT id,email,lozinka_hash,4,5,6,7,8,9,10,11 FROM korisnici -- " "$A/vijesti" >/dev/null
hasnt "SQLi (UNION) u ?q= ne vraća hasheve" '\$2y\$'
eq    "  → 0 rezultata" "$(jv meta.ukupno)" "0"
code "$A/vijesti?kategorija=1%20OR%201=1&po_stranici=50" >/dev/null
hasnt "SQLi u ?kategorija= ne otkriva nacrte" 'NACRT'
code "$A/prijave?status=ZAVRSENO'%20OR%20'1'='1" >/dev/null
eq    "SQLi u ?status= prijava → filtar ignoriran" "$(jv meta.ukupno)" "$(echo 'SELECT COUNT(*) FROM prijave' | SQL)"
code "$A/prijave?po_stranici=100" >/dev/null
hasnt "javni popis prijava bez e-mailova i hasheva" '@zagreb-prijave.test\|\$2y\$'
hasnt "  → bez opisa i imena prijavitelja" '"opis"\|prijavitelj'
check "detalj prijave bez prijave → 401 (ne preusmjeravanje)" "$(code "$A/prijave/1")" "401"
header_hasnt "API ne dopušta čitanje s drugih domena (nema CORS zaglavlja)" 'Access-Control-Allow-Origin'

LEA_API=$(api_prijava urednik.lea@zagreb-prijave.test); tLA=$(cat "$LEA_API.csrf")
check "PATCH bez CSRF tokena (cookie je ipak poslan)" "$(code -b "$LEA_API" -H 'Content-Type: application/json' -X PATCH --data '{"naslov":"Hakirano"}' "$A/vijesti/2")" "403"
check "DELETE s krivim CSRF tokenom" "$(code -b "$LEA_API" -H 'X-CSRF-Token: lazni' -X DELETE "$A/vijesti/2")" "403"
check "CSRF preko HTML obrasca (enctype text/plain, JSON u tijelu)" "$(code -b "$LEA_API" -H 'Content-Type: text/plain' --data '{"naslov":"x","sazetak":"Lazna vijest s druge stranice.","sadrzaj":"x","kategorija_id":1}' "$A/vijesti")" "403"
check "  → i s ispravnim tokenom: tijelo mora biti application/json" "$(code -b "$LEA_API" -H 'Content-Type: text/plain' -H "X-CSRF-Token: $tLA" --data '{"naslov":"x"}' "$A/vijesti")" "400"
check "IDOR: Lea mijenja Tinovu vijest 4" "$(code -b "$LEA_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tLA" -X PATCH --data '{"naslov":"Preuzeto"}' "$A/vijesti/4")" "403"
check "mass assignment: autor_id u POST-u" "$(code -b "$LEA_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tLA" --data '{"naslov":"Test vijest","sazetak":"Dovoljno dug sazetak.","sadrzaj":"<p>x</p>","kategorija_id":1,"autor_id":1}' "$A/vijesti")" "400"
check "pohranjeni XSS preko API-ja" "$(code -b "$LEA_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tLA" --data '{"naslov":"XSS kroz API","sazetak":"Pokusaj XSS-a kroz API.","sadrzaj":"<p onclick=\"x()\">a</p><img src=x onerror=alert(1)><a href=\"javascript:alert(1)\">b</a>","kategorija_id":1,"status":"objavljeno"}' "$A/vijesti")" "201"
hasnt "  → onclick, onerror, javascript: uklonjeni" 'onclick\|onerror\|javascript:'
DUBOKO=$(printf '%.0s[' {1..40}; printf '%.0s]' {1..40})
check "JSON s 40 razina ugniježđenja" "$(code -b "$LEA_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tLA" --data "{\"naslov\":$DUBOKO}" "$A/vijesti")" "400"
check "JSON niz umjesto objekta" "$(code -b "$LEA_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tLA" --data '[1,2,3]' "$A/vijesti")" "400"
check "nepodržana metoda PUT" "$(code -X PUT "$A/prijave/1")" "405"
code "$A/vijesti/999999" >/dev/null
hasnt "pogreška API-ja bez putanja i SQL-a" 'xampp\|SQLSTATE'
IVAN_API=$(api_prijava radnik.ivan@zagreb-prijave.test); tIA=$(cat "$IVAN_API.csrf")
check "radnik preko API-ja sam zatvara prijavu" "$(code -b "$IVAN_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tIA" -X PATCH --data '{"status":"ZAVRSENO"}' "$A/prijave/4")" "403"
check "radnik preko API-ja mijenja tuđi zadatak" "$(code -b "$IVAN_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tIA" -X PATCH --data '{"status":"U_RADU"}' "$A/prijave/5")" "403"
check "radnik preko API-ja dodjeljuje zadatak sebi" "$(code -b "$IVAN_API" -H 'Content-Type: application/json' -H "X-CSRF-Token: $tIA" -X PATCH --data '{"radnik_id":4}' "$A/prijave/2")" "403"

# ---------------------------------------------------------------------
naslov "13. Karta, radovi i promet (TomTom proxy)"
# ---------------------------------------------------------------------
ADMIN=$(prijava admin@zagreb-prijave.test); tAd=$(tok "$ADMIN" /admin/radovi)
LEA=$(prijava urednik.lea@zagreb-prijave.test)
IVAN=$(prijava radnik.ivan@zagreb-prijave.test)
for J in "$LEA" "$IVAN" "$ANA"; do
    t=$(tok "$J" /prijave/nova)
    s1=$(code -b "$J" -X POST --data-urlencode "_csrf=$t" -d 'naslov=Hakirani radovi' "$B/admin/radovi")
    s2=$(code -b "$J" -X POST --data-urlencode "_csrf=$t" "$B/admin/radovi/1/obrisi")
    if [[ "$s1 $s2" == "403  403 " ]]; then ok "neadmin ne može unositi ni brisati radove (403/403)"; else fail "neadmin radovi: $s1 / $s2"; fi
done
eq    "  → radovi netaknuti" "$(echo 'SELECT COUNT(*) FROM radovi' | SQL)" "5"
code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tAd" --data-urlencode 'naslov=<script>alert("radovi")</script>' -d 'vrsta=cesta' -d 'utjecaj_na_promet=nema' \
    -d 'lat=45.81' -d 'lng=15.97' -d 'pocetak=2026-11-01T08:00' --data-urlencode 'adresa="><img src=x onerror=alert(1)>' "$B/admin/radovi" >/dev/null
code -b "$ADMIN" "$B/admin/radovi" >/dev/null
hasnt "pohranjeni XSS u nazivu radova escapean (popis)" '<script>alert("radovi")'
hasnt "  → i u adresi" '<img src=x'
check "lokacija radova '45.8; DROP TABLE radovi'" "$(code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tAd" -d 'naslov=Test radovi' -d 'vrsta=cesta' -d 'utjecaj_na_promet=nema' --data-urlencode 'lat=45.8; DROP TABLE radovi' -d 'lng=15.97' -d 'pocetak=2026-11-01T08:00' "$B/admin/radovi")" "422"
eq    "  → tablica postoji" "$(echo 'SELECT COUNT(*) > 0 FROM radovi' | SQL)" "1"
for sim in "" testni nedostupan neispravno; do
    code "$B/api/v1/promet${sim:+?simulacija=$sim}" >/dev/null
    if ! grep -q 'key=\|api\.tomtom\.com\|TOMTOM_API_KEY=' "$T/body"; then ok "promet${sim:+ ($sim)}: odgovor ne otkriva ključ ni adresu TomToma"; else fail "promet ($sim) otkriva ključ/URL"; fi
done
check "promet: ?simulacija=../../.env (nepoznata vrijednost)" "$(code "$B/api/v1/promet?simulacija=../../.env")" "200"
hasnt "  → bez sadržaja .env" 'DB_PASS'
check "promet: klijent ne može zadati bbox/URL (SSRF)" "$(code "$B/api/v1/promet?bbox=0,0,90,90&url=http://127.0.0.1/")" "200"
hasnt "  → nema traga podmetnutog URL-a" '127.0.0.1'
check "cache prometa nije dostupan izvana" "$(code --path-as-is "$B/storage/cache/promet.json")" "404"

# ---------------------------------------------------------------------
naslov "14. Iza HTTPS tunela (cloudflared na istom računalu)"
# Testovi idu s localhosta, pa simuliraju zaglavlja koja dodaje cloudflared.
# ---------------------------------------------------------------------
code -H 'X-Forwarded-Proto: https' "$B/racun/prijava" >/dev/null
header_has "kolačić sesije dobiva Secure kad je veza do preglednika HTTPS" 'Set-Cookie: ZGPRIJAVE=.*secure'
code "$B/racun/prijava" >/dev/null
header_hasnt "  → bez tunela (http://localhost) nema Secure" 'Set-Cookie: ZGPRIJAVE=.*secure'
J="$T/tunel.jar"; t=$(tok "$J" /racun/prijava)
code -b "$J" -c "$J" -H 'CF-Connecting-IP: 203.0.113.7' -X POST --data-urlencode "_csrf=$t" -d 'email=tunel@zagreb-prijave.test&lozinka=kriva123' "$B/racun/prijava" >/dev/null
eq    "neuspjela prijava bilježi stvarnu IP klijenta iza tunela" "$(echo "SELECT ip FROM pokusaji_prijave WHERE email='tunel@zagreb-prijave.test' ORDER BY id DESC LIMIT 1" | SQL)" "203.0.113.7"
code -b "$J" -c "$J" -H 'CF-Connecting-IP: <script>' -X POST --data-urlencode "_csrf=$t" -d 'email=tunel@zagreb-prijave.test&lozinka=kriva123' "$B/racun/prijava" >/dev/null
IP=$(echo "SELECT ip FROM pokusaji_prijave WHERE email='tunel@zagreb-prijave.test' ORDER BY id DESC LIMIT 1" | SQL)
if [[ "$IP" == "127.0.0.1" || "$IP" == "::1" ]]; then ok "  → neispravna vrijednost zaglavlja se ignorira ($IP)"; else fail "  → upisano: $IP"; fi

# ---------------------------------------------------------------------
naslov "15. Pogađanje lozinke – brute force (A07)"
# Na kraju, jer blokada po IP-u utječe na sve daljnje prijave s ovog računala.
# ---------------------------------------------------------------------
echo "DELETE FROM pokusaji_prijave" | SQL
J="$T/bf.jar"; t=$(tok "$J" /racun/prijava)
for i in 1 2 3 4 5; do
    code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d "email=ana@zagreb-prijave.test&lozinka=pogadjam$i" "$B/racun/prijava" >/dev/null
done
check "6. pokušaj, čak i s ISPRAVNOM lozinkom → blokirano" \
    "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" --data-urlencode email=ana@zagreb-prijave.test --data-urlencode "lozinka=$LOZ" "$B/racun/prijava")" "429"
header_has "  → zaglavlje Retry-After" 'Retry-After: 900'
for i in 1 2 3 4 5; do
    code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d "email=ne.postoji@zagreb-prijave.test&lozinka=x$i" "$B/racun/prijava" >/dev/null
done
check "blokada i za nepostojeći e-mail (ne otkriva postoji li račun)" \
    "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d 'email=ne.postoji@zagreb-prijave.test&lozinka=x' "$B/racun/prijava")" "429"
for i in $(seq 1 10); do
    code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" -d "email=raspr$i@zagreb-prijave.test&lozinka=Zagreb2026" "$B/racun/prijava" >/dev/null
done
check "password spraying: 20 pokušaja s iste IP adrese → blokirano i za druge račune" \
    "$(code -b "$J" -c "$J" -X POST --data-urlencode "_csrf=$t" --data-urlencode email=luka@zagreb-prijave.test --data-urlencode "lozinka=$LOZ" "$B/racun/prijava")" "429"

# ---------------------------------------------------------------------
naslov "Završna provjera"
# ---------------------------------------------------------------------
LOG_POSLIJE=$(cat "$LOG" 2>/dev/null | wc -l)
if (( LOG_POSLIJE > LOG_PRIJE )); then
    fail "napadi su izazvali pogreške 500 (storage/logs/app.log):"; tail -n $((LOG_POSLIJE - LOG_PRIJE)) "$LOG"
else ok "nijedan napad nije izazvao pogrešku 500"; fi

# Čišćenje: slike nastale testom i početni podaci u bazi
for f in $(echo "SELECT slika FROM vijesti WHERE slika IS NOT NULL AND id > 9" | SQL); do rm -f "$KORIJEN/public/uploads/vijesti/$f"; done
reset_baze
sazetak
