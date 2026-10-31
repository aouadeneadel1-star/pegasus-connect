<?php

declare(strict_types=1);

namespace App\Services\Stockage;

use App\Core\HttpException;
use App\Models\UtilisateurConnecte;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentSocieteRepository;
use App\Services\Auth\Autorisation;

/**
 * Qui peut ouvrir quel fichier. Contrôlé à chaque téléchargement, même avec un lien signé.
 */
final class AccesDocuments
{
    public function __construct(
        private readonly Autorisation $autorisation,
        private readonly DocumentRepository $documents,
        private readonly DocumentSocieteRepository $documentsSociete,
    ) {
    }

    /**
     * @param array<string, mixed> $document
     */
    public function peutLire(UtilisateurConnecte $utilisateur, array $document): bool
    {
        $agentId = $document['agent_id'] === null ? null : (int) $document['agent_id'];
        $categorie = (string) $document['categorie'];

        if ($agentId !== null && $agentId === $utilisateur->agentId && (bool) $document['visible_agent']) {
            return true;
        }

        return match ($categorie) {
            'paie' => $this->couvreAgent($utilisateur, 'paie.gerer', $agentId),
            'rapport_mensuel' => $this->couvreAgent($utilisateur, 'rapports.voir', $agentId),
            'carte_pro', 'photo_agent' => $this->couvreAgent($utilisateur, 'agents.voir', $agentId),
            'contrat', 'avenant', 'diplome', 'attestation', 'identite', 'autre' => $this->couvreAgent($utilisateur, 'agents.documents.gerer', $agentId),
            'photo_anomalie' => $this->photoAnomalie($utilisateur, (int) $document['id']),
            'photo_point', 'plan_site' => $this->couvreSite($utilisateur, 'sites.voir', $this->documents->siteDeLaPhoto((int) $document['id'])),
            'societe' => $utilisateur->peut('societe.gerer')
                || $this->documentsSociete->documentVisiblePar((int) $document['id'], $utilisateur),
            default => false,
        };
    }

    private function couvreAgent(UtilisateurConnecte $utilisateur, string $permission, ?int $agentId): bool
    {
        if ($agentId === null || !$utilisateur->peut($permission)) {
            return false;
        }

        try {
            return $this->autorisation->perimetre($utilisateur, $permission)->couvreAgent($agentId);
        } catch (HttpException) {
            return false;
        }
    }

    private function couvreSite(UtilisateurConnecte $utilisateur, string $permission, ?int $siteId): bool
    {
        if ($siteId === null || !$utilisateur->peut($permission)) {
            return false;
        }

        try {
            return $this->autorisation->perimetre($utilisateur, $permission)->couvreSite($siteId);
        } catch (HttpException) {
            return false;
        }
    }

    private function photoAnomalie(UtilisateurConnecte $utilisateur, int $documentId): bool
    {
        if (!$this->couvreSite($utilisateur, 'rondes.suivre', $this->documents->siteDeLaPhoto($documentId))) {
            return false;
        }

        return $utilisateur->clientId === null || $this->documents->anomalieVisibleClient($documentId);
    }
}
