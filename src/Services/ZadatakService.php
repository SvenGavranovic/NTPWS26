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
 * Promjene statusa prijave. Metode vraćaju poruku pogreške ili null.
 */
final class ZadatakService
{
    public const KONFLIKT = 'Status prijave se u međuvremenu promijenio. Osvježite stranicu i pokušajte ponovno.';
    private const HITNOSTI = ['niska', 'srednja', 'visoka', 'kriticna'];

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

    /** DODIJELJENO/VRACENO → U_RADU */
    public function start(array $radnik, array $prijava): ?string
    {
        return $this->checkTransition($radnik, $prijava, 'U_RADU')
            ?? $this->transition($prijava, 'U_RADU', (int) $radnik['id']);
    }

    /** U_RADU → CEKA_PROVJERU */
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

    /** CEKA_PROVJERU → ZAVRSENO */
    public function approve(array $admin, array $prijava): ?string
    {
        return $this->checkTransition($admin, $prijava, 'ZAVRSENO')
            ?? $this->transition($prijava, 'ZAVRSENO', (int) $admin['id']);
    }

    /** CEKA_PROVJERU → VRACENO */
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

    private function checkTransition(array $korisnik, array $prijava, string $u): ?string
    {
        // vlasništvo prije prijelaza
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

    /** Dodatne izmjene, status i povijest u jednoj transakciji. */
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
            // status se u međuvremenu promijenio
            if (!(new Prijava())->updateStatus($id, $prijava['status'], $u)) {
                $db->rollBack();
                if ($onFailure !== null) {
                    $onFailure();
                }
                return self::KONFLIKT;
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
