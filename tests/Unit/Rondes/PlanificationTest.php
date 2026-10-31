<?php

declare(strict_types=1);

namespace Tests\Unit\Rondes;

use App\Core\Clock;
use App\Services\Rondes\Planification;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlanificationTest extends TestCase
{
    /**
     * @return array<string, int|string>
     */
    private static function modele(string $debut, string $fin, int $frequence, int $jours = 127): array
    {
        return ['heure_debut' => $debut, 'heure_fin' => $fin, 'frequence_minutes' => $frequence, 'jours_semaine' => $jours];
    }

    /**
     * @param list<DateTimeImmutable> $instants
     * @return list<string>
     */
    private static function heuresLocales(array $instants): array
    {
        return array_map(static fn (DateTimeImmutable $i): string => $i->setTimezone(Clock::local())->format('d H:i'), $instants);
    }

    public function testPlageDeJour(): void
    {
        $occurrences = Planification::occurrences(self::modele('08:00', '12:00', 60), Clock::fromLocal('2026-10-14 00:00'), Clock::fromLocal('2026-10-15 00:00'));

        self::assertSame(['14 08:00', '14 09:00', '14 10:00', '14 11:00'], self::heuresLocales($occurrences));
    }

    public function testPlagePassantMinuitCommenceLaVeille(): void
    {
        $occurrences = Planification::occurrences(self::modele('22:00', '06:00', 120), Clock::fromLocal('2026-10-14 00:00'), Clock::fromLocal('2026-10-15 00:00'));

        self::assertSame(['14 00:00', '14 02:00', '14 04:00', '14 22:00'], self::heuresLocales($occurrences));
    }

    public function testNuitDeChangementDHeureSansTrouNiDoublon(): void
    {
        $occurrences = Planification::occurrences(self::modele('22:00', '06:00', 60), Clock::fromLocal('2026-10-24 12:00'), Clock::fromLocal('2026-10-25 12:00'));

        // 22:00 CEST → 06:00 CET : neuf heures réelles, donc neuf rondes, dont deux affichées à 02:00.
        self::assertCount(9, $occurrences);
        self::assertSame(['24 22:00', '24 23:00', '25 00:00', '25 01:00', '25 02:00', '25 02:00', '25 03:00', '25 04:00', '25 05:00'], self::heuresLocales($occurrences));
        self::assertCount(9, array_unique(array_map(static fn (DateTimeImmutable $i): int => $i->getTimestamp(), $occurrences)));
    }

    public function testJoursDeLaSemaineRespectes(): void
    {
        $lundiSeulement = self::modele('08:00', '09:00', 60, 1);
        $occurrences = Planification::occurrences($lundiSeulement, Clock::fromLocal('2026-10-12 00:00'), Clock::fromLocal('2026-10-19 00:00'));

        self::assertSame(['12 08:00'], self::heuresLocales($occurrences));
    }

    public function testIntervalleDemiOuvert(): void
    {
        $occurrences = Planification::occurrences(self::modele('08:00', '12:00', 60), Clock::fromLocal('2026-10-14 09:00'), Clock::fromLocal('2026-10-14 11:00'));

        self::assertSame(['14 09:00', '14 10:00'], self::heuresLocales($occurrences));
    }

    public function testFrequenceMinimaleDeCinqMinutes(): void
    {
        $occurrences = Planification::occurrences(self::modele('08:00', '09:00', 1), Clock::fromLocal('2026-10-14 00:00'), Clock::fromLocal('2026-10-15 00:00'));

        self::assertCount(12, $occurrences);
    }

    public function testLibelleDesJours(): void
    {
        self::assertSame('Tous les jours', Planification::libelleJours(127));
        self::assertSame('Lun, Ven', Planification::libelleJours(1 | 16));
        self::assertSame('Sam, Dim', Planification::libelleJours(32 | 64));
    }
}
