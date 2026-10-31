<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Migrator
{
    private const FILE_PATTERN = '/^\d{3}_[a-z0-9_]+\.sql$/';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
    ) {
    }

    public function ensureHistoryTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL PRIMARY KEY,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return list<string> noms de fichiers, dans l'ordre d'exécution
     */
    public function available(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $path) {
            $name = basename($path);
            if (preg_match(self::FILE_PATTERN, $name) !== 1) {
                throw new RuntimeException(sprintf(
                    'Nom de migration invalide : %s (format attendu : 001_description.sql)',
                    $name,
                ));
            }
            $files[] = $name;
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return list<string>
     */
    public function executed(): array
    {
        $statement = $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version');

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->executed()));
    }

    /**
     * @param callable(string, int): void $onMigrated reçoit le fichier et le nombre de requêtes exécutées
     */
    public function migrate(callable $onMigrated): int
    {
        $count = 0;
        $record = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');

        foreach ($this->pending() as $file) {
            // Les ordres DDL valident implicitement la transaction en cours sous MariaDB :
            // la migration n'est enregistrée qu'une fois toutes ses requêtes passées.
            $statements = SqlScript::runFile($this->pdo, $this->directory . '/' . $file);
            $record->execute(['version' => $file]);
            $onMigrated($file, $statements);
            $count++;
        }

        return $count;
    }

    public function dropAllTables(): int
    {
        $tables = $this->pdo
            ->query(
                'SELECT TABLE_NAME AS name, TABLE_TYPE AS type
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()'
            )
            ->fetchAll();

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                $name = str_replace('`', '``', (string) $table['name']);
                $keyword = $table['type'] === 'VIEW' ? 'DROP VIEW ' : 'DROP TABLE ';
                $this->pdo->exec($keyword . '`' . $name . '`');
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return count($tables);
    }
}
