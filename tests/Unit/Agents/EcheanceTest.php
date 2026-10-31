<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use App\Core\Clock;
use App\Services\Agents\Echeance;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EcheanceTest extends TestCase
{
    protected function setUp(): void
    {
        // 23 h 30 UTC : il est déjà le 29 à Paris, le calcul doit partir de la date locale.
        Clock::freeze(new DateTimeImmutable('2026-10-28 23:30:00', Clock::utc()));
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
    }

    /**
     * @return iterable<string, array{?string, string, ?int}>
     */
    public static function echeances(): iterable
    {
        yield 'aucune' => [null, 'aucune', null];
        yield 'expirée hier' => ['2026-10-28', 'expire', -1];
        yield 'aujourd\'hui' => ['2026-10-29', '7', 0];
        yield 'J-7' => ['2026-11-05', '7', 7];
        yield 'J-8' => ['2026-11-06', '30', 8];
        yield 'J-30' => ['2026-11-28', '30', 30];
        yield 'J-90' => ['2027-01-27', '90', 90];
        yield 'au-delà' => ['2027-06-01', 'valide', 215];
        yield 'date et heure SQL' => ['2026-11-05 00:00:00', '7', 7];
    }

    #[DataProvider('echeances')]
    public function testStatut(?string $date, string $code, ?int $jours): void
    {
        $statut = Echeance::statut($date);

        self::assertSame($code, $statut['code']);
        self::assertSame($jours, $statut['jours']);
    }

    public function testLibelleDUneEcheanceLointaine(): void
    {
        self::assertSame('06/2027', Echeance::statut('2027-06-01')['libelle']);
    }
}
