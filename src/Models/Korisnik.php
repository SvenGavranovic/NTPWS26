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
}
