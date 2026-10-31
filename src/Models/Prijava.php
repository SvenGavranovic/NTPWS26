<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Tablica `prijave`. */
final class Prijava
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function statistika(): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS ukupno,
                    COALESCE(SUM(status = 'ZAVRSENO'), 0) AS rijeseno,
                    COALESCE(SUM(status IN ('DODIJELJENO', 'U_RADU', 'CEKA_PROVJERU', 'VRACENO')), 0) AS u_obradi
               FROM prijave"
        )->fetch();

        return array_map('intval', $row);
    }

    /** Datum, hitnost i status postavlja baza (DEFAULT). */
    public function create(int $korisnikId, array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO prijave (korisnik_id, cetvrt_id, vrsta_id, naslov, opis, adresa, lat, lng)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$korisnikId, $d['cetvrt_id'], $d['vrsta_id'], $d['naslov'], $d['opis'], $d['adresa'], $d['lat'] ?? null, $d['lng'] ?? null]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.naziv AS cetvrt, v.naziv AS vrsta,
                    CONCAT(k.ime, ' ', k.prezime) AS prijavitelj,
                    z.radnik_id, z.napomena_radnika, CONCAT(r.ime, ' ', r.prezime) AS radnik
               FROM prijave p
               JOIN gradske_cetvrti c ON c.id = p.cetvrt_id
               JOIN vrste_problema v  ON v.id = p.vrsta_id
               JOIN korisnici k       ON k.id = p.korisnik_id
               LEFT JOIN zadaci z     ON z.prijava_id = p.id
               LEFT JOIN korisnici r  ON r.id = z.radnik_id
              WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Uz ID prve slike "prije" (sličica). */
    public function findByUser(int $korisnikId, ?string $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->userWhere($korisnikId, $status);

        $stmt = $this->db->prepare(
            "SELECT p.id, p.naslov, p.status, p.kreirano_u, p.azurirano_u, p.adresa,
                    c.naziv AS cetvrt, v.naziv AS vrsta,
                    (SELECT s.id FROM prijava_slike s
                      WHERE s.prijava_id = p.id AND s.tip = 'prije'
                      ORDER BY s.id LIMIT 1) AS slika_id
               FROM prijave p
               JOIN gradske_cetvrti c ON c.id = p.cetvrt_id
               JOIN vrste_problema v  ON v.id = p.vrsta_id
              WHERE {$where}
              ORDER BY p.kreirano_u DESC, p.id DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByUser(int $korisnikId, ?string $status = null): int
    {
        [$where, $params] = $this->userWhere($korisnikId, $status);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prijave p WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countRecentByUser(int $korisnikId, int $minuta): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM prijave WHERE korisnik_id = :k AND kreirano_u > NOW() - INTERVAL :m MINUTE'
        );
        $stmt->bindValue(':k', $korisnikId, PDO::PARAM_INT);
        $stmt->bindValue(':m', $minuta, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** Mijenja status samo ako je prijava još u statusu $iz. */
    public function updateStatus(int $id, string $iz, string $u): bool
    {
        $stmt = $this->db->prepare('UPDATE prijave SET status = ? WHERE id = ? AND status = ?');
        $stmt->execute([$u, $id, $iz]);
        return $stmt->rowCount() === 1;
    }

    public function updateHitnost(int $id, string $hitnost): void
    {
        $stmt = $this->db->prepare('UPDATE prijave SET hitnost = ? WHERE id = ?');
        $stmt->execute([$hitnost, $id]);
    }

    /**
     * Sažetak prijava bez osobnih podataka (API, karta).
     * @param array{status: ?string, cetvrt: ?int, vrsta: ?int, korisnik: ?int} $f
     */
    public function findSummary(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->summaryWhere($f);

        $stmt = $this->db->prepare(
            "SELECT p.id, p.naslov, p.status, p.hitnost, p.lat, p.lng, p.kreirano_u, p.azurirano_u,
                    c.id AS cetvrt_id, c.naziv AS cetvrt, v.id AS vrsta_id, v.naziv AS vrsta
               FROM prijave p
               JOIN gradske_cetvrti c ON c.id = p.cetvrt_id
               JOIN vrste_problema v  ON v.id = p.vrsta_id
              WHERE {$where}
              ORDER BY p.kreirano_u DESC, p.id DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countSummary(array $f): int
    {
        [$where, $params] = $this->summaryWhere($f);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prijave p WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function summaryWhere(array $f): array
    {
        $where = ['1 = 1'];
        $params = [];
        foreach (['status' => 'p.status', 'cetvrt' => 'p.cetvrt_id', 'vrsta' => 'p.vrsta_id', 'korisnik' => 'p.korisnik_id'] as $kljuc => $stupac) {
            if ($f[$kljuc] !== null) {
                $where[] = "{$stupac} = :{$kljuc}";
                $params[":{$kljuc}"] = $f[$kljuc];
            }
        }
        return [implode(' AND ', $where), $params];
    }

    /** Prvo prijave koje čekaju provjeru, zatim neriješene po hitnosti. */
    public function findForAdmin(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminWhere($f);

        $stmt = $this->db->prepare(
            "SELECT p.id, p.naslov, p.status, p.hitnost, p.kreirano_u,
                    c.naziv AS cetvrt, v.naziv AS vrsta,
                    CONCAT(r.ime, ' ', r.prezime) AS radnik
               FROM prijave p
               JOIN gradske_cetvrti c ON c.id = p.cetvrt_id
               JOIN vrste_problema v  ON v.id = p.vrsta_id
               LEFT JOIN zadaci z     ON z.prijava_id = p.id
               LEFT JOIN korisnici r  ON r.id = z.radnik_id
              WHERE {$where}
              ORDER BY p.status = 'ZAVRSENO', p.status <> 'CEKA_PROVJERU', p.hitnost DESC, p.kreirano_u
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countForAdmin(array $f): int
    {
        [$where, $params] = $this->adminWhere($f);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prijave p WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countByStatus(): array
    {
        return $this->db->query('SELECT status, COUNT(*) FROM prijave GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @param array{status: ?string, hitnost: ?string, cetvrt: ?int, q: string} $f
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function adminWhere(array $f): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($f['status'] !== null) {
            $where[] = 'p.status = :status';
            $params[':status'] = $f['status'];
        }
        if ($f['hitnost'] !== null) {
            $where[] = 'p.hitnost = :hitnost';
            $params[':hitnost'] = $f['hitnost'];
        }
        if ($f['cetvrt'] !== null) {
            $where[] = 'p.cetvrt_id = :cetvrt';
            $params[':cetvrt'] = $f['cetvrt'];
        }
        if ($f['q'] !== '') {
            $where[] = 'p.naslov LIKE :q';
            $params[':q'] = '%' . addcslashes($f['q'], '%_\\') . '%';
        }
        return [implode(' AND ', $where), $params];
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function userWhere(int $korisnikId, ?string $status): array
    {
        $where = ['p.korisnik_id = :korisnik'];
        $params = [':korisnik' => $korisnikId];

        if ($status !== null) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        return [implode(' AND ', $where), $params];
    }
}
