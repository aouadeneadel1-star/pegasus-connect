<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Horloge de l'application. Toutes les dates manipulées en interne sont en UTC ;
 * le fuseau local ne sert qu'à l'affichage et au découpage des heures de travail.
 */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    public static function now(): DateTimeImmutable
    {
        return self::$frozen ?? new DateTimeImmutable('now', self::utc());
    }

    public static function freeze(DateTimeImmutable $at): void
    {
        self::$frozen = $at->setTimezone(self::utc());
    }

    public static function unfreeze(): void
    {
        self::$frozen = null;
    }

    public static function utc(): DateTimeZone
    {
        static $utc = null;

        return $utc ??= new DateTimeZone('UTC');
    }

    public static function local(): DateTimeZone
    {
        static $local = null;

        return $local ??= new DateTimeZone(Env::get('APP_TIMEZONE', 'Europe/Paris') ?? 'Europe/Paris');
    }

    public static function sql(DateTimeImmutable $date, bool $milliseconds = false): string
    {
        return $date->setTimezone(self::utc())->format($milliseconds ? 'Y-m-d H:i:s.v' : 'Y-m-d H:i:s');
    }

    public static function fromSql(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value, self::utc());
    }

    /**
     * Interprète une date et heure saisie en heure locale (champ datetime-local).
     */
    public static function fromLocal(string $value): DateTimeImmutable
    {
        return (new DateTimeImmutable($value, self::local()))->setTimezone(self::utc());
    }
}
