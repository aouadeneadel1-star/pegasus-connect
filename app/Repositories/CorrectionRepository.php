<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;

final class CorrectionRepository extends Repository
{
    /**
     * @param array<string, mixed> $donnees avant/après encodés par l'appelant
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('corrections_pointage', [...$donnees, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne('SELECT * FROM corrections_pointage WHERE id = :id', ['id' => $id]);
    }

    public function decider(int $id, string $statut, int $utilisateurId): void
    {
        $this->modifierLigne('corrections_pointage', $id, [
            'statut' => $statut,
            'validee_par' => $utilisateurId,
            'validee_le' => Clock::sql(Clock::now()),
        ]);
    }

    /**
     * @return list<array<string, mixed>> propositions en attente, dans le périmètre
     */
    public function enAttente(Perimetre $perimetre): array
    {
        [$condition, $params] = $perimetre->sqlSites("JSON_UNQUOTE(JSON_EXTRACT(c.apres, '$.site_id'))");

        return $this->lignes(
            "SELECT c.*, u.nom_complet AS demandeur, a.nom, a.prenom, a.matricule
             FROM corrections_pointage c
             JOIN utilisateurs u ON u.id = c.demandee_par
             LEFT JOIN agents a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(c.apres, '$.agent_id'))
             WHERE c.statut = 'proposee' AND $condition
             ORDER BY c.created_at",
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourService(int $serviceId): array
    {
        return $this->lignes(
            'SELECT c.*, u.nom_complet AS demandeur FROM corrections_pointage c JOIN utilisateurs u ON u.id = c.demandee_par
             WHERE c.service_id = :service ORDER BY c.created_at',
            ['service' => $serviceId],
        );
    }
}
