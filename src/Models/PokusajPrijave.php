<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Neuspjeli pokušaji prijave (zaštita od pogađanja lozinke, vidi AuthService). */
final class PokusajPrijave
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Broj neuspjelih pokušaja u zadnjih $minuta minuta za e-mail i za IP adresu. */
    public function count(string $email, string $ip, int $minuta): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                SUM(email = :email) AS po_emailu,
                SUM(ip = :ip)       AS po_ip
               FROM pokusaji_prijave
              WHERE vrijeme > NOW() - INTERVAL :minuta MINUTE'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':minuta', $minuta, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return ['email' => (int) $row['po_emailu'], 'ip' => (int) $row['po_ip']];
    }

    public function add(string $email, string $ip): void
    {
        $stmt = $this->db->prepare('INSERT INTO pokusaji_prijave (email, ip) VALUES (?, ?)');
        $stmt->execute([$email, $ip]);

        // Usput brišemo stare zapise da tablica ne raste bez kraja
        $this->db->exec('DELETE FROM pokusaji_prijave WHERE vrijeme < NOW() - INTERVAL 1 DAY');
    }

    public function clearForEmail(string $email): void
    {
        $stmt = $this->db->prepare('DELETE FROM pokusaji_prijave WHERE email = ?');
        $stmt->execute([$email]);
    }
}
