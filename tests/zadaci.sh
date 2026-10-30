#!/usr/bin/env bash
# =====================================================================
# Funkcionalni testovi faze 6 – dodjela, hitnost, kanban, state machine,
# provjera završetka. Pokretanje: bash tests/zadaci.sh  (VRAĆA BAZU!)
#
# Početno stanje (seed): Ivan (4) ima prijave 3 DODIJELJENO, 4 U_RADU, 7 VRACENO,
# 9 ZAVRSENO; Marko (5) ima 5 U_RADU, 6 CEKA_PROVJERU, 8 ZAVRSENO.
# Nedodijeljene (PRIJAVLJENO): 1, 2, 10.
# =====================================================================
source "$(dirname "$0")/lib.sh"

reset_baze || { echo "Baza nije dostupna – je li MySQL pokrenut?"; exit 1; }
napravi_png "$T/nakon.png"
# Hrvatski znakovi idu iz datoteke: Git Bash pri pozivu Windows curl.exe
# argumente pretvara u ANSI kodnu stranicu, pa bi č/š/ž stigli kao "?"
printf 'Znak STOP ponovno postavljen\ni učvršćen.' > "$T/napomena.txt"
printf 'Rupa nije potpuno sanirana, rub je i dalje oštar.' > "$T/komentar.txt"
st()  { echo "SELECT status FROM prijave WHERE id=$1" | SQL; }
zadnja_povijest() { echo "SELECT CONCAT(IFNULL(iz_statusa,'NULL'),'→',u_status,'/',korisnik_id,'/',IFNULL(komentar,'')) FROM povijest_statusa WHERE prijava_id=$1 ORDER BY id DESC LIMIT 1" | SQL; }

ADMIN=$(prijava admin@zagreb-prijave.test)
IVAN=$(prijava radnik.ivan@zagreb-prijave.test)
MARKO=$(prijava radnik.marko@zagreb-prijave.test)
ANA=$(prijava ana@zagreb-prijave.test)
LUKA=$(prijava luka@zagreb-prijave.test)
tA=$(tok "$ADMIN" /admin/prijave); tI=$(tok "$IVAN" /radnik/zadaci); tM=$(tok "$MARKO" /radnik/zadaci)
admin_post() { code -b "$ADMIN" -X POST --data-urlencode "_csrf=$tA" "${@:2}" "$B/admin/prijave/$1"; }

naslov "Pristup"
check "gost → /radnik/zadaci" "$(code "$B/radnik/zadaci")" "303 $B/racun/prijava"
check "korisnik → /radnik/zadaci" "$(code -b "$ANA" "$B/radnik/zadaci")" "403"
check "admin → /radnik/zadaci (nije radnik)" "$(code -b "$ADMIN" "$B/radnik/zadaci")" "403"
check "radnik → /admin/prijave" "$(code -b "$IVAN" "$B/admin/prijave")" "403"
check "korisnik → /admin/prijave" "$(code -b "$ANA" "$B/admin/prijave")" "403"

naslov "Kanban radnika"
check "Ivan → kanban" "$(code -b "$IVAN" "$B/radnik/zadaci")" "200"
eq    "  → 3 otvorena zadatka (3, 4, 7; završeni stariji od 14 dana skriveni)" "$(grep -c 'class="card kanban-kartica' "$T/body")" "3"
has   "  → zadatak 4 (kritično)" 'Srušen prometni znak STOP'
hasnt "  → nema Markovih zadataka" 'Polomljena klupa'
has   "  → SortableJS sa SRI" 'sortablejs@1.15.7/Sortable.min.js'
has   "  → stupac 'U radu' prima samo iz DODIJELJENO i VRACENO" 'data-status="U_RADU" data-prima="DODIJELJENO,VRACENO"'
has   "  → kartica ima i gumb (radi bez JS-a)" "/radnik/zadaci/3/zapocni"

naslov "Administrator: popis prijava"
check "admin → /admin/prijave" "$(code -b "$ADMIN" "$B/admin/prijave")" "200"
has   "  → 10 prijava" '10 prijava'
code -b "$ADMIN" "$B/admin/prijave?status=PRIJAVLJENO" >/dev/null;  has "filtar status=PRIJAVLJENO" '3 prijava'
code -b "$ADMIN" "$B/admin/prijave?hitnost=kriticna" >/dev/null;    has "filtar hitnost=kriticna" '1 prijava'
code -b "$ADMIN" "$B/admin/prijave?cetvrt=1" >/dev/null;            has "filtar četvrt=1" '1 prijava'
code -b "$ADMIN" "$B/admin/prijave?status=X&hitnost=Y&cetvrt=abc" >/dev/null; has "nepoznate vrijednosti filtra ignorirane" '10 prijava'
code -b "$ADMIN" "$B/admin/prijave" >/dev/null
first=$(grep -o 'href="[^"]*/prijave/[0-9]*"' "$T/body" | head -1 | grep -o '[0-9]*"$' | tr -d '"')
eq    "  → na vrhu je prijava koja čeka provjeru (6)" "$first" "6"
check "admin → /admin (pregled)" "$(code -b "$ADMIN" "$B/admin")" "200"
has   "  → kartica prijava" '3 novih · 1 čeka provjeru'

naslov "Dodjela i preraspodjela"
check "dodjela prijave 1 Ivanu" "$(admin_post 1/dodjela -d radnik_id=4)" "303 $B/prijave/1"
eq    "  → status" "$(st 1)" "DODIJELJENO"
eq    "  → zadatak (radnik/dodijelio)" "$(echo 'SELECT CONCAT(radnik_id,"/",dodijelio_id) FROM zadaci WHERE prijava_id=1' | SQL)" "4/1"
eq    "  → povijest" "$(zadnja_povijest 1)" "PRIJAVLJENO→DODIJELJENO/1/Dodijeljeno radniku Ivan Radnik."
check "Ivan sad vidi prijavu 1" "$(code -b "$IVAN" "$B/prijave/1")" "200"
admin_post 2/dodjela -d radnik_id=6 >/dev/null
eq    "dodjela korisnici (nije radnik) odbijena" "$(st 2)" "PRIJAVLJENO"
code -b "$ADMIN" "$B/prijave/2" >/dev/null; has "  → poruka" 'Odaberite aktivnog radnika'
admin_post 2/dodjela -d radnik_id=999 >/dev/null; eq "dodjela nepostojećem radniku odbijena" "$(st 2)" "PRIJAVLJENO"
admin_post 9/dodjela -d radnik_id=5 >/dev/null;   eq "dodjela ZAVRŠENE prijave odbijena" "$(echo 'SELECT CONCAT(status,"/",(SELECT radnik_id FROM zadaci WHERE prijava_id=9)) FROM prijave WHERE id=9' | SQL)" "ZAVRSENO/4"
check "preraspodjela prijave 1 Marku" "$(admin_post 1/dodjela -d radnik_id=5)" "303"
eq    "  → novi radnik, jedan zadatak po prijavi" "$(echo 'SELECT CONCAT(COUNT(*),"/",MAX(radnik_id)) FROM zadaci WHERE prijava_id=1' | SQL)" "1/5"
eq    "  → povijest" "$(zadnja_povijest 1)" "DODIJELJENO→DODIJELJENO/1/Preraspodijeljeno radniku Marko Radnik."
check "  → Ivan više ne vidi prijavu 1" "$(code -b "$IVAN" "$B/prijave/1")" "403"
admin_post 1/dodjela -d radnik_id=5 >/dev/null
code -b "$ADMIN" "$B/prijave/1" >/dev/null; has "ponovna dodjela istom radniku → poruka" 'već dodijeljena tom radniku'

naslov "Hitnost"
admin_post 2/hitnost -d hitnost=kriticna >/dev/null; eq "admin: prijava 2 → kritična" "$(echo 'SELECT hitnost FROM prijave WHERE id=2' | SQL)" "kriticna"
admin_post 2/hitnost -d hitnost=ekstremna >/dev/null; eq "nepostojeća hitnost odbijena" "$(echo 'SELECT hitnost FROM prijave WHERE id=2' | SQL)" "kriticna"
admin_post 9/hitnost -d hitnost=niska >/dev/null;     eq "hitnost završene prijave se ne mijenja" "$(echo 'SELECT hitnost FROM prijave WHERE id=9' | SQL)" "niska"
admin_post 8/hitnost -d hitnost=visoka >/dev/null;    eq "  → ni prijave 8" "$(echo 'SELECT hitnost FROM prijave WHERE id=8' | SQL)" "srednja"
check "radnik mijenja hitnost" "$(code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" -d hitnost=niska "$B/admin/prijave/3/hitnost")" "403"
check "korisnik mijenja hitnost" "$(code -b "$ANA" -X POST --data-urlencode "_csrf=$(tok "$ANA" /prijave/moje)" -d hitnost=kriticna "$B/admin/prijave/1/hitnost")" "403"

naslov "Radnik: početak rada"
check "Ivan započinje zadatak 3" "$(code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" "$B/radnik/zadaci/3/zapocni")" "303 $B/prijave/3"
eq    "  → status" "$(st 3)" "U_RADU"
eq    "  → povijest (tko = Ivan)" "$(zadnja_povijest 3)" "DODIJELJENO→U_RADU/4/"
code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" "$B/radnik/zadaci/1/zapocni" >/dev/null
eq    "Ivan → Markov zadatak 1 odbijeno" "$(st 1)" "DODIJELJENO"
code -b "$IVAN" "$B/prijave/3" >/dev/null   # flash s prethodne stranice
J=$(code -b "$IVAN" -X POST -H 'Accept: application/json' -H "X-CSRF-Token: $tI" "$B/radnik/zadaci/7/zapocni")
check "kanban (fetch, JSON): VRAĆENO 7 → U radu" "$J" "200"
header_has "  → Content-Type: application/json" 'Content-Type: application/json'
has   "  → {\"ok\":true}" '"ok":true'
eq    "  → status" "$(st 7)" "U_RADU"
check "kanban (JSON): ponovno isti prijelaz → 409" "$(code -b "$IVAN" -X POST -H 'Accept: application/json' -H "X-CSRF-Token: $tI" "$B/radnik/zadaci/7/zapocni")" "409"
has   "  → poruka o nedopuštenom prijelazu" 'nije dopušten'
check "kanban (JSON): tuđi zadatak → 409" "$(code -b "$IVAN" -X POST -H 'Accept: application/json' -H "X-CSRF-Token: $tI" "$B/radnik/zadaci/5/zapocni")" "409"
has   "  → poruka" 'nije dodijeljen vama'
check "kanban (JSON): bez CSRF tokena → 403" "$(code -b "$IVAN" -X POST -H 'Accept: application/json' "$B/radnik/zadaci/3/zapocni")" "403"

naslov "Radnik: slanje na provjeru (slika + napomena obavezne)"
code -b "$IVAN" -X POST -F "_csrf=$tI" -F 'napomena=Znak je ponovno postavljen.' "$B/radnik/zadaci/4/zavrsi" >/dev/null
eq    "bez slike → odbijeno" "$(st 4)" "U_RADU"
code -b "$IVAN" -X POST -F "_csrf=$tI" -F 'napomena=Gotovo' -F "slika=@$TW/nakon.png" "$B/radnik/zadaci/4/zavrsi" >/dev/null
eq    "prekratka napomena → odbijeno" "$(st 4)" "U_RADU"
eq    "  → slika se nije spremila" "$(echo "SELECT COUNT(*) FROM prijava_slike WHERE prijava_id=4 AND tip='poslije'" | SQL)" "0"
check "slika + napomena" "$(code -b "$IVAN" -X POST -F "_csrf=$tI" -F "napomena=<$TW/napomena.txt" -F "slika=@$TW/nakon.png" "$B/radnik/zadaci/4/zavrsi")" "303 $B/prijave/4"
eq    "  → status" "$(st 4)" "CEKA_PROVJERU"
eq    "  → slika 'poslije' (učitao Ivan)" "$(echo "SELECT CONCAT(COUNT(*),'/',MAX(ucitao_id)) FROM prijava_slike WHERE prijava_id=4 AND tip='poslije'" | SQL)" "1/4"
eq    "  → napomena u zadatku" "$(echo 'SELECT napomena_radnika FROM zadaci WHERE prijava_id=4' | SQL)" 'Znak STOP ponovno postavljen\ni učvršćen.'
eq    "  → povijest s napomenom" "$(echo 'SELECT CONCAT(iz_statusa,"→",u_status,"/",korisnik_id) FROM povijest_statusa WHERE prijava_id=4 ORDER BY id DESC LIMIT 1' | SQL)" "U_RADU→CEKA_PROVJERU/4"
check "radnik pokušava sam odobriti (ZAVRŠENO)" "$(code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" "$B/admin/prijave/4/odobri")" "403"
eq    "  → i dalje čeka provjeru" "$(st 4)" "CEKA_PROVJERU"
code -b "$LUKA" "$B/prijave/4" >/dev/null
eq    "građanin (Luka) prije odobrenja ne vidi sliku 'poslije'" "$(grep -c '<figure' "$T/body")" "1"

naslov "Administrator: odobrenje i vraćanje"
admin_post 3/odobri >/dev/null; eq "odobrenje prijave U RADU odbijeno" "$(st 3)" "U_RADU"
code -b "$ADMIN" "$B/prijave/3" >/dev/null; has "  → poruka" 'nije dopušten'
check "odobrenje prijave 4" "$(admin_post 4/odobri)" "303 $B/prijave/4"
eq    "  → ZAVRŠENO" "$(st 4)" "ZAVRSENO"
eq    "  → povijest (tko = admin)" "$(zadnja_povijest 4)" "CEKA_PROVJERU→ZAVRSENO/1/"
code -b "$LUKA" "$B/prijave/4" >/dev/null
eq    "  → građanin sad vidi i sliku 'poslije'" "$(grep -c '<figure' "$T/body")" "2"
code -b "$IVAN" "$B/radnik/zadaci" >/dev/null; has "  → u Ivanovu stupcu 'Završeno'" 'Srušen prometni znak STOP'

admin_post 6/vrati -d komentar= >/dev/null; eq "vraćanje bez razloga odbijeno" "$(st 6)" "CEKA_PROVJERU"
check "vraćanje prijave 6 s razlogom" "$(admin_post 6/vrati --data-urlencode "komentar@$TW/komentar.txt")" "303"
eq    "  → VRAĆENO, razlog u povijesti" "$(zadnja_povijest 6)" "CEKA_PROVJERU→VRACENO/1/Rupa nije potpuno sanirana, rub je i dalje oštar."
code -b "$MARKO" "$B/prijave/6" >/dev/null; has "  → radnik vidi razlog" 'Rupa nije potpuno sanirana'
code -b "$LUKA" "$B/prijave/6" >/dev/null;  hasnt "  → građanin ne vidi interni razlog" 'Rupa nije potpuno sanirana'

code -b "$MARKO" -X POST --data-urlencode "_csrf=$tM" "$B/radnik/zadaci/6/zapocni" >/dev/null
code -b "$MARKO" -X POST -F "_csrf=$tM" -F 'napomena=Rub poravnat i dodatno zbijen.' -F "slika=@$TW/nakon.png" "$B/radnik/zadaci/6/zavrsi" >/dev/null
admin_post 6/odobri >/dev/null
eq    "dorada: VRAĆENO → U RADU → ČEKA PROVJERU → ZAVRŠENO" "$(echo "SELECT GROUP_CONCAT(u_status ORDER BY id SEPARATOR ',') FROM povijest_statusa WHERE prijava_id=6 AND id > 30" | SQL)" "VRACENO,U_RADU,CEKA_PROVJERU,ZAVRSENO"
code -b "$ADMIN" "$B/prijave/6" >/dev/null; eq "  → admin vidi obje slike 'poslije' (+ 'prije')" "$(grep -c '<figure' "$T/body")" "3"
code -b "$LUKA" "$B/prijave/6" >/dev/null;  eq "  → građanin vidi samo zadnju (odobrenu)" "$(grep -c '<figure' "$T/body")" "2"

naslov "State machine: svaka radnja iz svakog statusa"
# Prijava 10 dodijeljena Ivanu izravno u bazi; status se postavlja prije svake radnje
echo "INSERT INTO zadaci (prijava_id, radnik_id, dodijelio_id) VALUES (10, 4, 1)" | SQL
provjeri() {  # provjeri OPIS NAREDBA DOPUŠTENI_STATUSI CILJNI_STATUS
    local rezultat="" s
    for s in PRIJAVLJENO DODIJELJENO U_RADU CEKA_PROVJERU VRACENO ZAVRSENO; do
        echo "UPDATE prijave SET status='$s' WHERE id=10" | SQL
        eval "$2" >/dev/null
        if [[ "$(st 10)" == "$4" && "$s" != "$4" ]]; then rezultat+="$s "; fi
    done
    eq "$1 dopušteno samo iz" "${rezultat% }" "$3"
}
provjeri "radnik: započni" 'code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" "$B/radnik/zadaci/10/zapocni"' "DODIJELJENO VRACENO" "U_RADU"
provjeri "radnik: na provjeru" 'code -b "$IVAN" -X POST -F "_csrf=$tI" -F "napomena=Testna napomena radnika." -F "slika=@$TW/nakon.png" "$B/radnik/zadaci/10/zavrsi"' "U_RADU" "CEKA_PROVJERU"
provjeri "admin: odobri" 'admin_post 10/odobri' "CEKA_PROVJERU" "ZAVRSENO"
provjeri "admin: vrati" 'admin_post 10/vrati -d komentar=Testni_razlog' "CEKA_PROVJERU" "VRACENO"
provjeri "radnik: odobri (ruta admina)" 'code -b "$IVAN" -X POST --data-urlencode "_csrf=$tI" "$B/admin/prijave/10/odobri"' "" "ZAVRSENO"

reset_baze
sazetak
