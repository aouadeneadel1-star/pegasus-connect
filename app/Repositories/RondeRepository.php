<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;
use DateTimeImmutable;

final class RondeRepository extends Repository
{
    public const PAR_PAGE = 50;

    private const SELECTION = 'SELECT r.*, s.nom AS site, s.client_id, m.nom AS modele, m.duree_max_minutes, m.ordre_impose,
                                      m.temps_min_entre_points_s, m.tolerance_demarrage_min, a.nom, a.prenom, a.matricule
                               FROM rondes r
                               JOIN sites s ON s.id = r.site_id
                               LEFT JOIN rondes_modeles m ON m.id = r.modele_id
                               LEFT JOIN agents a ON a.id = r.agent_id';

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id, bool $verrouiller = false): ?array
    {
        return $this->ligne(self::SELECTION . ' WHERE r.id = :id' . ($verrouiller ? ' FOR UPDATE' : ''), ['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parUuid(string $uuid): ?array
    {
        return $this->ligne(self::SELECTION . ' WHERE r.uuid_client = :uuid', ['uuid' => $uuid]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function enCoursPourAgent(int $agentId): ?array
    {
        return $this->ligne(self::SELECTION . " WHERE r.agent_id = :agent AND r.statut = 'en_cours'", ['agent' => $agentId]);
    }

    /**
     * Rondes planifiées qu'un agent présent sur le site peut démarrer.
     *
     * @return list<array<string, mixed>>
     */
    public function aDemarrer(int $siteId, DateTimeImmutable $maintenant): array
    {
        return $this->lignes(
            self::SELECTION . " WHERE r.site_id = :site AND r.statut = 'a_venir'
                AND r.prevue_le <= :avant
                AND DATE_ADD(r.prevue_le, INTERVAL COALESCE(m.tolerance_demarrage_min, 15) + COALESCE(m.duree_max_minutes, 45) MINUTE) >= :apres
              ORDER BY r.prevue_le",
            [
                'site' => $siteId,
                'avant' => Clock::sql($maintenant->modify('+30 minutes')),
                'apres' => Clock::sql($maintenant),
            ],
        );
    }

    public function existePrevue(int $modeleId, DateTimeImmutable $prevueLe): bool
    {
        return (bool) $this->valeur(
            'SELECT COUNT(*) FROM rondes WHERE modele_id = :modele AND prevue_le = :prevue',
            ['modele' => $modeleId, 'prevue' => Clock::sql($prevueLe)],
        );
    }

    public function creerPrevue(int $siteId, int $modeleId, DateTimeImmutable $prevueLe, int $nbPoints): int
    {
        return $this->inserer('rondes', [
            'site_id' => $siteId,
            'modele_id' => $modeleId,
            'prevue_le' => Clock::sql($prevueLe),
            'statut' => 'a_venir',
            'nb_points_prevus' => $nbPoints,
        ]);
    }

    /**
     * @param list<array{point_id: int, ordre: int, obligatoire: bool}> $points
     */
    public function demarrer(int $id, int $agentId, DateTimeImmutable $debut, array $points, string $uuid): void
    {
        $this->modifierLigne('rondes', $id, [
            'agent_id' => $agentId,
            'demarree_le' => Clock::sql($debut, true),
            'statut' => 'en_cours',
            'points_prevus' => json_encode($points, JSON_THROW_ON_ERROR),
            'nb_points_prevus' => count($points),
            'uuid_client' => $uuid,
        ]);
    }

    /**
     * @param list<array{point_id: int, ordre: int, obligatoire: bool}> $points
     */
    public function creerLibre(int $siteId, int $agentId, DateTimeImmutable $debut, array $points, string $uuid): int
    {
        return $this->inserer('rondes', [
            'site_id' => $siteId,
            'agent_id' => $agentId,
            'demarree_le' => Clock::sql($debut, true),
            'statut' => 'en_cours',
            'points_prevus' => json_encode($points, JSON_THROW_ON_ERROR),
            'nb_points_prevus' => count($points),
            'uuid_client' => $uuid,
        ]);
    }

    public function cloturer(int $id, string $statut, ?string $commentaire, bool $automatique, DateTimeImmutable $fin): void
    {
        $this->modifierLigne('rondes', $id, [
            'statut' => $statut,
            'cloturee_le' => Clock::sql($fin, true),
            'commentaire_cloture' => $commentaire,
            'cloture_auto' => $automatique ? 1 : 0,
        ]);
    }

    public function marquerManquee(int $id): void
    {
        $this->executer("UPDATE rondes SET statut = 'manquee' WHERE id = :id AND statut = 'a_venir'", ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function passages(int $rondeId): array
    {
        return $this->lignes(
            'SELECT pr.*, p.nom AS point, p.identifiant, p.zone FROM passages_ronde pr
             JOIN points_controle p ON p.id = pr.point_id
             WHERE pr.ronde_id = :ronde ORDER BY pr.horodatage, pr.id',
            ['ronde' => $rondeId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function passageParUuid(string $uuid): ?array
    {
        return $this->ligne(
            'SELECT pr.*, p.nom AS point FROM passages_ronde pr JOIN points_controle p ON p.id = pr.point_id WHERE pr.uuid_client = :uuid',
            ['uuid' => $uuid],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function passageDuPoint(int $rondeId, int $pointId): ?array
    {
        return $this->ligne(
            "SELECT pr.*, p.nom AS point FROM passages_ronde pr JOIN points_controle p ON p.id = pr.point_id
             WHERE pr.ronde_id = :ronde AND pr.point_id = :point AND pr.statut <> 'rejete' ORDER BY pr.horodatage LIMIT 1",
            ['ronde' => $rondeId, 'point' => $pointId],
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function ajouterPassage(array $donnees): int
    {
        $id = $this->inserer('passages_ronde', $donnees);
        $this->executer(
            "UPDATE rondes SET nb_points_controles = (
                 SELECT COUNT(DISTINCT point_id) FROM passages_ronde WHERE ronde_id = :ronde_compte AND statut <> 'rejete'
             ) WHERE id = :ronde",
            ['ronde_compte' => (int) $donnees['ronde_id'], 'ronde' => (int) $donnees['ronde_id']],
        );

        return $id;
    }

    /**
     * Horodatages des derniers passages de l'agent, pour repérer un scan groupé.
     *
     * @return list<DateTimeImmutable>
     */
    public function derniersPassagesAgent(int $agentId, DateTimeImmutable $depuis): array
    {
        return array_map(
            static fn (array $ligne): DateTimeImmutable => Clock::fromSql((string) $ligne['horodatage']),
            $this->lignes(
                "SELECT horodatage FROM passages_ronde WHERE agent_id = :agent AND horodatage >= :depuis AND statut <> 'rejete' ORDER BY horodatage DESC",
                ['agent' => $agentId, 'depuis' => Clock::sql($depuis, true)],
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentesPourAgent(int $agentId, int $limite): array
    {
        return $this->lignes(
            self::SELECTION . " WHERE r.agent_id = :agent AND r.statut <> 'a_venir' ORDER BY COALESCE(r.demarree_le, r.prevue_le) DESC LIMIT " . max(1, $limite),
            ['agent' => $agentId],
        );
    }

    /**
     * @param array{site?: int, statut?: string, agent?: int} $filtres
     * @return array{lignes: list<array<string, mixed>>, total: int}
     */
    public function lister(Perimetre $perimetre, DateTimeImmutable $du, DateTimeImmutable $au, array $filtres, int $page): array
    {
        [$condSite, $params] = $perimetre->sqlSites('r.site_id');
        $conditions = [$condSite, 'COALESCE(r.demarree_le, r.prevue_le) >= :du', 'COALESCE(r.demarree_le, r.prevue_le) < :au'];
        $params['du'] = Clock::sql($du);
        $params['au'] = Clock::sql($au);

        if (($filtres['site'] ?? 0) > 0) {
            $conditions[] = 'r.site_id = :site';
            $params['site'] = (int) $filtres['site'];
        }
        if (($filtres['agent'] ?? 0) > 0) {
            $conditions[] = 'r.agent_id = :agent';
            $params['agent'] = (int) $filtres['agent'];
        }
        if (in_array($filtres['statut'] ?? '', ['a_venir', 'en_cours', 'terminee', 'incomplete', 'manquee', 'annulee'], true)) {
            $conditions[] = 'r.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);
        $total = (int) $this->valeur(
            'SELECT COUNT(*) FROM rondes r LEFT JOIN rondes_modeles m ON m.id = r.modele_id' . $where,
            $params,
        );
        $offset = max(0, ($page - 1) * self::PAR_PAGE);

        return [
            'total' => $total,
            'lignes' => $this->lignes(
                self::SELECTION . $where . ' ORDER BY COALESCE(r.demarree_le, r.prevue_le) DESC LIMIT ' . self::PAR_PAGE . ' OFFSET ' . $offset,
                $params,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>> rondes planifiées non démarrées après la tolérance
     */
    public function nonDemarrees(DateTimeImmutable $maintenant): array
    {
        return $this->lignes(
            "SELECT r.id, r.site_id, r.prevue_le, s.nom AS site, m.nom AS modele FROM rondes r
             JOIN sites s ON s.id = r.site_id JOIN rondes_modeles m ON m.id = r.modele_id
             WHERE r.statut = 'a_venir' AND DATE_ADD(r.prevue_le, INTERVAL m.tolerance_demarrage_min MINUTE) < :maintenant",
            ['maintenant' => Clock::sql($maintenant)],
        );
    }

    /**
     * @return list<array<string, mixed>> rondes en cours au-delà de leur durée maximale
     */
    public function depassees(DateTimeImmutable $maintenant): array
    {
        return $this->lignes(
            "SELECT r.id, r.site_id, r.agent_id, r.demarree_le, s.nom AS site, a.utilisateur_id
             FROM rondes r JOIN sites s ON s.id = r.site_id LEFT JOIN rondes_modeles m ON m.id = r.modele_id
             LEFT JOIN agents a ON a.id = r.agent_id
             WHERE r.statut = 'en_cours'
               AND DATE_ADD(r.demarree_le, INTERVAL COALESCE(m.duree_max_minutes, 90) MINUTE) < :maintenant",
            ['maintenant' => Clock::sql($maintenant)],
        );
    }

    /**
     * Rondes d'un site sur une période (rapport de rondes, portail client).
     *
     * @return list<array<string, mixed>>
     */
    public function pourSiteEntre(int $siteId, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        return $this->lignes(
            self::SELECTION . ' WHERE r.site_id = :site AND COALESCE(r.demarree_le, r.prevue_le) >= :du
                AND COALESCE(r.demarree_le, r.prevue_le) < :au ORDER BY COALESCE(r.demarree_le, r.prevue_le)',
            ['site' => $siteId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }

    /**
     * @return array{rondes: int, points: int} activité d'un agent sur une période
     */
    public function totauxAgent(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        $ligne = $this->ligne(
            "SELECT COUNT(*) AS rondes, COALESCE(SUM(nb_points_controles), 0) AS points FROM rondes
             WHERE agent_id = :agent AND demarree_le >= :du AND demarree_le < :au AND statut IN ('terminee', 'incomplete')",
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );

        return ['rondes' => (int) ($ligne['rondes'] ?? 0), 'points' => (int) ($ligne['points'] ?? 0)];
    }
}
