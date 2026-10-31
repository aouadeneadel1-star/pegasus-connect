<?php

declare(strict_types=1);

namespace Tests\Unit\Heures;

use App\Core\Clock;
use App\Services\Heures\CalculHeures;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CalculHeuresTest extends TestCase
{
    private static function local(string $date): DateTimeImmutable
    {
        return Clock::fromLocal($date);
    }

    public function testServiceDeJourSansMajoration(): void
    {
        $resultat = (new CalculHeures([]))->ventiler(self::local('2026-10-14 08:00'), self::local('2026-10-14 16:00'));

        self::assertSame(480, $resultat['total']);
        self::assertSame(0, $resultat['nuit']);
        self::assertSame(0, $resultat['dimanche']);
        self::assertSame(0, $resultat['ferie']);
        self::assertSame(['2026-10-14' => 480], $resultat['jours']);
    }

    public function testServiceDeDouzeHeuresAvecPauseAuProrata(): void
    {
        $resultat = (new CalculHeures([]))->ventiler(self::local('2026-10-14 18:00'), self::local('2026-10-15 06:00'), 60);

        self::assertSame(660, $resultat['total']);
        // 9 h de nuit sur 12 : la pause est retirée à la même proportion.
        self::assertSame(495, $resultat['nuit']);
        self::assertSame(['2026-10-14' => 330, '2026-10-15' => 330], $resultat['jours']);
    }

    public function testNuitDuPassageALHeureDHiverDureDixHeures(): void
    {
        $resultat = (new CalculHeures([]))->ventiler(self::local('2026-10-24 21:00'), self::local('2026-10-25 06:00'));

        self::assertSame(600, $resultat['total']);
        self::assertSame(600, $resultat['nuit']);
        self::assertSame(420, $resultat['dimanche']);
        self::assertSame(['2026-10-24' => 180, '2026-10-25' => 420], $resultat['jours']);
    }

    public function testNuitDuPassageALHeureDEteDureHuitHeures(): void
    {
        $resultat = (new CalculHeures([]))->ventiler(self::local('2026-03-28 21:00'), self::local('2026-03-29 06:00'));

        self::assertSame(480, $resultat['total']);
        self::assertSame(480, $resultat['nuit']);
        self::assertSame(300, $resultat['dimanche']);
    }

    public function testJourFerieTombantUnDimanche(): void
    {
        $resultat = (new CalculHeures(['2026-11-01' => true]))->ventiler(self::local('2026-11-01 08:00'), self::local('2026-11-01 20:00'));

        self::assertSame(720, $resultat['total']);
        self::assertSame(720, $resultat['dimanche']);
        self::assertSame(720, $resultat['ferie']);
        self::assertSame(0, $resultat['nuit']);
    }

    public function testServiceAChevalSurDeuxMoisEstVentileParJour(): void
    {
        $resultat = (new CalculHeures(['2026-11-01' => true]))->ventiler(self::local('2026-10-31 21:00'), self::local('2026-11-01 07:00'));

        self::assertSame(600, $resultat['total']);
        self::assertSame(540, $resultat['nuit']);
        self::assertSame(420, $resultat['dimanche']);
        self::assertSame(420, $resultat['ferie']);
        self::assertSame(['2026-10-31' => 180, '2026-11-01' => 420], $resultat['jours']);
    }

    public function testServiceAChevalSurDeuxSemainesCiviles(): void
    {
        $resultat = (new CalculHeures([]))->ventiler(self::local('2026-10-18 20:00'), self::local('2026-10-19 04:00'));

        self::assertSame(['2026-10-12' => 240, '2026-10-19' => 240], $resultat['semaines']);
        self::assertSame(240, $resultat['dimanche']);
        self::assertSame(420, $resultat['nuit']);
    }

    public function testPlageDeNuitParametrable(): void
    {
        $resultat = (new CalculHeures([], 22 * 60, 5 * 60))->ventiler(self::local('2026-10-14 21:00'), self::local('2026-10-15 06:00'));

        self::assertSame(540, $resultat['total']);
        self::assertSame(420, $resultat['nuit']);
    }

    public function testServiceSansDureeRendDesZeros(): void
    {
        $instant = self::local('2026-10-14 08:00');
        $resultat = (new CalculHeures([]))->ventiler($instant, $instant->modify('-1 hour'));

        self::assertSame(0, $resultat['total']);
        self::assertSame([], $resultat['jours']);
    }

    public function testPauseSuperieureAuServiceNeDonnePasDeDureeNegative(): void
    {
        $resultat = (new CalculHeures([]))->ventiler(self::local('2026-10-14 08:00'), self::local('2026-10-14 08:30'), 60);

        self::assertSame(0, $resultat['total']);
    }

    public function testHeuresSupplementairesAuxDeuxTaux(): void
    {
        self::assertSame(['hs25' => 480, 'hs50' => 180], CalculHeures::heuresSupplementaires(46 * 60, 35 * 60, 8 * 60));
        self::assertSame(['hs25' => 120, 'hs50' => 0], CalculHeures::heuresSupplementaires(37 * 60, 35 * 60, 8 * 60));
        self::assertSame(['hs25' => 0, 'hs50' => 0], CalculHeures::heuresSupplementaires(30 * 60, 35 * 60, 8 * 60));
    }
}
