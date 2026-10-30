#!/usr/bin/env bash
# =====================================================================
# Funkcionalni testovi faze 5 – prijave problema.
# Pokretanje: bash tests/prijave.sh   (VRAĆA BAZU NA POČETNE PODATKE!)
# =====================================================================
source "$(dirname "$0")/lib.sh"

reset_baze || { echo "Baza nije dostupna – je li MySQL pokrenut?"; exit 1; }
SPREMISTE="$KORIJEN/storage/uploads/prijave"
broj_datoteka() { ls "$SPREMISTE" | wc -l; }
napravi_png "$T/foto.png"
slika_id() { echo "SELECT id FROM prijava_slike WHERE prijava_id=$1 AND tip='$2'" | SQL; }

ANA=$(prijava ana@zagreb-prijave.test)
ADMIN=$(prijava admin@zagreb-prijave.test)
IVAN=$(prijava radnik.ivan@zagreb-prijave.test)

naslov "Pristup"
check "gost → /prijave/nova" "$(code "$B/prijave/nova")" "303 $B/racun/prijava"
check "gost → /prijave/moje" "$(code "$B/prijave/moje")" "303 $B/racun/prijava"
check "gost → /prijave/1" "$(code "$B/prijave/1")" "303 $B/racun/prijava"

naslov "Moje prijave"
check "Ana → /prijave/moje" "$(code -b "$ANA" "$B/prijave/moje")" "200"
eq    "  → 5 vlastitih prijava (1, 3, 5, 7, 9)" "$(grep -c 'list-group-item-action' "$T/body")" "5"
hasnt "  → nema Lukinih prijava" 'Divlje odlagalište'
has   "  → sličica preko PHP-a, ne iz public/" "/prijave/1/slike/$(slika_id 1 prije)"
code -b "$ANA" "$B/prijave/moje?status=ZAVRSENO" >/dev/null
eq    "filtar status=ZAVRSENO" "$(grep -c 'list-group-item-action' "$T/body")" "1"
code -b "$ANA" "$B/prijave/moje?status=NEPOSTOJI" >/dev/null
eq    "nepoznat status → filtar ignoriran" "$(grep -c 'list-group-item-action' "$T/body")" "5"
code -b "$ANA" "$B/prijave/moje?status=DODIJELJENO" >/dev/null; eq "filtar DODIJELJENO" "$(grep -c 'list-group-item-action' "$T/body")" "1"
LUKA=$(prijava luka@zagreb-prijave.test)
code -b "$(prijava radnik.marko@zagreb-prijave.test)" "$B/prijave/moje" >/dev/null
has   "korisnik bez prijava → poruka" 'Još niste poslali nijednu prijavu'

naslov "Detalj prijave"
check "Ana → /prijave/1" "$(code -b "$ANA" "$B/prijave/1")" "200"
has   "  → naslov" 'Rupa na kolniku'
has   "  → četvrt" 'Donji grad'
has   "  → hitnost" '>Srednja<'
has   "  → tijek: Vi" '>Vi'
check "  → slika 'prije'" "$(code -b "$ANA" "$B/prijave/1/slike/$(slika_id 1 prije)")" "200"
header_has "  → Content-Type: image/png" 'Content-Type: image/png'
header_has "  → Cache-Control: private" 'Cache-Control: private'
check "Ana → /prijave/7 (VRAĆENO)" "$(code -b "$ANA" "$B/prijave/7")" "200"
hasnt "  → građanin NE vidi interni komentar službe" 'Na slici se ne vidi'
hasnt "  → građanin NE vidi imena zaposlenika" 'Ivan'
hasnt "  → građanin NE vidi neodobrenu sliku 'poslije'" "/slike/$(slika_id 7 poslije)"
check "  → ni izravnim URL-om" "$(code -b "$ANA" "$B/prijave/7/slike/$(slika_id 7 poslije)")" "404"
code -b "$ANA" "$B/prijave/9" >/dev/null
has   "Ana → /prijave/9 (ZAVRŠENO) vidi sliku 'poslije'" "/slike/$(slika_id 9 poslije)"
check "admin → /prijave/7" "$(code -b "$ADMIN" "$B/prijave/7")" "200"
has   "  → admin vidi komentar" 'Na slici se ne vidi'
has   "  → admin vidi prijavitelja" 'Prijavitelj'
has   "  → admin vidi sliku 'poslije'" "/slike/$(slika_id 7 poslije)"
check "radnik Ivan → svoja prijava 7" "$(code -b "$IVAN" "$B/prijave/7")" "200"
check "radnik Ivan → Markova prijava 6" "$(code -b "$IVAN" "$B/prijave/6")" "403"
check "nepostojeća prijava 999" "$(code -b "$ANA" "$B/prijave/999")" "404"
check "nepostojeća slika" "$(code -b "$ANA" "$B/prijave/1/slike/99999")" "404"

naslov "Nova prijava: obrazac i validacija"
check "obrazac" "$(code -b "$ANA" "$B/prijave/nova")" "200"
eq    "  → 17 četvrti + 9 vrsta u izbornicima" "$(grep -c '<option value="[0-9]' "$T/body")" "26"
has   "  → multipart" 'enctype="multipart/form-data"'
t=$(tok "$ANA" /prijave/nova)
prije=$(echo "SELECT COUNT(*) FROM prijave" | SQL)
check "prazan obrazac" "$(code -b "$ANA" -X POST --data-urlencode "_csrf=$t" "$B/prijave")" "422"
has   "  → fotografija obavezna" 'Fotografija problema je obavezna'
has   "  → četvrt obavezna" 'Odaberite gradsku četvrt'
has   "  → opis obavezan" 'Opis mora imati'
check "bez slike, ostalo ispravno" "$(code -b "$ANA" -X POST -F "_csrf=$t" -F cetvrt_id=3 -F vrsta_id=2 -F 'naslov=Ne radi lampa' -F 'opis=Lampa ne svijetli već tjedan dana.' "$B/prijave")" "422"
check "nepostojeća četvrt 99" "$(code -b "$ANA" -X POST -F "_csrf=$t" -F cetvrt_id=99 -F vrsta_id=2 -F 'naslov=Ne radi lampa' -F 'opis=Lampa ne svijetli već tjedan dana.' -F "slika=@$TW/foto.png" "$B/prijave")" "422"
has   "  → upisani naslov ostaje" 'value="Ne radi lampa"'
has   "  → traži ponovni odabir slike" 'ponovno odaberite fotografiju'
eq    "  → ništa nije spremljeno" "$(echo "SELECT COUNT(*) FROM prijave" | SQL)" "$prije"

naslov "Nova prijava: uspjeh (transakcija u 3 tablice)"
datoteke=$(broj_datoteka)
r=$(code -b "$ANA" -X POST -F "_csrf=$t" -F cetvrt_id=3 -F vrsta_id=2 -F 'naslov=Ne radi lampa' \
    -F $'opis=Lampa ne svijetli.\nDrugi redak <script>alert(1)</script>' -F 'adresa=Vukovarska 1' \
    -F hitnost=kriticna -F status=ZAVRSENO -F korisnik_id=1 -F "slika=@$TW/foto.png" "$B/prijave")
ID=$(echo "SELECT MAX(id) FROM prijave" | SQL)
check "poslano" "$r" "303 $B/prijave/$ID"
eq    "  → status/hitnost/vlasnik postavlja sustav (podmetnuto ignorirano)" "$(echo "SELECT CONCAT(status,'/',hitnost,'/',korisnik_id) FROM prijave WHERE id=$ID" | SQL)" "PRIJAVLJENO/srednja/6"
eq    "  → datum i vrijeme sada (± 1 min)" "$(echo "SELECT ABS(TIMESTAMPDIFF(SECOND, kreirano_u, NOW())) < 60 FROM prijave WHERE id=$ID" | SQL)" "1"
eq    "  → zapis slike 'prije'" "$(echo "SELECT CONCAT(tip,'/',mime_tip,'/',ucitao_id) FROM prijava_slike WHERE prijava_id=$ID" | SQL)" "prije/image/png/6"
eq    "  → prvi zapis povijesti" "$(echo "SELECT CONCAT(IFNULL(iz_statusa,'NULL'),'→',u_status,'/',korisnik_id) FROM povijest_statusa WHERE prijava_id=$ID" | SQL)" "NULL→PRIJAVLJENO/6"
DAT=$(echo "SELECT datoteka FROM prijava_slike WHERE prijava_id=$ID" | SQL)
if [[ -f "$SPREMISTE/$DAT" && "$DAT" =~ ^[a-f0-9]{32}\.png$ ]]; then ok "  → slika izvan webroota, nasumično ime ($DAT)"; else fail "  → slika nije spremljena ($DAT)"; fi
eq    "  → jedna nova datoteka" "$(broj_datoteka)" "$((datoteke + 1))"
code -b "$ANA" "$B/prijave/$ID" >/dev/null
has   "  → opis: prijelom retka kao <br>" 'Lampa ne svijetli.<br />'
hasnt "  → opis: <script> escapean" '<script>alert(1)'
code -b "$ANA" "$B/racun/profil" >/dev/null; has "  → profil: 6 prijava" 'Poslanih prijava: 6'

naslov "Transakcija: pad trećeg upita poništava sve"
# Okidač u bazi namjerno ruši INSERT u povijest_statusa (3. upit u transakciji)
echo "CREATE TRIGGER test_pad BEFORE INSERT ON povijest_statusa FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test'" | SQL
LOG="$KORIJEN/storage/logs/app.log"; LOG_PRIJE=$(cat "$LOG" 2>/dev/null | wc -l)
prije=$(echo "SELECT COUNT(*) FROM prijave" | SQL); slike_prije=$(echo "SELECT COUNT(*) FROM prijava_slike" | SQL); datoteke=$(broj_datoteka)
check "slanje kad povijest ne može nastati" "$(code -b "$ANA" -X POST -F "_csrf=$t" -F cetvrt_id=3 -F vrsta_id=2 -F 'naslov=Test transakcije' -F 'opis=Ova prijava ne smije ostati u bazi.' -F "slika=@$TW/foto.png" "$B/prijave")" "500"
if grep -q 'SQLSTATE' "$T/body"; then
    echo "  INFO  APP_DEBUG=true: razvojna stranica greške prikazuje detalje (s APP_DEBUG=false korisnik vidi samo generičku poruku)"
else ok "  → korisnik ne vidi SQL ni okidač"; fi
echo "DROP TRIGGER test_pad" | SQL
eq    "  → prijava NIJE ostala u bazi (ROLLBACK)" "$(echo "SELECT COUNT(*) FROM prijave" | SQL)" "$prije"
eq    "  → ni zapis slike" "$(echo "SELECT COUNT(*) FROM prijava_slike" | SQL)" "$slike_prije"
eq    "  → ni datoteka na disku" "$(broj_datoteka)" "$datoteke"
# Namjerna pogreška 500 zapisana je u log – uklanjamo je da ne zbuni sigurnosne testove
[[ -f "$LOG" ]] && sed -i "$((LOG_PRIJE + 1)),\$d" "$LOG"

naslov "Zaštita od zatrpavanja prijavama"
echo "INSERT INTO prijave (korisnik_id, cetvrt_id, vrsta_id, naslov, opis) SELECT 7, 1, 1, 'Spam', 'Spam prijava' FROM vrste_problema LIMIT 9" | SQL
tL=$(tok "$LUKA" /prijave/nova)
check "Luka: 10. prijava u sat vremena još prolazi" "$(code -b "$LUKA" -X POST -F "_csrf=$tL" -F cetvrt_id=1 -F vrsta_id=1 -F 'naslov=Deseta prijava' -F 'opis=Deseta prijava u satu.' -F "slika=@$TW/foto.png" "$B/prijave")" "303"
check "Luka: 11. prijava → 429" "$(code -b "$LUKA" -X POST -F "_csrf=$tL" -F cetvrt_id=1 -F vrsta_id=1 -F 'naslov=Jedanaesta' -F 'opis=Jedanaesta prijava u satu.' -F "slika=@$TW/foto.png" "$B/prijave")" "429"
has   "  → poruka" 'previše prijava'

reset_baze
sazetak
