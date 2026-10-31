<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

/**
 * Rapports mensuels PDF par agent. Chaque régénération crée une nouvelle version :
 * un rapport déjà envoyé n'est jamais écrasé.
 */
final class RapportRepository extends Repository
{
    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        $version = (int) $this->valeur(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM rapports_mensuels WHERE agent_id = :agent AND annee = :annee AND mois = :mois',
            ['agent' => $donnees['agent_id'], 'annee' => $donnees['annee'], 'mois' => $donnees['mois']],
        );

        return $this->inserer('rapports_mensuels', [...$donnees, 'version' => $version]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT r.*, a.nom, a.prenom, a.matricule, a.utilisateur_id FROM rapports_mensuels r JOIN agents a ON a.id = r.agent_id WHERE r.id = :id',
            ['id' => $id],
        );
    }

    /**
     * Dernière version de chaque rapport d'un mois.
     *
     * @return array<int, array<string, mixed>> indexé par agent
     */
    public function duMois(int $annee, int $mois): array
    {
        $resultat = [];
        foreach ($this->lignes(
            'SELECT r.* FROM rapports_mensuels r
             WHERE r.annee = :annee AND r.mois = :mois
               AND r.version = (SELECT MAX(r2.version) FROM rapports_mensuels r2 WHERE r2.agent_id = r.agent_id AND r2.annee = r.annee AND r2.mois = r.mois)',
            ['annee' => $annee, 'mois' => $mois],
        ) as $ligne) {
            $resultat[(int) $ligne['agent_id']] = $ligne;
        }

        return $resultat;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourAgent(int $agentId): array
    {
        return $this->lignes(
            "SELECT * FROM rapports_mensuels WHERE agent_id = :agent AND statut = 'envoye' ORDER BY annee DESC, mois DESC, version DESC",
            ['agent' => $agentId],
        );
    }

    public function marquerGenere(int $id, int $documentId, string $sha256): void
    {
        $this->modifierLigne('rapports_mensuels', $id, [
            'document_id' => $documentId,
            'sha256' => $sha256,
            'statut' => 'genere',
            'genere_le' => Clock::sql(Clock::now()),
        ]);
    }

    public function marquerEnvoye(int $id): void
    {
        $this->modifierLigne('rapports_mensuels', $id, ['statut' => 'envoye', 'envoye_le' => Clock::sql(Clock::now())]);
    }

    public function marquerErreur(int $id): void
    {
        $this->modifierLigne('rapports_mensuels', $id, ['statut' => 'erreur']);
    }
}
