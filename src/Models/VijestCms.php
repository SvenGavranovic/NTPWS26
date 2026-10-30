<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Vijesti za CMS, uključujući nacrte. */
final class VijestCms
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** $autorId = null → sve vijesti */
    public function find(?int $autorId, ?string $status, string $q, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($autorId, $status, $q);

        $stmt = $this->db->prepare(
            "SELECT v.id, v.naslov, v.status, v.datum_objave, v.azurirano_u, v.autor_id,
                    k.naziv AS kategorija, CONCAT(a.ime, ' ', a.prezime) AS autor
               FROM vijesti v
               JOIN kategorije_vijesti k ON k.id = v.kategorija_id
               JOIN korisnici a          ON a.id = v.autor_id
              WHERE {$where}
              ORDER BY v.azurirano_u DESC, v.id DESC
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

    public function count(?int $autorId, ?string $status, string $q): int
    {
        [$where, $params] = $this->where($autorId, $status, $q);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM vijesti v WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, kategorija_id, autor_id, naslov, sazetak, sadrzaj, slika, status, datum_objave
               FROM vijesti WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d, int $autorId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO vijesti (kategorija_id, autor_id, naslov, sazetak, sadrzaj, slika, status, datum_objave)
             VALUES (?, ?, ?, ?, ?, ?, ?, IF(? = 'objavljeno', NOW(), NULL))"
        );
        $stmt->execute([
            $d['kategorija_id'], $autorId, $d['naslov'], $d['sazetak'], $d['sadrzaj'], $d['slika'],
            $d['status'], $d['status'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Autor se ne mijenja; datum_objave se postavlja pri prvoj objavi. */
    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            "UPDATE vijesti
                SET kategorija_id = ?, naslov = ?, sazetak = ?, sadrzaj = ?, slika = ?, status = ?,
                    datum_objave = IF(? = 'objavljeno' AND datum_objave IS NULL, NOW(), datum_objave)
              WHERE id = ?"
        );
        $stmt->execute([
            $d['kategorija_id'], $d['naslov'], $d['sazetak'], $d['sadrzaj'], $d['slika'], $d['status'],
            $d['status'], $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM vijesti WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function where(?int $autorId, ?string $status, string $q): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($autorId !== null) {
            $where[] = 'v.autor_id = :autor';
            $params[':autor'] = $autorId;
        }
        if ($status !== null) {
            $where[] = 'v.status = :status';
            $params[':status'] = $status;
        }
        if ($q !== '') {
            $where[] = 'v.naslov LIKE :q';
            $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
