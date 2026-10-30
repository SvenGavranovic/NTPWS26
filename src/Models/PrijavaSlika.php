<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Slike prijava (tablica `prijava_slike`): tip 'prije' (građanin) i 'poslije' (radnik). */
final class PrijavaSlika
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function add(int $prijavaId, int $ucitaoId, string $tip, string $datoteka, string $mime, int $velicina): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO prijava_slike (prijava_id, ucitao_id, tip, datoteka, mime_tip, velicina)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$prijavaId, $ucitaoId, $tip, $datoteka, $mime, $velicina]);
    }

    /** Sve slike prijave, najprije "prije", pa "poslije". */
    public function forReport(int $prijavaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, tip, datoteka, mime_tip, kreirano_u
               FROM prijava_slike WHERE prijava_id = ? ORDER BY tip = 'poslije', id"
        );
        $stmt->execute([$prijavaId]);
        return $stmt->fetchAll();
    }

    /** Slika samo ako pripada TOJ prijavi (inače bi se tuđa slika mogla dohvatiti promjenom ID-a slike). */
    public function find(int $prijavaId, int $slikaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, tip, datoteka, mime_tip FROM prijava_slike WHERE id = ? AND prijava_id = ?'
        );
        $stmt->execute([$slikaId, $prijavaId]);
        return $stmt->fetch() ?: null;
    }
}
