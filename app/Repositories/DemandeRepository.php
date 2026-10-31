<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

/**
 * Demandes de contact des agents et des clients, avec leur fil de messages.
 */
final class DemandeRepository extends Repository
{
    public const CATEGORIES = [
        'rh' => 'Ressources humaines',
        'paie' => 'Paie',
        'planning' => 'Planning',
        'urgence' => 'Urgence sur site',
        'client' => 'Demande client',
        'autre' => 'Autre',
    ];

    public const STATUTS = [
        'nouvelle' => 'Nouvelle',
        'en_cours' => 'En cours',
        'attente_demandeur' => 'En attente de votre réponse',
        'resolue' => 'Résolue',
        'close' => 'Close',
    ];

    public function creer(int $demandeurId, string $categorie, string $objet, string $priorite): int
    {
        return $this->inserer('demandes', [
            'demandeur_id' => $demandeurId,
            'categorie' => $categorie,
            'objet' => mb_substr($objet, 0, 150),
            'priorite' => $priorite,
            'statut' => 'nouvelle',
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    public function ajouterMessage(int $demandeId, int $auteurId, string $message, ?int $documentId, bool $interne): int
    {
        $id = $this->inserer('demandes_messages', [
            'demande_id' => $demandeId,
            'auteur_id' => $auteurId,
            'message' => $message,
            'document_id' => $documentId,
            'interne' => $interne ? 1 : 0,
            'created_at' => Clock::sql(Clock::now()),
        ]);
        $this->executer('UPDATE demandes SET updated_at = :le WHERE id = :id', ['le' => Clock::sql(Clock::now()), 'id' => $demandeId]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT d.*, u.nom_complet AS demandeur, u.email AS demandeur_email, u.role_id AS demandeur_role, u.client_id,
                    ua.nom_complet AS assignee
             FROM demandes d JOIN utilisateurs u ON u.id = d.demandeur_id LEFT JOIN utilisateurs ua ON ua.id = d.assignee_a
             WHERE d.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(int $demandeId, bool $avecInternes): array
    {
        return $this->lignes(
            'SELECT m.*, u.nom_complet AS auteur, u.role_id AS auteur_role FROM demandes_messages m JOIN utilisateurs u ON u.id = m.auteur_id
             WHERE m.demande_id = :demande' . ($avecInternes ? '' : ' AND m.interne = 0') . ' ORDER BY m.created_at, m.id',
            ['demande' => $demandeId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function duDemandeur(int $utilisateurId): array
    {
        return $this->lignes(
            'SELECT d.*, (SELECT MAX(m.created_at) FROM demandes_messages m WHERE m.demande_id = d.id AND m.interne = 0) AS dernier_message
             FROM demandes d WHERE d.demandeur_id = :utilisateur ORDER BY COALESCE(d.updated_at, d.created_at) DESC LIMIT 100',
            ['utilisateur' => $utilisateurId],
        );
    }

    /**
     * @param array{statut?: string, categorie?: string, assignee?: int} $filtres
     * @param list<string> $categories catégories visibles par le gestionnaire
     * @return list<array<string, mixed>>
     */
    public function file(array $filtres, array $categories): array
    {
        [$condCategories, $params] = self::dans('d.categorie', $categories, 'c');
        $conditions = [$condCategories];

        if (($filtres['statut'] ?? '') === '') {
            $conditions[] = "d.statut IN ('nouvelle', 'en_cours', 'attente_demandeur')";
        } elseif (array_key_exists($filtres['statut'], self::STATUTS)) {
            $conditions[] = 'd.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (array_key_exists($filtres['categorie'] ?? '', self::CATEGORIES)) {
            $conditions[] = 'd.categorie = :categorie';
            $params['categorie'] = $filtres['categorie'];
        }
        if (($filtres['assignee'] ?? 0) > 0) {
            $conditions[] = 'd.assignee_a = :assignee';
            $params['assignee'] = (int) $filtres['assignee'];
        }

        return $this->lignes(
            "SELECT d.*, u.nom_complet AS demandeur, ua.nom_complet AS assignee
             FROM demandes d JOIN utilisateurs u ON u.id = d.demandeur_id LEFT JOIN utilisateurs ua ON ua.id = d.assignee_a
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY FIELD(d.priorite, 'urgente', 'haute', 'normale'), d.statut = 'nouvelle' DESC, d.created_at
             LIMIT 300",
            $params,
        );
    }

    /**
     * @param list<string> $categories
     */
    public function nombreNouvelles(array $categories): int
    {
        [$condition, $params] = self::dans('categorie', $categories, 'c');

        return (int) $this->valeur("SELECT COUNT(*) FROM demandes WHERE statut = 'nouvelle' AND $condition", $params);
    }

    public function changerStatut(int $id, string $statut): void
    {
        $this->modifierLigne('demandes', $id, [
            'statut' => $statut,
            'resolue_le' => in_array($statut, ['resolue', 'close'], true) ? Clock::sql(Clock::now()) : null,
        ]);
    }

    public function assigner(int $id, ?int $utilisateurId): void
    {
        $this->modifierLigne('demandes', $id, ['assignee_a' => $utilisateurId]);
    }
}
