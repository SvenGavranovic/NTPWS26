<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Povijest statusa prijave (zapisi se samo dodaju). */
final class PovijestStatusa
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function add(int $prijavaId, int $korisnikId, ?string $iz, string $u, ?string $komentar = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO povijest_statusa (prijava_id, korisnik_id, iz_statusa, u_status, komentar)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$prijavaId, $korisnikId, $iz, $u, $komentar]);
    }

    public function forReport(int $prijavaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.iz_statusa, h.u_status, h.komentar, h.kreirano_u, h.korisnik_id,
                    CONCAT(k.ime, ' ', k.prezime) AS osoba, u.naziv AS uloga
               FROM povijest_statusa h
               JOIN korisnici k ON k.id = h.korisnik_id
               JOIN uloge u     ON u.id = k.uloga_id
              WHERE h.prijava_id = ?
              ORDER BY h.kreirano_u, h.id"
        );
        $stmt->execute([$prijavaId]);
        return $stmt->fetchAll();
    }
}
