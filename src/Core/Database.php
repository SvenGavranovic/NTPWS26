<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Jedna PDO veza po zahtjevu. */
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
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // pravi prepared statements
                PDO::ATTR_EMULATE_PREPARES   => false,
                // rowCount(): pronađeni, a ne samo promijenjeni retci
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]);

            // ista vremenska zona kao u PHP-u
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }

        return self::$pdo;
    }
}
