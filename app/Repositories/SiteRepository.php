<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;
use App\Models\Role;

final class SiteRepository extends Repository
{
    public const CHAMPS = [
        'client_id', 'code', 'nom', 'adresse', 'code_postal', 'ville', 'latitude', 'longitude',
        'rayon_metres', 'mode_pointage', 'badge_nfc_actif', 'badge_pin_requis', 'telephone_poste', 'consignes', 'actif',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT s.*, c.raison_sociale AS client FROM sites s JOIN clients c ON c.id = s.client_id WHERE s.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouverParCode(string $code): ?array
    {
        return $this->ligne('SELECT * FROM sites WHERE code = :code', ['code' => $code]);
    }

    public function codeUtilise(string $code, ?int $saufId): bool
    {
        return (bool) $this->valeur('SELECT COUNT(*) FROM sites WHERE code = :code AND id <> :sauf', ['code' => $code, 'sauf' => $saufId ?? 0]);
    }

    /**
     * @param array{q?: string, client?: int, actif?: string} $filtres
     * @return list<array<string, mixed>>
     */
    public function lister(Perimetre $perimetre, array $filtres = []): array
    {
        [$condition, $params] = $perimetre->sqlSites('s.id');
        $conditions = [$condition];

        if (($filtres['q'] ?? '') !== '') {
            $conditions[] = '(s.nom LIKE :q_nom OR s.ville LIKE :q_ville OR s.code LIKE :q_code)';
            $params['q_nom'] = $params['q_ville'] = $params['q_code'] = '%' . $filtres['q'] . '%';
        }
        if (($filtres['client'] ?? 0) > 0) {
            $conditions[] = 's.client_id = :client';
            $params['client'] = (int) $filtres['client'];
        }
        if (($filtres['actif'] ?? '') !== 'tous') {
            $conditions[] = 's.actif = 1';
        }

        return $this->lignes(
            'SELECT s.*, c.raison_sociale AS client,
                    (SELECT COUNT(*) FROM points_controle p WHERE p.site_id = s.id AND p.actif = 1) AS nb_points,
                    (SELECT COUNT(*) FROM bornes b WHERE b.site_id = s.id AND b.statut = \'active\') AS nb_bornes
             FROM sites s JOIN clients c ON c.id = s.client_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY s.nom',
            $params,
        );
    }

    /**
     * @return list<array{id: int, nom: string}>
     */
    public function choix(Perimetre $perimetre): array
    {
        [$condition, $params] = $perimetre->sqlSites('id');

        return array_map(
            static fn (array $ligne): array => ['id' => (int) $ligne['id'], 'nom' => (string) $ligne['nom']],
            $this->lignes("SELECT id, nom FROM sites WHERE actif = 1 AND $condition ORDER BY nom", $params),
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('sites', [...$donnees, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('sites', $id, $donnees);
    }

    public function incrementerVersionQrStatique(int $id): int
    {
        $this->executer('UPDATE sites SET qr_statique_version = qr_statique_version + 1 WHERE id = :id', ['id' => $id]);

        return (int) $this->valeur('SELECT qr_statique_version FROM sites WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function responsables(int $siteId): array
    {
        return $this->lignes(
            'SELECT a.id, a.nom, a.prenom, a.utilisateur_id FROM sites_responsables sr JOIN agents a ON a.id = sr.agent_id
             WHERE sr.site_id = :site ORDER BY a.nom',
            ['site' => $siteId],
        );
    }

    /**
     * @param list<int> $agentIds
     */
    public function definirResponsables(int $siteId, array $agentIds): void
    {
        $this->executer('DELETE FROM sites_responsables WHERE site_id = :site', ['site' => $siteId]);
        foreach (array_unique($agentIds) as $agentId) {
            $this->executer('INSERT INTO sites_responsables (site_id, agent_id) VALUES (:site, :agent)', ['site' => $siteId, 'agent' => $agentId]);
        }
    }

    /**
     * @return list<int> utilisateurs des chefs responsables du site
     */
    public function utilisateursResponsables(int $siteId): array
    {
        return $this->ids(
            "SELECT a.utilisateur_id FROM sites_responsables sr
             JOIN agents a ON a.id = sr.agent_id JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE sr.site_id = :site AND u.statut = 'actif'",
            ['site' => $siteId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chefsDisponibles(): array
    {
        return $this->lignes(
            "SELECT a.id, a.nom, a.prenom FROM agents a JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE u.role_id = :role AND u.statut IN ('actif', 'invite') ORDER BY a.nom",
            ['role' => Role::Chef->id()],
        );
    }

    /**
     * @return list<array<string, mixed>> agents affectés aujourd'hui
     */
    public function agentsAffectes(int $siteId): array
    {
        return $this->lignes(
            "SELECT a.id, a.nom, a.prenom, a.matricule FROM agent_sites asg
             JOIN agents a ON a.id = asg.agent_id JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE asg.site_id = :site AND (asg.date_fin IS NULL OR asg.date_fin >= :jour) AND u.statut = 'actif'
             ORDER BY a.nom, a.prenom",
            ['site' => $siteId, 'jour' => Clock::now()->setTimezone(Clock::local())->format('Y-m-d')],
        );
    }
}
