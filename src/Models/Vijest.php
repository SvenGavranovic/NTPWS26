<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Vijesti za javni dio; svi upiti idu kroz publicWhere() (samo objavljene).
 */
final class Vijest
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findPublished(?int $kategorijaId, string $q, int $limit, int $offset): array
    {
        [$where, $params] = $this->publicWhere($kategorijaId, $q);

        $stmt = $this->db->prepare(
            $this->select() . " WHERE {$where}
             ORDER BY v.datum_objave DESC, v.id DESC
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

    public function countPublished(?int $kategorijaId, string $q): int
    {
        [$where, $params] = $this->publicWhere($kategorijaId, $q);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM vijesti v WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** null ako ne postoji ili nije objavljena */
    public function findPublishedById(int $id): ?array
    {
        [$where, $params] = $this->publicWhere(null, '');

        $stmt = $this->db->prepare($this->select(sadrzaj: true) . " WHERE {$where} AND v.id = :id");
        $stmt->execute($params + [':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function latestPublished(int $limit): array
    {
        return $this->findPublished(null, '', $limit, 0);
    }

    /** Sadržaj samo za detalj. */
    private function select(bool $sadrzaj = false): string
    {
        return 'SELECT v.id, v.naslov, v.sazetak, v.slika, v.datum_objave'
            . ($sadrzaj ? ', v.sadrzaj' : '') . ",
                   k.id AS kategorija_id, k.naziv AS kategorija,
                   CONCAT(a.ime, ' ', a.prezime) AS autor
              FROM vijesti v
              JOIN kategorije_vijesti k ON k.id = v.kategorija_id
              JOIN korisnici a          ON a.id = v.autor_id";
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function publicWhere(?int $kategorijaId, string $q): array
    {
        $where = ["v.status = 'objavljeno'"];
        $params = [];

        if ($kategorijaId !== null) {
            $where[] = 'v.kategorija_id = :kategorija';
            $params[':kategorija'] = $kategorijaId;
        }

        if ($q !== '') {
            // % i _ kao obični znakovi
            $like = '%' . addcslashes($q, '%_\\') . '%';
            // bez emulacije isti parametar ne smije se ponoviti
            $where[] = '(v.naslov LIKE :q1 OR v.sazetak LIKE :q2 OR v.sadrzaj LIKE :q3)';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }

        return [implode(' AND ', $where), $params];
    }
}
