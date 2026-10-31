<?php

declare(strict_types=1);

namespace Tests\Unit\Pointage;

use App\Services\Pointage\Geo;
use PHPUnit\Framework\TestCase;

final class GeoTest extends TestCase
{
    public function testMemePointDistanceNulle(): void
    {
        self::assertSame(0, Geo::distance(43.6961, 7.2719, 43.6961, 7.2719));
    }

    public function testUnDegreDeLatitude(): void
    {
        self::assertSame(111195, Geo::distance(43.0, 7.0, 44.0, 7.0));
    }

    public function testDistanceSymetrique(): void
    {
        // Port de Nice → gare de Nice-Ville, environ 2,4 km.
        $aller = Geo::distance(43.6961, 7.2856, 43.7045, 7.2619);

        self::assertSame($aller, Geo::distance(43.7045, 7.2619, 43.6961, 7.2856));
        self::assertGreaterThan(2000, $aller);
        self::assertLessThan(2500, $aller);
    }

    public function testVitesse(): void
    {
        self::assertEqualsWithDelta(100.0, Geo::vitesseKmh(1000, 36), 0.001);
        self::assertInfinite(Geo::vitesseKmh(500, 0));
    }
}
