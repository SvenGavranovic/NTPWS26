<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Pristup tablici `korisnici`. Ovdje su SAMO SQL upiti, bez poslovne logike.
 * Svi upiti koriste prepared statements s parametrima (?), nikad spajanje stringova.
 */
final class Korisnik
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Korisnik s kodom uloge, BEZ hasha lozinke (za Auth::user()). */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT k.id, k.ime, k.prezime, k.email, k.aktivan, k.zadnja_prijava, k.kreirano_u,
                    u.kod AS uloga, u.naziv AS uloga_naziv
               FROM korisnici k
               JOIN uloge u ON u.id = k.uloga_id
              WHERE k.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Korisnik S hashom lozinke – koristi se samo pri prijavi. */
    public function findForLogin(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, lozinka_hash, aktivan FROM korisnici WHERE email = ?'
        );
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findPasswordHash(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT lozinka_hash FROM korisnici WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash === false ? null : $hash;
    }

    public function updateName(int $id, string $ime, string $prezime): void
    {
        $stmt = $this->db->prepare('UPDATE korisnici SET ime = ?, prezime = ? WHERE id = ?');
        $stmt->execute([$ime, $prezime, $id]);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM korisnici WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Novi korisnik s ulogom zadanom KODOM ('korisnik').
     * INSERT ... SELECT dohvaća id uloge u istom upitu.
     */
    public function create(string $ime, string $prezime, string $email, string $lozinkaHash, string $ulogaKod): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO korisnici (ime, prezime, email, lozinka_hash, uloga_id)
             SELECT ?, ?, ?, ?, id FROM uloge WHERE kod = ?'
        );
        $stmt->execute([$ime, $prezime, $email, $lozinkaHash, $ulogaKod]);

        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException("Uloga '{$ulogaKod}' ne postoji.");
        }
        return (int) $this->db->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE korisnici SET zadnja_prijava = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE korisnici SET lozinka_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }
    // --- Administracija korisnika ------------------------------------------

    /** Popis za administratora: pretraga po imenu/e-mailu, filtar uloge i statusa. */
    public function findForAdmin(string $q, ?string $uloga, ?int $aktivan, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminWhere($q, $uloga, $aktivan);

        $stmt = $this->db->prepare(
            "SELECT k.id, k.ime, k.prezime, k.email, k.aktivan, k.zadnja_prijava, k.kreirano_u,
                    u.kod AS uloga, u.naziv AS uloga_naziv
               FROM korisnici k
               JOIN uloge u ON u.id = k.uloga_id
              WHERE {$where}
              ORDER BY k.prezime, k.ime, k.id
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

    public function countForAdmin(string $q, ?string $uloga, ?int $aktivan): int
    {
        [$where, $params] = $this->adminWhere($q, $uloga, $aktivan);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM korisnici k JOIN uloge u ON u.id = k.uloga_id WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Aktivni radnici s brojem otvorenih zadataka (za odabir pri dodjeli –
     * administrator vidi koliko je tko opterećen).
     */
    public function activeWorkers(): array
    {
        return $this->db->query(
            "SELECT k.id, CONCAT(k.ime, ' ', k.prezime) AS ime,
                    COUNT(p.id) AS otvoreno
               FROM korisnici k
               JOIN uloge u ON u.id = k.uloga_id AND u.kod = 'radnik'
               LEFT JOIN zadaci z  ON z.radnik_id = k.id
               LEFT JOIN prijave p ON p.id = z.prijava_id AND p.status <> 'ZAVRSENO'
              WHERE k.aktivan = 1
              GROUP BY k.id, k.ime, k.prezime
              ORDER BY k.prezime, k.ime"
        )->fetchAll();
    }

    /** kod => naziv svih uloga (redoslijed po id-u: od najmanjih ovlasti). */
    public function roles(): array
    {
        return $this->db->query('SELECT kod, naziv FROM uloge ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Uloga se zadaje KODOM; INSERT/UPDATE ... SELECT odbija nepostojeću ulogu (0 redaka). */
    public function updateRoleAndStatus(int $id, string $ulogaKod, bool $aktivan): void
    {
        $stmt = $this->db->prepare(
            'UPDATE korisnici k
               JOIN uloge u ON u.kod = ?
                SET k.uloga_id = u.id, k.aktivan = ?
              WHERE k.id = ?'
        );
        $stmt->execute([$ulogaKod, $aktivan ? 1 : 0, $id]);
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function adminWhere(string $q, ?string $uloga, ?int $aktivan): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = "(CONCAT(k.ime, ' ', k.prezime) LIKE :q1 OR k.email LIKE :q2)";
            $params[':q1'] = $params[':q2'] = $like;
        }
        if ($uloga !== null) {
            $where[] = 'u.kod = :uloga';
            $params[':uloga'] = $uloga;
        }
        if ($aktivan !== null) {
            $where[] = 'k.aktivan = :aktivan';
            $params[':aktivan'] = $aktivan;
        }

        return [implode(' AND ', $where), $params];
    }
}
