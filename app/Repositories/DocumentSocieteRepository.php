<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Role;
use App\Models\UtilisateurConnecte;

/**
 * Règlement intérieur, consignes, notes de service : documents versionnés, ciblés
 * (tous les agents, un site, une équipe, un rôle) et suivis par accusé de lecture.
 */
final class DocumentSocieteRepository extends Repository
{
    public const CATEGORIES = [
        'reglement' => 'Règlement intérieur',
        'consignes_generales' => 'Consignes générales',
        'consignes_site' => 'Consignes de site',
        'note_service' => 'Note de service',
        'procedure' => 'Procédure',
        'autre' => 'Autre',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function lister(string $statut = ''): array
    {
        $condition = in_array($statut, ['brouillon', 'publie', 'archive'], true) ? 'WHERE ds.statut = :statut' : "WHERE ds.statut <> 'archive'";

        return $this->lignes(
            "SELECT ds.*, v.version, v.publiee_le,
                    (SELECT COUNT(*) FROM accuses_lecture al WHERE al.version_id = ds.version_courante_id AND al.accuse_le IS NOT NULL) AS nb_accuses
             FROM documents_societe ds
             LEFT JOIN documents_societe_versions v ON v.id = ds.version_courante_id
             $condition
             ORDER BY ds.statut = 'brouillon' DESC, v.publiee_le DESC, ds.created_at DESC",
            $condition === "WHERE ds.statut <> 'archive'" ? [] : ['statut' => $statut],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT ds.*, v.version, v.document_id, v.publiee_le, v.note_version, v.date_effet
             FROM documents_societe ds LEFT JOIN documents_societe_versions v ON v.id = ds.version_courante_id
             WHERE ds.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('documents_societe', [...$donnees, 'statut' => 'brouillon', 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('documents_societe', $id, $donnees);
    }

    /**
     * @param list<array{type: string, id: int}> $cibles
     */
    public function definirCibles(int $id, array $cibles): void
    {
        $this->executer('DELETE FROM documents_societe_cibles WHERE document_societe_id = :id', ['id' => $id]);
        foreach ($cibles as $cible) {
            $this->executer(
                'INSERT IGNORE INTO documents_societe_cibles (document_societe_id, cible_type, cible_id) VALUES (:id, :type, :cible)',
                ['id' => $id, 'type' => $cible['type'], 'cible' => $cible['id']],
            );
        }
    }

    /**
     * @return list<array{type: string, id: int}>
     */
    public function cibles(int $id): array
    {
        return array_map(
            static fn (array $ligne): array => ['type' => (string) $ligne['cible_type'], 'id' => (int) $ligne['cible_id']],
            $this->lignes('SELECT cible_type, cible_id FROM documents_societe_cibles WHERE document_societe_id = :id', ['id' => $id]),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function versions(int $id): array
    {
        return $this->lignes(
            'SELECT v.*, d.nom_original, d.taille_octets, u.nom_complet AS publiee_par_nom
             FROM documents_societe_versions v JOIN documents d ON d.id = v.document_id
             LEFT JOIN utilisateurs u ON u.id = v.publiee_par
             WHERE v.document_societe_id = :id ORDER BY v.version DESC',
            ['id' => $id],
        );
    }

    public function ajouterVersion(int $id, int $documentId, ?string $note, ?string $dateEffet): int
    {
        $version = (int) $this->valeur('SELECT COALESCE(MAX(version), 0) + 1 FROM documents_societe_versions WHERE document_societe_id = :id', ['id' => $id]);

        return $this->inserer('documents_societe_versions', [
            'document_societe_id' => $id,
            'version' => $version,
            'document_id' => $documentId,
            'note_version' => $note,
            'date_effet' => $dateEffet,
        ]);
    }

    /**
     * @return array<string, mixed>|null dernière version non encore publiée
     */
    public function brouillonVersion(int $id): ?array
    {
        return $this->ligne(
            'SELECT * FROM documents_societe_versions WHERE document_societe_id = :id AND publiee_le IS NULL ORDER BY version DESC LIMIT 1',
            ['id' => $id],
        );
    }

    public function publier(int $id, int $versionId, int $utilisateurId): void
    {
        $this->executer(
            'UPDATE documents_societe_versions SET publiee_le = :le, publiee_par = :par WHERE id = :version AND document_societe_id = :id',
            ['le' => Clock::sql(Clock::now()), 'par' => $utilisateurId, 'version' => $versionId, 'id' => $id],
        );
        $this->modifierLigne('documents_societe', $id, ['statut' => 'publie', 'version_courante_id' => $versionId]);
    }

    /**
     * Utilisateurs destinataires d'un document selon son ciblage.
     *
     * @return list<int>
     */
    public function destinataires(int $id): array
    {
        $document = $this->ligne('SELECT ciblage_global FROM documents_societe WHERE id = :id', ['id' => $id]);
        if ($document === null) {
            return [];
        }

        $jour = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
        if ((bool) $document['ciblage_global']) {
            return $this->ids(
                "SELECT id FROM utilisateurs WHERE statut = 'actif' AND role_id IN (:agent, :chef)",
                ['agent' => Role::Agent->id(), 'chef' => Role::Chef->id()],
            );
        }

        return $this->ids(
            "SELECT DISTINCT u.id FROM utilisateurs u
             LEFT JOIN agents a ON a.utilisateur_id = u.id
             WHERE u.statut = 'actif' AND (
                 EXISTS (SELECT 1 FROM documents_societe_cibles c WHERE c.document_societe_id = :id_role AND c.cible_type = 'role' AND c.cible_id = u.role_id)
              OR EXISTS (SELECT 1 FROM documents_societe_cibles c WHERE c.document_societe_id = :id_equipe AND c.cible_type = 'equipe' AND c.cible_id = a.equipe_id)
              OR EXISTS (SELECT 1 FROM documents_societe_cibles c JOIN agent_sites asg ON asg.site_id = c.cible_id
                         WHERE c.document_societe_id = :id_site AND c.cible_type = 'site' AND asg.agent_id = a.id
                           AND (asg.date_fin IS NULL OR asg.date_fin >= :jour))
             )",
            ['id_role' => $id, 'id_equipe' => $id, 'id_site' => $id, 'jour' => $jour],
        );
    }

    /**
     * Documents publiés visibles d'un utilisateur, avec son état de lecture.
     *
     * @return list<array<string, mixed>>
     */
    public function visiblesPour(UtilisateurConnecte $utilisateur): array
    {
        [$condition, $params] = $this->conditionVisibilite($utilisateur);

        return $this->lignes(
            "SELECT ds.id, ds.titre, ds.categorie, ds.description, ds.accuse_requis, v.id AS version_id, v.version,
                    v.document_id, v.publiee_le, v.date_effet, v.note_version, al.ouvert_le, al.accuse_le
             FROM documents_societe ds
             JOIN documents_societe_versions v ON v.id = ds.version_courante_id
             LEFT JOIN accuses_lecture al ON al.version_id = v.id AND al.utilisateur_id = :utilisateur
             WHERE ds.statut = 'publie' AND $condition
             ORDER BY (ds.accuse_requis = 1 AND al.accuse_le IS NULL) DESC, v.publiee_le DESC",
            [...$params, 'utilisateur' => $utilisateur->id],
        );
    }

    public function nombreNonLus(UtilisateurConnecte $utilisateur): int
    {
        [$condition, $params] = $this->conditionVisibilite($utilisateur);

        return (int) $this->valeur(
            "SELECT COUNT(*) FROM documents_societe ds
             LEFT JOIN accuses_lecture al ON al.version_id = ds.version_courante_id AND al.utilisateur_id = :utilisateur
             WHERE ds.statut = 'publie' AND ds.accuse_requis = 1 AND al.accuse_le IS NULL AND $condition",
            [...$params, 'utilisateur' => $utilisateur->id],
        );
    }

    public function documentVisiblePar(int $documentId, UtilisateurConnecte $utilisateur): bool
    {
        [$condition, $params] = $this->conditionVisibilite($utilisateur);

        return (bool) $this->valeur(
            "SELECT COUNT(*) FROM documents_societe_versions v JOIN documents_societe ds ON ds.id = v.document_societe_id
             WHERE v.document_id = :document AND v.id = ds.version_courante_id AND ds.statut = 'publie' AND $condition",
            [...$params, 'document' => $documentId],
        );
    }

    /**
     * @return array<string, mixed>|null version publiée visible de l'utilisateur
     */
    public function versionVisible(int $versionId, UtilisateurConnecte $utilisateur): ?array
    {
        [$condition, $params] = $this->conditionVisibilite($utilisateur);

        return $this->ligne(
            "SELECT v.*, ds.titre, ds.accuse_requis, ds.categorie, ds.description FROM documents_societe_versions v
             JOIN documents_societe ds ON ds.id = v.document_societe_id
             WHERE v.id = :version AND v.id = ds.version_courante_id AND ds.statut = 'publie' AND $condition",
            [...$params, 'version' => $versionId],
        );
    }

    public function marquerOuvert(int $versionId, int $utilisateurId): void
    {
        $this->executer(
            'INSERT INTO accuses_lecture (version_id, utilisateur_id, ouvert_le) VALUES (:version, :utilisateur, :le)
             ON DUPLICATE KEY UPDATE ouvert_le = COALESCE(ouvert_le, :le_maj)',
            ['version' => $versionId, 'utilisateur' => $utilisateurId, 'le' => Clock::sql(Clock::now()), 'le_maj' => Clock::sql(Clock::now())],
        );
    }

    public function accuser(int $versionId, int $utilisateurId, string $ip): void
    {
        $maintenant = Clock::sql(Clock::now());
        $this->executer(
            'INSERT INTO accuses_lecture (version_id, utilisateur_id, ouvert_le, accuse_le, ip) VALUES (:version, :utilisateur, :ouvert, :accuse, :ip)
             ON DUPLICATE KEY UPDATE accuse_le = COALESCE(accuse_le, :accuse_maj), ip = COALESCE(ip, :ip_maj)',
            [
                'version' => $versionId, 'utilisateur' => $utilisateurId, 'ouvert' => $maintenant, 'accuse' => $maintenant,
                'ip' => (string) inet_pton($ip), 'accuse_maj' => $maintenant, 'ip_maj' => (string) inet_pton($ip),
            ],
        );
    }

    /**
     * Suivi des accusés d'une version : chaque destinataire et son état.
     *
     * @param list<int> $destinataires
     * @return list<array<string, mixed>>
     */
    public function suiviAccuses(int $versionId, array $destinataires): array
    {
        if ($destinataires === []) {
            return [];
        }
        [$condition, $params] = self::dans('u.id', $destinataires, 'u');

        return $this->lignes(
            "SELECT u.id, u.nom_complet, a.matricule, al.ouvert_le, al.accuse_le
             FROM utilisateurs u LEFT JOIN agents a ON a.utilisateur_id = u.id
             LEFT JOIN accuses_lecture al ON al.version_id = :version AND al.utilisateur_id = u.id
             WHERE $condition
             ORDER BY al.accuse_le IS NULL DESC, u.nom_complet",
            [...$params, 'version' => $versionId],
        );
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function conditionVisibilite(UtilisateurConnecte $utilisateur): array
    {
        $params = ['v_role' => $utilisateur->role->id()];
        $parties = ["EXISTS (SELECT 1 FROM documents_societe_cibles c WHERE c.document_societe_id = ds.id AND c.cible_type = 'role' AND c.cible_id = :v_role)"];

        if ($utilisateur->role->estTerrain()) {
            $parties[] = 'ds.ciblage_global = 1';
        }

        if ($utilisateur->agentId !== null) {
            $equipe = $this->valeur('SELECT equipe_id FROM agents WHERE id = :agent', ['agent' => $utilisateur->agentId]);
            if ($equipe !== null) {
                $params['v_equipe'] = (int) $equipe;
                $parties[] = "EXISTS (SELECT 1 FROM documents_societe_cibles c WHERE c.document_societe_id = ds.id AND c.cible_type = 'equipe' AND c.cible_id = :v_equipe)";
            }

            $sites = $this->ids(
                'SELECT site_id FROM agent_sites WHERE agent_id = :agent AND (date_fin IS NULL OR date_fin >= :jour)',
                ['agent' => $utilisateur->agentId, 'jour' => Clock::now()->setTimezone(Clock::local())->format('Y-m-d')],
            );
            if ($sites !== []) {
                [$dansSites, $paramsSites] = self::dans('c.cible_id', $sites, 'v_site');
                $params = [...$params, ...$paramsSites];
                $parties[] = "EXISTS (SELECT 1 FROM documents_societe_cibles c WHERE c.document_societe_id = ds.id AND c.cible_type = 'site' AND $dansSites)";
            }
        }

        return ['(' . implode(' OR ', $parties) . ')', $params];
    }
}
