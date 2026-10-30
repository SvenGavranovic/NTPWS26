<?php

declare(strict_types=1);

/*
 * Demo slike za prijave iz seed.sql (storage/uploads/prijave/).
 * GD nije dostupan, pa se PNG gradi ručno.
 *
 *     php database/demo_slike.php
 */

const SIRINA = 640;
const VISINA = 480;
const MJERILO = 8; // jedan "piksel" slova = 8×8 piksela slike

/** Slova 5×7 piksela (samo znakovi koji se koriste u natpisima). */
const FONT = [
    'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
    'D' => ['11110', '10001', '10001', '10001', '10001', '10001', '11110'],
    'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
    'I' => ['01110', '00100', '00100', '00100', '00100', '00100', '01110'],
    'J' => ['00111', '00010', '00010', '00010', '00010', '10010', '01100'],
    'L' => ['10000', '10000', '10000', '10000', '10000', '10000', '11111'],
    'M' => ['10001', '11011', '10101', '10101', '10001', '10001', '10001'],
    'O' => ['01110', '10001', '10001', '10001', '10001', '10001', '01110'],
    'P' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
    'R' => ['11110', '10001', '10001', '11110', '10100', '10010', '10001'],
    'S' => ['01111', '10000', '10000', '01110', '00001', '00001', '11110'],
    'V' => ['10001', '10001', '10001', '10001', '10001', '01010', '00100'],
    '#' => ['01010', '01010', '11111', '01010', '11111', '01010', '01010'],
    '0' => ['01110', '10001', '10011', '10101', '11001', '10001', '01110'],
    '1' => ['00100', '01100', '00100', '00100', '00100', '00100', '01110'],
    '2' => ['01110', '10001', '00001', '00010', '00100', '01000', '11111'],
    '3' => ['11110', '00001', '00001', '01110', '00001', '00001', '11110'],
    '4' => ['00010', '00110', '01010', '10010', '11111', '00010', '00010'],
    '5' => ['11111', '10000', '11110', '00001', '00001', '10001', '01110'],
    '6' => ['00110', '01000', '10000', '11110', '10001', '10001', '01110'],
    '7' => ['11111', '00001', '00010', '00100', '01000', '01000', '01000'],
    '8' => ['01110', '10001', '10001', '01110', '10001', '10001', '01110'],
    '9' => ['01110', '10001', '10001', '01111', '00001', '00010', '01100'],
    ' ' => ['00000', '00000', '00000', '00000', '00000', '00000', '00000'],
];

/** Slike iz seed.sql: [broj prijave, tip]. */
$slike = [
    [1, 'prije'], [2, 'prije'], [3, 'prije'], [4, 'prije'], [5, 'prije'],
    [6, 'prije'], [6, 'poslije'], [7, 'prije'], [7, 'poslije'],
    [8, 'prije'], [8, 'poslije'], [9, 'prije'], [9, 'poslije'], [10, 'prije'],
];

$dir = dirname(__DIR__) . '/storage/uploads/prijave';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

foreach ($slike as [$broj, $tip]) {
    // "prije": nijanse narančaste/crvene (problem), "poslije": zelena (riješeno)
    $boja = $tip === 'prije' ? [200 + $broj * 4, 90 + $broj * 8, 40] : [40, 150, 90 + $broj * 5];
    $natpisi = [sprintf('PRIJAVA #%02d', $broj), $tip === 'prije' ? 'PRIJE' : 'POSLIJE', 'DEMO'];

    $ime = sprintf('demo-p%02d-%s.png', $broj, $tip);
    file_put_contents($dir . '/' . $ime, png(slika($boja, $natpisi)));
    echo "  {$ime}\n";
}
echo 'Stvoreno ' . count($slike) . " slika u storage/uploads/prijave/\n";

/** Pikseli kao niz redaka; svaki redak je binarni niz RGB bajtova. */
function slika(array $boja, array $natpisi): array
{
    $retci = [];
    for ($y = 0; $y < VISINA; $y++) {
        // okomiti prijelaz boje: prema dnu sve tamnije
        $f = 1 - 0.45 * $y / VISINA;
        $piksel = chr((int) ($boja[0] * $f)) . chr((int) ($boja[1] * $f)) . chr((int) ($boja[2] * $f));
        $retci[$y] = str_repeat($piksel, SIRINA);
    }

    $visinaReda = 7 * MJERILO + 24;
    $pocetakY = (int) ((VISINA - count($natpisi) * $visinaReda) / 2);
    foreach ($natpisi as $i => $tekst) {
        $sirinaTeksta = strlen($tekst) * 6 * MJERILO;
        napisi($retci, $tekst, (int) ((SIRINA - $sirinaTeksta) / 2), $pocetakY + $i * $visinaReda);
    }
    return $retci;
}

/** Upisuje tekst bijelim pikselima u retke slike. */
function napisi(array &$retci, string $tekst, int $x0, int $y0): void
{
    $bijelo = "\xFF\xFF\xFF";
    foreach (str_split($tekst) as $n => $znak) {
        foreach (FONT[$znak] ?? FONT[' '] as $red => $bitovi) {
            foreach (str_split($bitovi) as $stupac => $bit) {
                if ($bit !== '1') {
                    continue;
                }
                for ($dy = 0; $dy < MJERILO; $dy++) {
                    $y = $y0 + $red * MJERILO + $dy;
                    $x = $x0 + ($n * 6 + $stupac) * MJERILO;
                    $retci[$y] = substr_replace($retci[$y], str_repeat($bijelo, MJERILO), $x * 3, MJERILO * 3);
                }
            }
        }
    }
}

/** Gradi PNG datoteku (8-bitni RGB, bez prozirnosti). */
function png(array $retci): string
{
    $sirovo = '';
    foreach ($retci as $redak) {
        $sirovo .= "\x00" . $redak; // filtar 0 (None) ispred svakog retka
    }

    $blok = static function (string $vrsta, string $podaci): string {
        return pack('N', strlen($podaci)) . $vrsta . $podaci . pack('N', crc32($vrsta . $podaci));
    };

    return "\x89PNG\r\n\x1a\n"
        . $blok('IHDR', pack('NNCCCCC', SIRINA, VISINA, 8, 2, 0, 0, 0))
        . $blok('IDAT', gzcompress($sirovo, 9))
        . $blok('IEND', '');
}
