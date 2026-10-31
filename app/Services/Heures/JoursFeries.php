<?php

declare(strict_types=1);

namespace App\Services\Heures;

use DateTimeImmutable;

/**
 * Jours fériés légaux de France métropolitaine (code du travail, art. L3133-1).
 * Pâques est calculé ici (algorithme de Meeus, calendrier grégorien) : l'extension
 * calendar de PHP n'est pas garantie sur l'hébergement.
 */
final class JoursFeries
{
    /**
     * @return array<string, string> date Y-m-d => libellé, dans l'ordre chronologique
     */
    public static function pourAnnee(int $annee): array
    {
        $paques = self::paques($annee);
        $jours = [
            $annee . '-01-01' => 'Jour de l\'an',
            $paques->modify('+1 day')->format('Y-m-d') => 'Lundi de Pâques',
            $annee . '-05-01' => 'Fête du Travail',
            $annee . '-05-08' => 'Victoire 1945',
            $paques->modify('+39 days')->format('Y-m-d') => 'Ascension',
            $paques->modify('+50 days')->format('Y-m-d') => 'Lundi de Pentecôte',
            $annee . '-07-14' => 'Fête nationale',
            $annee . '-08-15' => 'Assomption',
            $annee . '-11-01' => 'Toussaint',
            $annee . '-11-11' => 'Armistice 1918',
            $annee . '-12-25' => 'Noël',
        ];
        ksort($jours);

        return $jours;
    }

    public static function paques(int $annee): DateTimeImmutable
    {
        $a = $annee % 19;
        $b = intdiv($annee, 100);
        $c = $annee % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mois = intdiv($h + $l - 7 * $m + 114, 31);
        $jour = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $annee, $mois, $jour));
    }
}
