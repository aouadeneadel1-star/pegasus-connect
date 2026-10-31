<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

abstract class Repository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, mixed>|null
     */
    protected function ligne(string $sql, array $params = []): ?array
    {
        $statement = $this->executer($sql, $params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, mixed>>
     */
    protected function lignes(string $sql, array $params = []): array
    {
        return $this->executer($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, scalar|null> $params
     */
    protected function valeur(string $sql, array $params = []): mixed
    {
        $value = $this->executer($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<int>
     */
    protected function ids(string $sql, array $params = []): array
    {
        return array_map('intval', $this->executer($sql, $params)->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<string, scalar|null> $params
     */
    protected function executer(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string, scalar|null> $values colonne => valeur ; les noms de colonnes
     *                                          proviennent toujours du code, jamais de l'utilisateur
     */
    protected function inserer(string $table, array $values): int
    {
        $columns = array_keys($values);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (:%s)',
            $table,
            implode(', ', $columns),
            implode(', :', $columns),
        );
        $this->executer($sql, $values);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, scalar|null> $values
     */
    protected function modifierLigne(string $table, int $id, array $values): void
    {
        if ($values === []) {
            return;
        }

        $assignments = implode(', ', array_map(static fn (string $column): string => $column . ' = :' . $column, array_keys($values)));
        $this->executer(
            sprintf('UPDATE %s SET %s WHERE id = :id_ligne', $table, $assignments),
            [...$values, 'id_ligne' => $id],
        );
    }

    /**
     * Condition « colonne IN (...) » avec un paramètre nommé par valeur.
     *
     * @param list<int|string> $valeurs
     * @return array{0: string, 1: array<string, int|string>}
     */
    protected static function dans(string $colonne, array $valeurs, string $prefixe): array
    {
        if ($valeurs === []) {
            return ['1 = 0', []];
        }

        $params = [];
        foreach (array_values($valeurs) as $index => $valeur) {
            $params[$prefixe . $index] = $valeur;
        }

        return [$colonne . ' IN (:' . implode(', :', array_keys($params)) . ')', $params];
    }
}
