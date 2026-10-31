<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;
use DateTimeImmutable;

final class PointageRepository extends Repository
{
    public const PAR_PAGE = 50;

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT p.*, s.nom AS site, a.nom, a.prenom, a.matricule, a.utilisateur_id
             FROM pointages p JOIN sites s ON s.id = p.site_id JOIN agents a ON a.id = p.agent_id
             WHERE p.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parUuid(string $uuid): ?array
    {
        return $this->ligne(
            'SELECT p.*, s.nom AS site FROM pointages p JOIN sites s ON s.id = p.site_id WHERE p.uuid_client = :uuid',
            ['uuid' => $uuid],
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('pointages', [...$donnees, 'created_at' => Clock::sql(Clock::now())]);
    }

    public function definirStatut(int $id, string $statut): void
    {
        $this->modifierLigne('pointages', $id, ['statut' => $statut]);
    }

    /**
     * Dernier pointage retenu de l'agent, avec la position du site pour les pointages sans GPS.
     *
     * @return array<string, mixed>|null
     */
    public function dernierPourAgent(int $agentId): ?array
    {
        return $this->ligne(
            "SELECT p.*, s.latitude AS site_latitude, s.longitude AS site_longitude
             FROM pointages p JOIN sites s ON s.id = p.site_id
             WHERE p.agent_id = :agent AND p.statut NOT IN ('annule', 'rejete')
             ORDER BY p.horodatage DESC, p.id DESC LIMIT 1",
            ['agent' => $agentId],
        );
    }

    /**
     * Pointages d'une journée locale, pour le suivi.
     *
     * @param array{site?: int, statut?: string, agent?: int} $filtres
     * @return array{lignes: list<array<string, mixed>>, total: int}
     */
    public function lister(Perimetre $perimetre, DateTimeImmutable $du, DateTimeImmutable $au, array $filtres, int $page): array
    {
        [$condSite, $params] = $perimetre->sqlSites('p.site_id');
        $conditions = [$condSite, 'p.horodatage >= :du', 'p.horodatage < :au'];
        $params['du'] = Clock::sql($du);
        $params['au'] = Clock::sql($au);

        if (($filtres['site'] ?? 0) > 0) {
            $conditions[] = 'p.site_id = :site';
            $params['site'] = (int) $filtres['site'];
        }
        if (($filtres['agent'] ?? 0) > 0) {
            $conditions[] = 'p.agent_id = :agent';
            $params['agent'] = (int) $filtres['agent'];
        }
        if (in_array($filtres['statut'] ?? '', ['valide', 'a_verifier', 'rejete', 'annule', 'corrige'], true)) {
            $conditions[] = 'p.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }

        $from = 'FROM pointages p JOIN sites s ON s.id = p.site_id JOIN agents a ON a.id = p.agent_id
                 LEFT JOIN bornes b ON b.id = p.borne_id
                 WHERE ' . implode(' AND ', $conditions);
        $total = (int) $this->valeur('SELECT COUNT(*) ' . $from, $params);
        $offset = max(0, ($page - 1) * self::PAR_PAGE);

        return [
            'total' => $total,
            'lignes' => $this->lignes(
                'SELECT p.id, p.agent_id, p.site_id, p.type, p.mode, p.horodatage, p.hors_ligne, p.distance_site_m,
                        p.precision_m, p.geoloc_statut, p.statut, p.indicateurs, p.vacation_id,
                        s.nom AS site, a.nom, a.prenom, a.matricule, b.libelle AS borne
                 ' . $from . ' ORDER BY p.horodatage DESC, p.id DESC LIMIT ' . self::PAR_PAGE . ' OFFSET ' . $offset,
                $params,
            ),
        ];
    }

    /**
     * File « à vérifier » : tous les pointages non encore traités, quelle que soit leur date.
     *
     * @return list<array<string, mixed>>
     */
    public function aVerifier(Perimetre $perimetre, int $limite = 200): array
    {
        [$condSite, $params] = $perimetre->sqlSites('p.site_id');

        return $this->lignes(
            "SELECT p.id, p.agent_id, p.type, p.mode, p.horodatage, p.hors_ligne, p.distance_site_m, p.precision_m,
                    p.geoloc_statut, p.indicateurs, s.nom AS site, a.nom, a.prenom, a.matricule
             FROM pointages p JOIN sites s ON s.id = p.site_id JOIN agents a ON a.id = p.agent_id
             WHERE p.statut = 'a_verifier' AND $condSite
             ORDER BY p.horodatage DESC LIMIT " . $limite,
            $params,
        );
    }

    public function nombreAVerifier(Perimetre $perimetre): int
    {
        [$condSite, $params] = $perimetre->sqlSites('site_id');

        return (int) $this->valeur("SELECT COUNT(*) FROM pointages WHERE statut = 'a_verifier' AND $condSite", $params);
    }

    /**
     * Export CSV : mêmes critères que la liste, sans pagination.
     *
     * @return iterable<array<string, mixed>>
     */
    public function exporter(Perimetre $perimetre, DateTimeImmutable $du, DateTimeImmutable $au, ?int $siteId): iterable
    {
        [$condSite, $params] = $perimetre->sqlSites('p.site_id');
        $params['du'] = Clock::sql($du);
        $params['au'] = Clock::sql($au);
        $filtreSite = '';
        if ($siteId !== null) {
            $filtreSite = ' AND p.site_id = :site';
            $params['site'] = $siteId;
        }

        $requete = $this->executer(
            "SELECT p.id, p.horodatage, p.type, p.mode, p.statut, p.indicateurs, p.hors_ligne, p.distance_site_m,
                    p.precision_m, p.geoloc_statut, a.matricule, a.nom, a.prenom, s.code AS site_code, s.nom AS site
             FROM pointages p JOIN sites s ON s.id = p.site_id JOIN agents a ON a.id = p.agent_id
             WHERE $condSite AND p.horodatage >= :du AND p.horodatage < :au $filtreSite
             ORDER BY p.horodatage",
            $params,
        );
        while (($ligne = $requete->fetch()) !== false) {
            yield $ligne;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourAgentEntre(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        return $this->lignes(
            'SELECT p.*, s.nom AS site FROM pointages p JOIN sites s ON s.id = p.site_id
             WHERE p.agent_id = :agent AND p.horodatage >= :du AND p.horodatage < :au
             ORDER BY p.horodatage',
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }

    /**
     * Vacations publiées commencées depuis le délai, sans aucun pointage d'arrivée.
     *
     * @return list<array<string, mixed>>
     */
    public function vacationsSansArrivee(DateTimeImmutable $debutMin, DateTimeImmutable $debutMax): array
    {
        return $this->lignes(
            "SELECT v.id, v.agent_id, v.site_id, v.debut, v.fin, a.nom, a.prenom, a.utilisateur_id, s.nom AS site
             FROM vacations v JOIN agents a ON a.id = v.agent_id JOIN sites s ON s.id = v.site_id
             WHERE v.statut = 'publiee' AND v.debut >= :debut_min AND v.debut <= :debut_max
               AND NOT EXISTS (
                   SELECT 1 FROM pointages p
                   WHERE p.agent_id = v.agent_id AND p.type = 'arrivee' AND p.statut NOT IN ('annule', 'rejete')
                     AND p.horodatage >= DATE_SUB(v.debut, INTERVAL 2 HOUR) AND p.horodatage <= v.fin
               )",
            ['debut_min' => Clock::sql($debutMin), 'debut_max' => Clock::sql($debutMax)],
        );
    }

    /**
     * Retards de l'agent sur une période : nombre et minutes au-delà du début prévu.
     *
     * @return array{nombre: int, minutes: int}
     */
    public function retards(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        $ligne = $this->ligne(
            "SELECT COUNT(*) AS nombre, COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE, v.debut, p.horodatage))), 0) AS minutes
             FROM pointages p JOIN vacations v ON v.id = p.vacation_id
             WHERE p.agent_id = :agent AND p.type = 'arrivee' AND FIND_IN_SET('retard', p.indicateurs) > 0
               AND p.statut NOT IN ('annule', 'rejete') AND p.horodatage >= :du AND p.horodatage < :au",
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );

        return ['nombre' => (int) ($ligne['nombre'] ?? 0), 'minutes' => (int) ($ligne['minutes'] ?? 0)];
    }

    /**
     * Vacations publiées et terminées sans aucune arrivée pointée.
     */
    public function absences(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au): int
    {
        return (int) $this->valeur(
            "SELECT COUNT(*) FROM vacations v
             WHERE v.agent_id = :agent AND v.statut = 'publiee' AND v.debut >= :du AND v.debut < :au AND v.fin < :maintenant
               AND NOT EXISTS (
                   SELECT 1 FROM pointages p WHERE p.agent_id = v.agent_id AND p.type = 'arrivee' AND p.statut NOT IN ('annule', 'rejete')
                     AND p.horodatage >= DATE_SUB(v.debut, INTERVAL 2 HOUR) AND p.horodatage <= v.fin
               )",
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au), 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function purgerGeolocalisationAvant(DateTimeImmutable $limite): int
    {
        return $this->executer(
            'UPDATE pointages SET latitude = NULL, longitude = NULL WHERE horodatage < :limite AND latitude IS NOT NULL',
            ['limite' => Clock::sql($limite)],
        )->rowCount();
    }
}
