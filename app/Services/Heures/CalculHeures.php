<?php

declare(strict_types=1);

namespace App\Services\Heures;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * Ventilation des heures travaillées (cahier des charges, § 5.6.3).
 *
 * Chaque service est découpé aux bornes locales minuit, début et fin de la plage de nuit.
 * La durée d'un segment est mesurée en UTC : une nuit de changement d'heure compte
 * réellement 7 ou 9 heures. La pause de la vacation est répartie au prorata des segments.
 */
final class CalculHeures
{
    /**
     * @param array<string, true> $feries dates locales Y-m-d
     * @param int $nuitDebut minutes depuis minuit (21:00 → 1260)
     * @param int $nuitFin minutes depuis minuit (06:00 → 360)
     */
    public function __construct(
        private readonly array $feries,
        private readonly int $nuitDebut = 21 * 60,
        private readonly int $nuitFin = 6 * 60,
    ) {
    }

    /**
     * @return array{total: int, nuit: int, dimanche: int, ferie: int, jours: array<string, int>, semaines: array<string, int>}
     *         minutes ; « semaines » est indexé par le lundi (Y-m-d) de chaque semaine civile
     */
    public function ventiler(DateTimeImmutable $debut, DateTimeImmutable $fin, int $pauseMinutes = 0): array
    {
        $resultat = ['total' => 0, 'nuit' => 0, 'dimanche' => 0, 'ferie' => 0, 'jours' => [], 'semaines' => []];
        $dureeTotale = $fin->getTimestamp() - $debut->getTimestamp();
        if ($dureeTotale <= 0) {
            return $resultat;
        }

        $ratio = max(0.0, 1 - ($pauseMinutes * 60) / $dureeTotale);
        $secondes = ['total' => 0.0, 'nuit' => 0.0, 'dimanche' => 0.0, 'ferie' => 0.0];
        $jours = [];
        $semaines = [];

        foreach ($this->segments($debut, $fin) as [$segmentDebut, $segmentFin]) {
            $local = $segmentDebut->setTimezone(Clock::local());
            $duree = ($segmentFin->getTimestamp() - $segmentDebut->getTimestamp()) * $ratio;
            $minuteDuJour = (int) $local->format('G') * 60 + (int) $local->format('i');
            $date = $local->format('Y-m-d');
            $lundi = $local->modify('-' . ((int) $local->format('N') - 1) . ' days')->format('Y-m-d');

            $secondes['total'] += $duree;
            if ($this->estNuit($minuteDuJour)) {
                $secondes['nuit'] += $duree;
            }
            if ($local->format('N') === '7') {
                $secondes['dimanche'] += $duree;
            }
            if (isset($this->feries[$date])) {
                $secondes['ferie'] += $duree;
            }
            $jours[$date] = ($jours[$date] ?? 0.0) + $duree;
            $semaines[$lundi] = ($semaines[$lundi] ?? 0.0) + $duree;
        }

        foreach ($secondes as $cle => $valeur) {
            $resultat[$cle] = (int) round($valeur / 60);
        }
        $resultat['jours'] = array_map(static fn (float $s): int => (int) round($s / 60), $jours);
        $resultat['semaines'] = array_map(static fn (float $s): int => (int) round($s / 60), $semaines);

        return $resultat;
    }

    /**
     * Heures supplémentaires d'une semaine civile : les premières heures au-delà de la durée
     * de référence au premier taux, les suivantes au second.
     *
     * @return array{hs25: int, hs50: int}
     */
    public static function heuresSupplementaires(int $minutesSemaine, int $referenceMinutes, int $seuilPremierTauxMinutes): array
    {
        $supplementaires = max(0, $minutesSemaine - $referenceMinutes);
        $premier = min($supplementaires, $seuilPremierTauxMinutes);

        return ['hs25' => $premier, 'hs50' => $supplementaires - $premier];
    }

    /**
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    private function segments(DateTimeImmutable $debut, DateTimeImmutable $fin): array
    {
        $bornes = [];
        $jour = $debut->setTimezone(Clock::local())->setTime(0, 0);
        $dernier = $fin->setTimezone(Clock::local());
        while ($jour <= $dernier) {
            foreach ([0, $this->nuitFin, $this->nuitDebut] as $minutes) {
                $borne = $jour->setTime(intdiv($minutes, 60), $minutes % 60)->getTimestamp();
                if ($borne > $debut->getTimestamp() && $borne < $fin->getTimestamp()) {
                    $bornes[$borne] = true;
                }
            }
            $jour = $jour->modify('+1 day');
        }
        ksort($bornes);

        $segments = [];
        $courant = $debut->getTimestamp();
        foreach (array_keys($bornes) as $borne) {
            $segments[] = [self::instant($courant), self::instant($borne)];
            $courant = $borne;
        }
        $segments[] = [self::instant($courant), self::instant($fin->getTimestamp())];

        return $segments;
    }

    private function estNuit(int $minuteDuJour): bool
    {
        return $this->nuitDebut > $this->nuitFin
            ? $minuteDuJour >= $this->nuitDebut || $minuteDuJour < $this->nuitFin
            : $minuteDuJour >= $this->nuitDebut && $minuteDuJour < $this->nuitFin;
    }

    private static function instant(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(Clock::utc());
    }
}
