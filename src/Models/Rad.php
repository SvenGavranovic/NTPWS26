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

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM radovi WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d, int $adminId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO radovi (naslov, opis, vrsta, utjecaj_na_promet, cetvrt_id, adresa, lat, lng, pocetak, kraj, unio_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['naslov'], $d['opis'], $d['vrsta'], $d['utjecaj_na_promet'], $d['cetvrt_id'], $d['adresa'],
            $d['lat'], $d['lng'], $d['pocetak'], $d['kraj'], $adminId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            'UPDATE radovi SET naslov = ?, opis = ?, vrsta = ?, utjecaj_na_promet = ?, cetvrt_id = ?, adresa = ?,
                               lat = ?, lng = ?, pocetak = ?, kraj = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $d['naslov'], $d['opis'], $d['vrsta'], $d['utjecaj_na_promet'], $d['cetvrt_id'], $d['adresa'],
            $d['lat'], $d['lng'], $d['pocetak'], $d['kraj'], $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM radovi WHERE id = ?');
        $stmt->execute([$id]);
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
