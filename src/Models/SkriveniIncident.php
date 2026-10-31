<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** TomTom incidenti koje je administrator sakrio (tablica `skriveni_incidenti`). */
final class SkriveniIncident
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** SHA-256 TomTomova ID-a (ID može biti vrlo dug). */
    public static function hash(string $incidentId): string
    {
        return hash('sha256', $incidentId);
    }

    /** @return string[] sažeci skrivenih incidenata */
    public function hashes(): array
    {
        return $this->db->query('SELECT incident_hash FROM skriveni_incidenti')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT s.id, s.opis, s.razlog, s.kreirano_u, CONCAT(k.ime, ' ', k.prezime) AS sakrio
               FROM skriveni_incidenti s
               JOIN korisnici k ON k.id = s.sakrio_id
              ORDER BY s.kreirano_u DESC"
        )->fetchAll();
    }

    /** Ponovno skrivanje istog incidenta ne mijenja ništa. */
    public function hide(string $incidentId, string $opis, string $razlog, int $adminId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO skriveni_incidenti (incident_hash, opis, razlog, sakrio_id) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = id'
        );
        $stmt->execute([self::hash($incidentId), mb_substr($opis, 0, 255), $razlog, $adminId]);
    }

    public function unhide(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM skriveni_incidenti WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }
}
