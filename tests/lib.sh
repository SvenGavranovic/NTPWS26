#!/usr/bin/env bash
# Zajedničke funkcije za testne skripte (Git Bash + curl).
# Ne pokreće se samostalno – učitavaju ga tests/*.sh.

B="${APP_URL:-http://localhost/zagreb-prijave}"
MYSQL="${MYSQL:-/c/xampp/mysql/bin/mysql.exe}"
KORIJEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
T="$(mktemp -d)"
# Windows curl (Git Bash) ne razumije uvijek /tmp putanje u -F "polje=@datoteka"
TW="$(cygpath -m "$T" 2>/dev/null || echo "$T")"
trap 'rm -rf "$T"' EXIT
LOZ='Zagreb2026!'   # lozinka svih testnih računa iz seed.sql
PROSLO=0; PALO=0

ok()   { echo "  OK    $1"; PROSLO=$((PROSLO+1)); }
fail() { echo "  PALO  $1"; PALO=$((PALO+1)); }
naslov() { echo; echo "== $1"; }

# SQL nad testnom bazom (bez zaglavlja stupaca)
SQL() { "$MYSQL" -u root zagreb_prijave -N; }

# Vraća bazu u početno stanje (schema + seed). BRIŠE SVE PODATKE!
reset_baze() {
    "$MYSQL" -u root < "$KORIJEN/database/schema.sql" && "$MYSQL" -u root < "$KORIJEN/database/seed.sql"
}

# code [curl opcije] URL → "statusni_kod preusmjerenje", tijelo odgovora ide u $T/body
code() { curl -s -o "$T/body" -D "$T/headers" -w '%{http_code} %{redirect_url}' "$@"; }

# check "opis" "dobiveno" "očekivani početak"
check() { if [[ "$2" == $3* ]]; then ok "$1 → $2"; else fail "$1 → dobiveno '$2', očekivano '$3'"; fi; }
has()   { if grep -q -- "$2" "$T/body"; then ok "$1"; else fail "$1 (nema: $2)"; fi; }
hasnt() { if grep -q -- "$2" "$T/body"; then fail "$1 (pronađeno: $2)"; else ok "$1"; fi; }
header_has()   { if grep -qi -- "$2" "$T/headers"; then ok "$1"; else fail "$1 (nema zaglavlja: $2)"; fi; }
header_hasnt() { if grep -qi -- "$2" "$T/headers"; then fail "$1 (pronađeno zaglavlje: $2)"; else ok "$1"; fi; }
eq() { if [[ "$2" == "$3" ]]; then ok "$1 = $2"; else fail "$1: dobiveno '$2', očekivano '$3'"; fi; }

# CSRF token sa stranice $2, uz kolačiće iz datoteke $1
tok() { curl -s -b "$1" -c "$1" "$B$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }

# prijava EMAIL → ispiše putanju do datoteke s kolačićima prijavljenog korisnika
prijava() {
    local jar="$T/$1.jar" t
    rm -f "$jar"
    t=$(tok "$jar" /racun/prijava)
    curl -s -o /dev/null -b "$jar" -c "$jar" -X POST --data-urlencode "_csrf=$t" \
        --data-urlencode "email=$1" --data-urlencode "lozinka=$LOZ" "$B/racun/prijava"
    echo "$jar"
}

# Testne slike (ispravan PNG 1×1 piksel)
PNG_B64='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
napravi_png() { echo "$PNG_B64" | base64 -d > "$1"; }

sazetak() {
    echo
    echo "Rezultat: $PROSLO prošlo, $PALO palo"
    [[ $PALO -eq 0 ]]
}
