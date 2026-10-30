<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Šifrarnici za padajuće izbornike: gradske četvrti i vrste problema. */
final class Sifrarnik
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** id => naziv, abecedno (collation hr: Črnomerec dolazi nakon C...) */
    public function cetvrti(): array
    {
        return $this->db->query('SELECT id, naziv FROM gradske_cetvrti ORDER BY naziv')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** id => naziv; "Ostalo" je namjerno zadnje. */
    public function vrsteProblema(): array
    {
        return $this->db->query("SELECT id, naziv FROM vrste_problema ORDER BY naziv = 'Ostalo', naziv")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
