<?php

declare(strict_types=1);

namespace App\Services\Rondes;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * Heures de passage d'un modèle de ronde. Les plages sont saisies en heure locale et peuvent
 * passer minuit (22:00 – 06:00) ; l'intervalle entre deux rondes est compté en temps réel,
 * de sorte qu'un changement d'heure ne crée ni trou ni doublon.
 */
final class Planification
{
    /** Masque des jours : lundi = 1, mardi = 2 … dimanche = 64. */
    public const JOURS = [1 => 'Lun', 2 => 'Mar', 4 => 'Mer', 8 => 'Jeu', 16 => 'Ven', 32 => 'Sam', 64 => 'Dim'];

    /**
     * @param array<string, mixed> $modele ligne de rondes_modeles
     * @return list<DateTimeImmutable> instants UTC compris dans [du, au[
     */
    public static function occurrences(array $modele, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        $frequence = max(5, (int) $modele['frequence_minutes']) * 60;
        $masque = (int) $modele['jours_semaine'];
        [$hDebut, $mDebut] = array_map('intval', explode(':', (string) $modele['heure_debut']));
        [$hFin, $mFin] = array_map('intval', explode(':', (string) $modele['heure_fin']));
        $passeMinuit = $hFin * 60 + $mFin <= $hDebut * 60 + $mDebut;

        $jour = $du->setTimezone(Clock::local())->setTime(0, 0)->modify('-1 day');
        $dernierJour = $au->setTimezone(Clock::local())->setTime(0, 0);
        $occurrences = [];

        while ($jour <= $dernierJour) {
            $bit = 1 << ((int) $jour->format('N') - 1);
            if (($masque & $bit) !== 0) {
                $debut = $jour->setTime($hDebut, $mDebut);
                $fin = ($passeMinuit ? $jour->modify('+1 day') : $jour)->setTime($hFin, $mFin);
                for ($t = $debut->getTimestamp(); $t < $fin->getTimestamp(); $t += $frequence) {
                    if ($t >= $du->getTimestamp() && $t < $au->getTimestamp()) {
                        $occurrences[] = (new DateTimeImmutable('@' . $t))->setTimezone(Clock::utc());
                    }
                }
            }
            $jour = $jour->modify('+1 day');
        }

        return $occurrences;
    }

    public static function libelleJours(int $masque): string
    {
        if ($masque === 127) {
            return 'Tous les jours';
        }

        $jours = [];
        foreach (self::JOURS as $bit => $libelle) {
            if (($masque & $bit) !== 0) {
                $jours[] = $libelle;
            }
        }

        return implode(', ', $jours);
    }
}
