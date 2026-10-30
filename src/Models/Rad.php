<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Radovi na infrastrukturi (tablica `radovi`). */
final class Rad
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @param array{aktivni: bool, cetvrt: ?int, vrsta: ?string} $f */
    public function find(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f);

        $stmt = $this->db->prepare(
            "SELECT r.id, r.naslov, r.opis, r.vrsta, r.utjecaj_na_promet, r.adresa, r.lat, r.lng,
                    r.pocetak, r.kraj, c.id AS cetvrt_id, c.naziv AS cetvrt
               FROM radovi r
               LEFT JOIN gradske_cetvrti c ON c.id = r.cetvrt_id
              WHERE {$where}
              ORDER BY r.pocetak DESC, r.id DESC
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

    public function count(array $f): int
    {
        [$where, $params] = $this->where($f);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM radovi r WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function where(array $f): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($f['aktivni']) {
            $where[] = 'r.pocetak <= NOW() AND (r.kraj IS NULL OR r.kraj >= NOW())';
        }
        if ($f['cetvrt'] !== null) {
            $where[] = 'r.cetvrt_id = :cetvrt';
            $params[':cetvrt'] = $f['cetvrt'];
        }
        if ($f['vrsta'] !== null) {
            $where[] = 'r.vrsta = :vrsta';
            $params[':vrsta'] = $f['vrsta'];
        }
        return [implode(' AND ', $where), $params];
    }
}
