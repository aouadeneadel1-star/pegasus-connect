<?php

declare(strict_types=1);

namespace App\Services\Pointage;

final class Geo
{
    private const RAYON_TERRE_M = 6371008.8;

    /**
     * Distance orthodromique (formule de haversine), en mètres.
     */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return (int) round(2 * self::RAYON_TERRE_M * asin(min(1.0, sqrt($a))));
    }

    public static function vitesseKmh(int $metres, int $secondes): float
    {
        return $secondes <= 0 ? INF : ($metres / 1000) / ($secondes / 3600);
    }
}
