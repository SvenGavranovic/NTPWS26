<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Prijave problema. Za sada samo javna statistika za početnu stranicu;
 * ostatak dolazi u fazama 5 i 6.
 */
final class Prijava
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Zbirni brojevi – ne otkrivaju ništa o pojedinoj prijavi ni korisniku. */
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
}
