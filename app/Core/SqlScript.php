<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Découpe un fichier SQL en requêtes exécutables une à une.
 *
 * Les requêtes multiples sont désactivées sur la connexion PDO : chaque instruction
 * doit donc être envoyée séparément. Les procédures stockées (DELIMITER) ne sont pas gérées.
 */
final class SqlScript
{
    /**
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`') {
                    $buffer .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if (self::startsLineComment($sql, $i)) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end - 1;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new RuntimeException('Commentaire /* non fermé dans le script SQL.');
                }
                $i = $end + 1;
                $buffer .= ' ';
                continue;
            }

            if ($char === ';') {
                self::push($statements, $buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if ($quote !== null) {
            throw new RuntimeException('Chaîne non fermée dans le script SQL.');
        }

        self::push($statements, $buffer);

        return $statements;
    }

    public static function runFile(PDO $pdo, string $path): int
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Lecture impossible : %s', $path));
        }

        $statements = self::statements($content);
        foreach ($statements as $position => $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $exception) {
                throw new RuntimeException(sprintf(
                    '%s, requête n°%d (%s…) : %s',
                    basename($path),
                    $position + 1,
                    mb_substr(preg_replace('/\s+/', ' ', $statement) ?? '', 0, 80),
                    $exception->getMessage(),
                ), 0, $exception);
            }
        }

        return count($statements);
    }

    private static function startsLineComment(string $sql, int $i): bool
    {
        if ($sql[$i] === '#') {
            return true;
        }

        // En SQL MySQL, « -- » n'ouvre un commentaire que s'il est suivi d'un blanc.
        return $sql[$i] === '-'
            && ($sql[$i + 1] ?? '') === '-'
            && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\n", "\r"], true);
    }

    /**
     * @param list<string> $statements
     */
    private static function push(array &$statements, string $buffer): void
    {
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }
}
