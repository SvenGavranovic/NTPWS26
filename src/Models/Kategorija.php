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

    /** Kategorije s brojem objavljenih vijesti. */
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
    /** Kategorije s brojem svih vijesti (i nacrta). */
    public function allWithTotalCount(): array
    {
        return $this->db->query(
            'SELECT k.id, k.naziv, k.opis, COUNT(v.id) AS broj_vijesti
               FROM kategorije_vijesti k
               LEFT JOIN vijesti v ON v.kategorija_id = k.id
              GROUP BY k.id, k.naziv, k.opis
              ORDER BY k.naziv'
        )->fetchAll();
    }

    /** id => naziv */
    public function options(): array
    {
        return $this->db->query('SELECT id, naziv FROM kategorije_vijesti ORDER BY naziv')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, naziv, opis FROM kategorije_vijesti WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $naziv, ?string $opis): int
    {
        $stmt = $this->db->prepare('INSERT INTO kategorije_vijesti (naziv, opis) VALUES (?, ?)');
        $stmt->execute([$naziv, $opis]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $naziv, ?string $opis): void
    {
        $stmt = $this->db->prepare('UPDATE kategorije_vijesti SET naziv = ?, opis = ? WHERE id = ?');
        $stmt->execute([$naziv, $opis, $id]);
    }

    public function countNews(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM vijesti WHERE kategorija_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM kategorije_vijesti WHERE id = ?');
        $stmt->execute([$id]);
    }
}
