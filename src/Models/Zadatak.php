<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Zadaci (tablica `zadaci`): koja je prijava dodijeljena kojem radniku.
 * Jedna prijava ima najviše jedan zadatak (UNIQUE prijava_id). Status se ovdje
 * NE sprema – čita se iz prijave (jedan izvor istine).
 */
final class Zadatak
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Dodjela ili preraspodjela. Ako zadatak već postoji (UNIQUE prijava_id),
     * mijenja se radnik, a napomena prethodnog radnika se briše.
     */
    public function assign(int $prijavaId, int $radnikId, int $adminId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO zadaci (prijava_id, radnik_id, dodijelio_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE radnik_id = VALUES(radnik_id), dodijelio_id = VALUES(dodijelio_id),
                                     napomena_radnika = NULL, dodijeljeno_u = NOW()'
        );
        $stmt->execute([$prijavaId, $radnikId, $adminId]);
    }

    public function setNote(int $prijavaId, string $napomena): void
    {
        $stmt = $this->db->prepare('UPDATE zadaci SET napomena_radnika = ? WHERE prijava_id = ?');
        $stmt->execute([$napomena, $prijavaId]);
    }

    /**
     * Kanban radnika: otvoreni zadaci i nedavno završeni (zadnjih 14 dana).
     * Unutar stupca: najhitnije prvo (ENUM se sortira po redoslijedu), pa najstarije.
     */
    public function forWorker(int $radnikId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.naslov, p.status, p.hitnost, p.adresa, p.kreirano_u, p.azurirano_u,
                    c.naziv AS cetvrt, v.naziv AS vrsta
               FROM zadaci z
               JOIN prijave p         ON p.id = z.prijava_id
               JOIN gradske_cetvrti c ON c.id = p.cetvrt_id
               JOIN vrste_problema v  ON v.id = p.vrsta_id
              WHERE z.radnik_id = ?
                AND (p.status <> 'ZAVRSENO' OR p.azurirano_u > NOW() - INTERVAL 14 DAY)
              ORDER BY p.hitnost DESC, p.kreirano_u"
        );
        $stmt->execute([$radnikId]);
        return $stmt->fetchAll();
    }
}
