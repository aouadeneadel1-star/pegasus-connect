<?php

declare(strict_types=1);

namespace Tests\Unit\Heures;

use App\Services\Heures\JoursFeries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JoursFeriesTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function datesDePaques(): iterable
    {
        yield '2024' => [2024, '2024-03-31'];
        yield '2026' => [2026, '2026-04-05'];
        yield '2027' => [2027, '2027-03-28'];
        yield '2038 (plus tardive du siècle)' => [2038, '2038-04-25'];
        yield '2285 (plus précoce possible)' => [2285, '2285-03-22'];
    }

    #[DataProvider('datesDePaques')]
    public function testPaques(int $annee, string $attendu): void
    {
        self::assertSame($attendu, JoursFeries::paques($annee)->format('Y-m-d'));
    }

    public function testOnzeJoursFeriesEnOrdre(): void
    {
        $jours = JoursFeries::pourAnnee(2026);

        self::assertCount(11, $jours);
        self::assertSame(array_keys($jours), array_values(array_unique(array_keys($jours))));
        $tries = array_keys($jours);
        sort($tries);
        self::assertSame($tries, array_keys($jours));
    }

    public function testConcordeAvecLesDonneesInitiales(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 3) . '/database/seeds/004_jours_feries.sql');
        preg_match_all("/\\('(\\d{4}-\\d{2}-\\d{2})', '((?:[^']|'')+)'/", $seed, $lignes, PREG_SET_ORDER);
        $attendu = [];
        foreach ($lignes as [, $date, $libelle]) {
            $attendu[$date] = str_replace("''", "'", $libelle);
        }

        $calcule = [];
        foreach (range(2026, 2030) as $annee) {
            $calcule += JoursFeries::pourAnnee($annee);
        }

        self::assertCount(55, $attendu);
        self::assertSame($attendu, $calcule);
    }
}
