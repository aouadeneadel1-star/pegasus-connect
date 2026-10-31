<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class DocumentRepository extends Repository
{
    public const CATEGORIES_AGENT = [
        'contrat' => 'Contrat',
        'avenant' => 'Avenant',
        'diplome' => 'Diplôme',
        'carte_pro' => 'Carte professionnelle',
        'attestation' => 'Attestation',
        'identite' => 'Pièce d\'identité',
        'autre' => 'Autre',
    ];

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('documents', [...$donnees, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne('SELECT * FROM documents WHERE id = :id AND supprime_le IS NULL', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourAgent(int $agentId, bool $visiblesSeulement, ?string $categorie = null): array
    {
        $conditions = ['agent_id = :agent', 'supprime_le IS NULL', "categorie IN ('contrat','avenant','diplome','carte_pro','attestation','identite','autre','rapport_mensuel')"];
        $params = ['agent' => $agentId];
        if ($visiblesSeulement) {
            $conditions[] = 'visible_agent = 1';
        }
        if ($categorie !== null) {
            $conditions[] = 'categorie = :categorie';
            $params['categorie'] = $categorie;
        }

        return $this->lignes(
            'SELECT * FROM documents WHERE ' . implode(' AND ', $conditions) . ' ORDER BY COALESCE(date_document, DATE(created_at)) DESC, id DESC',
            $params,
        );
    }

    public function supprimer(int $id): void
    {
        $this->modifierLigne('documents', $id, ['supprime_le' => Clock::sql(Clock::now())]);
    }

    /**
     * Le fichier d'une anomalie ou d'un point permet de retrouver le site concerné.
     */
    public function siteDeLaPhoto(int $documentId): ?int
    {
        $site = $this->valeur(
            'SELECT a.site_id FROM anomalies_photos ap JOIN anomalies a ON a.id = ap.anomalie_id WHERE ap.document_id = :document
             UNION SELECT site_id FROM points_controle WHERE photo_document_id = :document_point
             UNION SELECT id FROM sites WHERE plan_document_id = :document_plan
             LIMIT 1',
            ['document' => $documentId, 'document_point' => $documentId, 'document_plan' => $documentId],
        );

        return $site === null ? null : (int) $site;
    }

    public function anomalieVisibleClient(int $documentId): bool
    {
        return (bool) $this->valeur(
            'SELECT a.visible_client FROM anomalies_photos ap JOIN anomalies a ON a.id = ap.anomalie_id WHERE ap.document_id = :document',
            ['document' => $documentId],
        );
    }

    /**
     * @return list<array<string, mixed>> documents supprimés logiquement depuis plus de 30 jours
     */
    public function aPurger(): array
    {
        return $this->lignes(
            'SELECT id, chemin FROM documents WHERE supprime_le IS NOT NULL AND supprime_le < :limite',
            ['limite' => Clock::sql(Clock::now()->modify('-30 days'))],
        );
    }

    public function effacerDefinitivement(int $id): void
    {
        $this->executer('DELETE FROM documents WHERE id = :id', ['id' => $id]);
    }
}
