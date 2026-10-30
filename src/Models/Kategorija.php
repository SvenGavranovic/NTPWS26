<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Kategorija
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Sve kategorije s brojem OBJAVLJENIH vijesti (nacrti se ne broje). */
    public function allWithPublishedCount(): array
    {
        return $this->db->query(
            "SELECT k.id, k.naziv, COUNT(v.id) AS broj_vijesti
               FROM kategorije_vijesti k
               LEFT JOIN vijesti v ON v.kategorija_id = k.id AND v.status = 'objavljeno'
              GROUP BY k.id, k.naziv
              ORDER BY k.naziv"
        )->fetchAll();
    }
}
