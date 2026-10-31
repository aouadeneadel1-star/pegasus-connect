<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        return self::$connection ??= self::connect(
            Env::required('DB_HOST'),
            Env::int('DB_PORT', 3306),
            Env::required('DB_NAME'),
            Env::required('DB_USER'),
            Env::get('DB_PASSWORD', '') ?? '',
        );
    }

    public static function connect(string $host, int $port, string $name, string $user, string $password): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);

        // NOW() et CURRENT_TIMESTAMP doivent rester en UTC quel que soit le réglage du serveur.
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
