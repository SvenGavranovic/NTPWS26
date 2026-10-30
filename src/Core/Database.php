<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Jedna PDO veza po zahtjevu (otvara se tek kad zatreba).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME']
            );

            self::$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                // Svaka SQL pogreška baca iznimku – ne može proći neopaženo
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Retci se vraćaju kao asocijativni nizovi ['email' => ...]
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // PRAVI prepared statements: upit i podaci idu bazi odvojeno,
                // pa podatak nikad ne može postati dio SQL naredbe (SQL injection)
                PDO::ATTR_EMULATE_PREPARES   => false,
                // rowCount() za UPDATE vraća broj PRONAĐENIH redaka (WHERE), a ne samo
                // promijenjenih. Inače bi UPDATE na istu vrijednost (npr. preraspodjela:
                // DODIJELJENO → DODIJELJENO) izgledao kao da redak nije pronađen.
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]);

            // Ista vremenska zona u PHP-u i u bazi (CURRENT_TIMESTAMP, NOW())
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }

        return self::$pdo;
    }
}
