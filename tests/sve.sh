#!/usr/bin/env bash
# =====================================================================
# Pokreće sve testne skripte redom i ispisuje sažetak.
# Pokretanje: bash tests/sve.sh
#
# Na Windowsima svaka zatvorena TCP veza oko 2 minute ostaje u stanju
# TIME_WAIT i zauzima port. Testovi šalju tisuće zahtjeva, pa se prije svake
# skripte čeka da se oslobode portovi (inače MySQL veze ne uspijevaju).
# =====================================================================
cd "$(dirname "$0")/.." || exit 1
SKRIPTE=(sigurnost cms prijave zadaci api karta)
UKUPNO_PALO=0

cekaj_portove() {
    local n i
    for i in $(seq 1 90); do
        n=$(netstat -an 2>/dev/null | grep -c TIME_WAIT)
        (( n < 3000 )) && return
        (( i == 1 )) && echo "   čekam oslobađanje portova ($n u TIME_WAIT)…"
        sleep 10
    done
}

for s in "${SKRIPTE[@]}"; do
    cekaj_portove
    echo "== tests/$s.sh"
    izlaz=$(bash "tests/$s.sh" 2>&1)
    echo "$izlaz" | grep -E '^  (PALO|INFO)'
    rezultat=$(echo "$izlaz" | grep -E '^Rezultat:')
    echo "   $rezultat"
    palo=$(echo "$rezultat" | grep -oE '[0-9]+ palo' | grep -oE '[0-9]+')
    UKUPNO_PALO=$(( UKUPNO_PALO + ${palo:-1} ))
done

echo
if (( UKUPNO_PALO == 0 )); then echo "Svi testovi prošli."; else echo "Palih testova: $UKUPNO_PALO"; fi
(( UKUPNO_PALO == 0 ))
