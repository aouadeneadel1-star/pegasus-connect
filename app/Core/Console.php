<?php

declare(strict_types=1);

namespace App\Core;

final class Console
{
    /**
     * Valeur d'une option « --nom=valeur ». Renvoie une chaîne vide pour « --nom » seul
     * et null si l'option est absente.
     *
     * @param list<string> $argv
     */
    public static function option(array $argv, string $name): ?string
    {
        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--' . $name) {
                return '';
            }
            if (str_starts_with($argument, '--' . $name . '=')) {
                return substr($argument, strlen($name) + 3);
            }
        }

        return null;
    }

    /**
     * @param list<string> $argv
     */
    public static function flag(array $argv, string $name): bool
    {
        return self::option($argv, $name) !== null;
    }

    public static function ok(string $message): void
    {
        self::write(self::color('[OK]', '32') . ' ' . $message);
    }

    public static function ko(string $message): void
    {
        self::write(self::color('[KO]', '31') . ' ' . $message);
    }

    public static function line(string $message = ''): void
    {
        self::write($message);
    }

    public static function error(string $message): void
    {
        fwrite(STDERR, self::color('Erreur :', '31') . ' ' . $message . PHP_EOL);
    }

    private static function write(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private static function color(string $text, string $code): string
    {
        if (!stream_isatty(STDOUT)) {
            return $text;
        }

        return "\033[" . $code . 'm' . $text . "\033[0m";
    }
}
