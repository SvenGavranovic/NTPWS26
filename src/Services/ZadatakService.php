<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Korisnik;
use App\Models\PovijestStatusa;
use App\Models\Prijava;
use App\Models\PrijavaSlika;
use App\Models\Zadatak;
use Throwable;

/**
 * Tok rada nad prijavom: dodjela, hitnost, početak rada, slanje na provjeru,
 * odobrenje i vraćanje.
 *
 * Svaka radnja:
 *  1. provjerava ULOGU i dopušteni prijelaz (StatusPrijave::canTransition),
 *  2. za radnika provjerava je li zadatak dodijeljen BAŠ NJEMU,
 *  3. u TRANSAKCIJI mijenja status (samo ako se u međuvremenu nije promijenio)
 *     i upisuje zapis u povijest_statusa.
 * Metode vraćaju poruku pogreške ili null kad je sve uspjelo.
 */
final class ZadatakService
{
    private const HITNOSTI = ['niska', 'srednja', 'visoka', 'kriticna'];

    /** Admin dodjeljuje prijavu radniku ili je preraspodjeljuje drugom radniku. */
    public function assign(array $admin, array $prijava, int $radnikId): ?string
    {
        if ($greska = $this->checkTransition($admin, $prijava, 'DODIJELJENO')) {
            return $greska;
        }

        $radnik = null;
        foreach ((new Korisnik())->activeWorkers() as $r) {
            if ((int) $r['id'] === $radnikId) {
                $radnik = $r;
            }
        }
        if ($radnik === null) {
            return 'Odaberite aktivnog radnika.';
        }
        if ((int) $prijava['radnik_id'] === $radnikId && $prijava['status'] === 'DODIJELJENO') {
            return 'Prijava je već dodijeljena tom radniku.';
        }

        $komentar = ($prijava['radnik_id'] ? 'Preraspodijeljeno radniku ' : 'Dodijeljeno radniku ') . $radnik['ime'] . '.';

        return $this->transition($prijava, 'DODIJELJENO', (int) $admin['id'], $komentar, function () use ($prijava, $radnikId, $admin): void {
            (new Zadatak())->assign((int) $prijava['id'], $radnikId, (int) $admin['id']);
        });
    }

    /** Hitnost mijenja samo administrator, dok prijava nije završena. */
    public function setHitnost(array $admin, array $prijava, string $hitnost): ?string
    {
        if ($admin['uloga'] !== 'admin') {
            return 'Hitnost može mijenjati samo administrator.';
        }
        if (!in_array($hitnost, self::HITNOSTI, true)) {
            return 'Odaberite hitnost.';
        }
        if ($prijava['status'] === 'ZAVRSENO') {
            return 'Završenoj prijavi ne mijenja se hitnost.';
        }
        (new Prijava())->updateHitnost((int) $prijava['id'], $hitnost);
        return null;
    }

    /** Radnik započinje rad (iz DODIJELJENO ili nakon vraćanja iz VRACENO). */
    public function start(array $radnik, array $prijava): ?string
    {
        return $this->checkTransition($radnik, $prijava, 'U_RADU')
            ?? $this->transition($prijava, 'U_RADU', (int) $radnik['id']);
    }

    /**
     * Radnik šalje obavljeni posao na provjeru. Obavezni su slika "poslije" i napomena.
     * Radnik NE može sam označiti ZAVRSENO – to odobrava administrator.
     */
    public function submit(array $radnik, array $prijava, string $napomena, ?array $slika): ?string
    {
        if ($greska = $this->checkTransition($radnik, $prijava, 'CEKA_PROVJERU')) {
            return $greska;
        }

        $napomena = trim(str_replace("\r\n", "\n", $napomena));
        $duljina = mb_strlen($napomena);
        if ($duljina < 10 || $duljina > 2000) {
            return 'Napomena o obavljenom poslu mora imati između 10 i 2000 znakova.';
        }

        $upload = ImageUpload::save($slika, PrijavaService::MAPA_SLIKA, PrijavaService::MAX_SLIKA);
        if ($upload === []) {
            return 'Fotografija stanja nakon popravka je obavezna.';
        }
        if (isset($upload['greska'])) {
            return $upload['greska'];
        }

        $id = (int) $prijava['id'];
        $radnikId = (int) $radnik['id'];
        $greska = $this->transition($prijava, 'CEKA_PROVJERU', $radnikId, $napomena,
            function () use ($id, $radnikId, $napomena, $upload): void {
                (new PrijavaSlika())->add($id, $radnikId, 'poslije', $upload['ime'], $upload['mime'], $upload['velicina']);
                (new Zadatak())->setNote($id, $napomena);
            },
            onFailure: static fn () => ImageUpload::delete($upload['ime'], PrijavaService::MAPA_SLIKA),
        );
        return $greska;
    }

    /** Administrator odobrava obavljeni posao → ZAVRSENO. */
    public function approve(array $admin, array $prijava): ?string
    {
        return $this->checkTransition($admin, $prijava, 'ZAVRSENO')
            ?? $this->transition($prijava, 'ZAVRSENO', (int) $admin['id']);
    }

    /** Administrator vraća posao radniku na doradu; razlog je obavezan. */
    public function reject(array $admin, array $prijava, string $komentar): ?string
    {
        if ($greska = $this->checkTransition($admin, $prijava, 'VRACENO')) {
            return $greska;
        }
        $komentar = trim(str_replace("\r\n", "\n", $komentar));
        $duljina = mb_strlen($komentar);
        if ($duljina < 5 || $duljina > 1000) {
            return 'Upišite razlog vraćanja (5–1000 znakova).';
        }
        return $this->transition($prijava, 'VRACENO', (int) $admin['id'], $komentar);
    }

    /**
     * Provjera ovlasti PRIJE bilo kakve izmjene:
     *  - prijelaz mora biti u tablici StatusPrijave::PRIJELAZI za tu ulogu,
     *  - radnik smije mijenjati samo zadatak koji je dodijeljen njemu.
     */
    private function checkTransition(array $korisnik, array $prijava, string $u): ?string
    {
        // Vlasništvo se provjerava PRVO: radnik o tuđem zadatku ne smije doznati ni njegov status
        if ($korisnik['uloga'] === 'radnik' && (int) $prijava['radnik_id'] !== (int) $korisnik['id']) {
            return 'Ovaj zadatak nije dodijeljen vama.';
        }
        if (!StatusPrijave::canTransition($prijava['status'], $u, $korisnik['uloga'])) {
            [$iz] = StatusPrijave::STATUSI[$prijava['status']];
            [$cilj] = StatusPrijave::STATUSI[$u];
            return "Prijelaz „{$iz}” → „{$cilj}” nije dopušten.";
        }
        return null;
    }

    /**
     * Promjena statusa u transakciji: dodatne izmjene ($dodatno) + status + povijest.
     * Ako je netko u međuvremenu promijenio status, sve se poništava.
     */
    private function transition(
        array $prijava,
        string $u,
        int $korisnikId,
        ?string $komentar = null,
        ?callable $dodatno = null,
        ?callable $onFailure = null,
    ): ?string {
        $id = (int) $prijava['id'];
        $db = Database::connection();
        $db->beginTransaction();
        try {
            if ($dodatno !== null) {
                $dodatno();
            }
            // UPDATE ... WHERE status = <stari status>: ako ga je netko u međuvremenu
            // promijenio, ne mijenja se ništa i cijela se transakcija poništava
            if (!(new Prijava())->updateStatus($id, $prijava['status'], $u)) {
                $db->rollBack();
                if ($onFailure !== null) {
                    $onFailure();
                }
                return 'Status prijave se u međuvremenu promijenio. Osvježite stranicu i pokušajte ponovno.';
            }
            (new PovijestStatusa())->add($id, $korisnikId, $prijava['status'], $u, $komentar);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            if ($onFailure !== null) {
                $onFailure();
            }
            throw $e;
        }
        return null;
    }
}
