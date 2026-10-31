<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Journal applicatif (storage/logs/app-AAAA-MM-JJ.log), une ligne JSON par événement.
 * Ne jamais y écrire de NIR, mot de passe, jeton, UID de badge ou coordonnée GPS.
 */
final class Log
{
    private static ?string $directory = null;

    public static function configure(string $directory): void
    {
        self::$directory = $directory;
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function exception(Throwable $exception): void
    {
        self::write('error', $exception->getMessage(), [
            'exception' => $exception::class,
            'fichier' => $exception->getFile() . ':' . $exception->getLine(),
        ]);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        if (self::$directory === null) {
            error_log(sprintf('[%s] %s', $level, $message));

            return;
        }

        $line = json_encode([
            'date' => Clock::now()->format(DATE_ATOM),
            'niveau' => $level,
            'message' => $message,
            'contexte' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        file_put_contents(
            self::$directory . '/app-' . Clock::now()->format('Y-m-d') . '.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }
}
