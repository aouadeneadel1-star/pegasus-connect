<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;

final class AgentRepository extends Repository
{
    public const CHAMPS_IDENTITE = [
        'civilite', 'nom', 'nom_naissance', 'prenom', 'date_naissance', 'lieu_naissance', 'nationalite',
        'adresse', 'code_postal', 'ville', 'telephone',
    ];

    public const CHAMPS_CONTRAT = [
        'matricule', 'fonction', 'date_embauche', 'date_sortie', 'type_contrat', 'temps_travail',
        'heures_mensuelles', 'taux_horaire_base', 'equipe_id',
    ];

    public const PAR_PAGE = 25;

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT a.*, u.email, u.statut, u.role_id, u.totp_actif, u.derniere_connexion, r.code AS role_code,
                    e.nom AS equipe
             FROM agents a
             JOIN utilisateurs u ON u.id = a.utilisateur_id
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN equipes e ON e.id = a.equipe_id
             WHERE a.id = :id',
            ['id' => $id],
        );
    }

    public function idPourUtilisateur(int $utilisateurId): ?int
    {
        $id = $this->valeur('SELECT id FROM agents WHERE utilisateur_id = :utilisateur', ['utilisateur' => $utilisateurId]);

        return $id === null ? null : (int) $id;
    }

    public function utilisateurDe(int $agentId): ?int
    {
        $id = $this->valeur('SELECT utilisateur_id FROM agents WHERE id = :id', ['id' => $agentId]);

        return $id === null ? null : (int) $id;
    }

    /**
     * @param list<int> $agentIds
     * @return list<int>
     */
    public function utilisateursDe(array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }

        $params = [];
        foreach (array_values($agentIds) as $index => $id) {
            $params['a' . $index] = $id;
        }

        return $this->ids(
            'SELECT utilisateur_id FROM agents WHERE id IN (:' . implode(', :', array_keys($params)) . ')',
            $params,
        );
    }

    public function matriculeUtilise(string $matricule, ?int $saufId = null): bool
    {
        return (bool) $this->valeur(
            'SELECT COUNT(*) FROM agents WHERE matricule = :matricule AND id <> :sauf',
            ['matricule' => $matricule, 'sauf' => $saufId ?? 0],
        );
    }

    public function nirUtilise(string $index, ?int $saufId = null): bool
    {
        return (bool) $this->valeur(
            'SELECT COUNT(*) FROM agents WHERE nir_index = :index_nir AND id <> :sauf',
            ['index_nir' => $index, 'sauf' => $saufId ?? 0],
        );
    }

    public function prochainMatricule(): string
    {
        $dernier = (int) $this->valeur(
            "SELECT MAX(CAST(SUBSTRING(matricule, 5) AS UNSIGNED)) FROM agents WHERE matricule REGEXP '^PGS-[0-9]+$'"
        );

        return sprintf('PGS-%04d', $dernier + 1);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(int $utilisateurId, array $donnees): int
    {
        return $this->inserer('agents', [...$donnees, 'utilisateur_id' => $utilisateurId, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('agents', $id, $donnees);
    }

    public function definirNir(int $id, string $chiffre, string $index, string $fin): void
    {
        $this->modifierLigne('agents', $id, ['nir_chiffre' => $chiffre, 'nir_index' => $index, 'nir_fin' => $fin]);
    }

    public function nirChiffre(int $id): ?string
    {
        $valeur = $this->valeur('SELECT nir_chiffre FROM agents WHERE id = :id', ['id' => $id]);

        return is_string($valeur) ? $valeur : null;
    }

    /**
     * @param array{q?: string, site?: int, statut?: string, echeance?: string} $filtres
     * @return array{lignes: list<array<string, mixed>>, total: int}
     */
    public function lister(Perimetre $perimetre, array $filtres, int $page): array
    {
        $aujourdhui = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
        [$condition, $params] = $perimetre->sqlAgents('a.id');
        $conditions = [$condition];

        if (($filtres['q'] ?? '') !== '') {
            $conditions[] = '(a.nom LIKE :q_nom OR a.prenom LIKE :q_prenom OR a.matricule LIKE :q_matricule)';
            $params['q_nom'] = $params['q_prenom'] = $params['q_matricule'] = '%' . $filtres['q'] . '%';
        }
        if (($filtres['site'] ?? 0) > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM agent_sites f WHERE f.agent_id = a.id AND f.site_id = :site AND (f.date_fin IS NULL OR f.date_fin >= :jour_site))';
            $params['site'] = (int) $filtres['site'];
            $params['jour_site'] = $aujourdhui;
        }
        if (in_array($filtres['statut'] ?? '', ['invite', 'actif', 'suspendu', 'desactive'], true)) {
            $conditions[] = 'u.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        $jours = ['expire' => 0, '7' => 7, '30' => 30, '90' => 90][$filtres['echeance'] ?? ''] ?? null;
        if ($jours !== null) {
            $conditions[] = 'cp.date_expiration <= :limite_echeance';
            $params['limite_echeance'] = Clock::now()->modify('+' . $jours . ' days')->format('Y-m-d');
        }

        $from = 'FROM agents a
                 JOIN utilisateurs u ON u.id = a.utilisateur_id
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN cartes_pro cp ON cp.agent_id = a.id AND cp.courante = 1
                 WHERE ' . implode(' AND ', $conditions);

        $total = (int) $this->valeur('SELECT COUNT(*) ' . $from, $params);
        $offset = max(0, ($page - 1) * self::PAR_PAGE);

        $lignes = $this->lignes(
            'SELECT a.id, a.matricule, a.nom, a.prenom, a.fonction, u.statut, r.code AS role_code,
                    cp.date_expiration AS carte_pro_expiration,
                    (SELECT s.nom FROM agent_sites asg JOIN sites s ON s.id = asg.site_id
                     WHERE asg.agent_id = a.id AND asg.principal = 1 AND (asg.date_fin IS NULL OR asg.date_fin >= :jour_principal)
                     LIMIT 1) AS site_principal,
                    (SELECT MAX(q.date_echeance) FROM qualifications q
                     JOIN types_qualification t ON t.id = q.type_qualification_id
                     WHERE q.agent_id = a.id AND t.code = \'SST\') AS sst_echeance
             ' . $from . '
             ORDER BY a.nom, a.prenom
             LIMIT ' . self::PAR_PAGE . ' OFFSET ' . $offset,
            [...$params, 'jour_principal' => $aujourdhui],
        );

        return ['lignes' => $lignes, 'total' => $total];
    }

    /**
     * @return list<array{id: int, nom: string}>
     */
    public function choix(Perimetre $perimetre): array
    {
        [$condition, $params] = $perimetre->sqlAgents('a.id');

        return array_map(
            static fn (array $ligne): array => ['id' => (int) $ligne['id'], 'nom' => (string) $ligne['nom_complet']],
            $this->lignes(
                "SELECT a.id, CONCAT(a.nom, ' ', a.prenom, ' (', a.matricule, ')') AS nom_complet
                 FROM agents a JOIN utilisateurs u ON u.id = a.utilisateur_id
                 WHERE u.statut IN ('actif', 'invite') AND $condition
                 ORDER BY a.nom, a.prenom",
                $params,
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function affectations(int $agentId): array
    {
        return $this->lignes(
            'SELECT asg.site_id, asg.date_debut, asg.date_fin, asg.principal, s.nom, s.code
             FROM agent_sites asg JOIN sites s ON s.id = asg.site_id
             WHERE asg.agent_id = :agent
             ORDER BY asg.principal DESC, s.nom',
            ['agent' => $agentId],
        );
    }

    /**
     * Remplace les affectations en cours. Les affectations terminées restent pour l'historique.
     *
     * @param list<int> $siteIds
     */
    public function definirAffectations(int $agentId, array $siteIds, ?int $sitePrincipal): void
    {
        $aujourdhui = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
        $hier = Clock::now()->setTimezone(Clock::local())->modify('-1 day')->format('Y-m-d');
        $actuelles = $this->ids(
            'SELECT site_id FROM agent_sites WHERE agent_id = :agent AND (date_fin IS NULL OR date_fin >= :jour)',
            ['agent' => $agentId, 'jour' => $aujourdhui],
        );

        foreach (array_diff($actuelles, $siteIds) as $siteId) {
            $this->executer(
                'UPDATE agent_sites SET date_fin = :hier WHERE agent_id = :agent AND site_id = :site AND (date_fin IS NULL OR date_fin >= :jour)',
                ['hier' => $hier, 'agent' => $agentId, 'site' => $siteId, 'jour' => $aujourdhui],
            );
        }

        foreach (array_diff($siteIds, $actuelles) as $siteId) {
            $this->executer(
                'INSERT INTO agent_sites (agent_id, site_id, date_debut, date_fin, principal) VALUES (:agent, :site, :debut, NULL, 0)
                 ON DUPLICATE KEY UPDATE date_fin = NULL',
                ['agent' => $agentId, 'site' => $siteId, 'debut' => $aujourdhui],
            );
        }

        $this->executer(
            'UPDATE agent_sites SET principal = IF(site_id = :principal, 1, 0) WHERE agent_id = :agent AND (date_fin IS NULL OR date_fin >= :jour)',
            ['principal' => $sitePrincipal ?? 0, 'agent' => $agentId, 'jour' => $aujourdhui],
        );
    }

    /**
     * @return list<int>
     */
    public function sitesAffectes(int $agentId): array
    {
        return $this->ids(
            'SELECT site_id FROM agent_sites WHERE agent_id = :agent AND (date_fin IS NULL OR date_fin >= :jour)',
            ['agent' => $agentId, 'jour' => Clock::now()->setTimezone(Clock::local())->format('Y-m-d')],
        );
    }

    public function estAffecte(int $agentId, int $siteId): bool
    {
        return in_array($siteId, $this->sitesAffectes($agentId), true);
    }

    /**
     * @return list<array{id: int, nom: string}>
     */
    public function equipes(): array
    {
        return array_map(
            static fn (array $ligne): array => ['id' => (int) $ligne['id'], 'nom' => (string) $ligne['nom']],
            $this->lignes('SELECT id, nom FROM equipes WHERE actif = 1 ORDER BY nom'),
        );
    }

    /**
     * @return list<array<string, mixed>> agents actifs, pour les traitements planifiés
     */
    public function actifs(): array
    {
        return $this->lignes(
            "SELECT a.id, a.utilisateur_id, a.nom, a.prenom, a.matricule, a.heures_mensuelles
             FROM agents a JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE u.statut = 'actif'
             ORDER BY a.nom, a.prenom"
        );
    }
}
