<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * Statut d'affichage d'une date d'échéance (carte pro, SST, diplôme).
 */
final class Echeance
{
    /**
     * @return array{code: string, libelle: string, classe: string, jours: ?int}
     */
    public static function statut(?string $date): array
    {
        if ($date === null || $date === '') {
            return ['code' => 'aucune', 'libelle' => 'Sans échéance', 'classe' => '', 'jours' => null];
        }

        $aujourdhui = new DateTimeImmutable(Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));
        $echeance = new DateTimeImmutable(substr($date, 0, 10));
        $jours = (int) $aujourdhui->diff($echeance)->format('%r%a');

        return match (true) {
            $jours < 0 => ['code' => 'expire', 'libelle' => 'Expiré', 'classe' => 'badge-danger', 'jours' => $jours],
            $jours <= 7 => ['code' => '7', 'libelle' => $jours . ' j', 'classe' => 'badge-danger', 'jours' => $jours],
            $jours <= 30 => ['code' => '30', 'libelle' => $jours . ' j', 'classe' => 'badge-warning', 'jours' => $jours],
            $jours <= 90 => ['code' => '90', 'libelle' => $jours . ' j', 'classe' => 'badge-info', 'jours' => $jours],
            default => ['code' => 'valide', 'libelle' => $echeance->format('m/Y'), 'classe' => 'badge-success', 'jours' => $jours],
        };
    }
}
