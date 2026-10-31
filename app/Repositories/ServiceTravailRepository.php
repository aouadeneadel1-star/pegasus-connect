<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;
use DateTimeImmutable;

/**
 * Services effectués : une arrivée et, une fois l'agent reparti, un départ.
 * Un seul service ouvert par agent, garanti par un index unique conditionnel.
 */
final class ServiceTravailRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT sv.*, s.nom AS site, a.nom, a.prenom, a.matricule
             FROM services sv JOIN sites s ON s.id = sv.site_id JOIN agents a ON a.id = sv.agent_id
             WHERE sv.id = :id',
            ['id' => $id],
        );
    }

    /**
     * Verrouille la ligne pendant la transaction d'un pointage pour sérialiser deux scans simultanés.
     *
     * @return array<string, mixed>|null
     */
    public function ouvertPourAgent(int $agentId, bool $verrouiller = false): ?array
    {
        return $this->ligne(
            "SELECT sv.*, s.nom AS site FROM services sv JOIN sites s ON s.id = sv.site_id
             WHERE sv.agent_id = :agent AND sv.statut = 'ouvert'" . ($verrouiller ? ' FOR UPDATE' : ''),
            ['agent' => $agentId],
        );
    }

    public function ouvrir(int $agentId, int $siteId, ?int $vacationId, int $pointageId, DateTimeImmutable $debut): int
    {
        return $this->inserer('services', [
            'agent_id' => $agentId,
            'site_id' => $siteId,
            'vacation_id' => $vacationId,
            'pointage_arrivee_id' => $pointageId,
            'debut' => Clock::sql($debut, true),
            'statut' => 'ouvert',
        ]);
    }

    public function clore(int $id, int $pointageDepartId, DateTimeImmutable $fin, string $statut = 'clos'): void
    {
        $debut = Clock::fromSql((string) $this->valeur('SELECT debut FROM services WHERE id = :id', ['id' => $id]));
        $minutes = $debut === null ? 0 : max(0, intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60));

        $this->modifierLigne('services', $id, [
            'pointage_depart_id' => $pointageDepartId,
            'fin' => Clock::sql($fin, true),
            'duree_minutes' => $minutes,
            'statut' => $statut,
        ]);
    }

    /**
     * Annulation d'un départ : le service redevient ouvert.
     */
    public function rouvrir(int $id): void
    {
        $this->modifierLigne('services', $id, ['pointage_depart_id' => null, 'fin' => null, 'duree_minutes' => null, 'statut' => 'ouvert']);
    }

    /**
     * Départ rejeté : le service reste sans fin, hors des heures, jusqu'à correction.
     */
    public function retirerDepart(int $id): void
    {
        $this->modifierLigne('services', $id, ['pointage_depart_id' => null, 'fin' => null, 'duree_minutes' => null, 'statut' => 'depart_manquant']);
    }

    public function definirStatut(int $id, string $statut): void
    {
        $this->modifierLigne('services', $id, ['statut' => $statut]);
    }

    /**
     * Correction manuelle des horaires d'un service.
     */
    public function corriger(int $id, DateTimeImmutable $debut, ?DateTimeImmutable $fin): void
    {
        $this->modifierLigne('services', $id, [
            'debut' => Clock::sql($debut, true),
            'fin' => $fin === null ? null : Clock::sql($fin, true),
            'duree_minutes' => $fin === null ? null : max(0, intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60)),
            'statut' => $fin === null ? 'ouvert' : 'corrige',
        ]);
    }

    public function parPointage(int $pointageId): ?array
    {
        return $this->ligne(
            'SELECT * FROM services WHERE pointage_arrivee_id = :arrivee OR pointage_depart_id = :depart',
            ['arrivee' => $pointageId, 'depart' => $pointageId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentsPourAgent(int $agentId, int $limite): array
    {
        return $this->lignes(
            'SELECT sv.*, s.nom AS site FROM services sv JOIN sites s ON s.id = sv.site_id
             WHERE sv.agent_id = :agent ORDER BY sv.debut DESC LIMIT ' . max(1, $limite),
            ['agent' => $agentId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourAgentEntre(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        return $this->lignes(
            "SELECT sv.*, s.nom AS site,
                    pa.statut AS statut_arrivee, pa.indicateurs AS indicateurs_arrivee, pa.mode AS mode_arrivee,
                    pd.statut AS statut_depart, pd.indicateurs AS indicateurs_depart, pd.mode AS mode_depart
             FROM services sv
             JOIN sites s ON s.id = sv.site_id
             JOIN pointages pa ON pa.id = sv.pointage_arrivee_id
             LEFT JOIN pointages pd ON pd.id = sv.pointage_depart_id
             WHERE sv.agent_id = :agent AND sv.debut >= :du AND sv.debut < :au AND sv.statut <> 'annule'
             ORDER BY sv.debut",
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }

    /**
     * Services comptés dans les heures d'un mois, rattachés au mois de leur début.
     *
     * @param list<int>|null $agentIds
     * @return list<array<string, mixed>>
     */
    public function comptabilisables(DateTimeImmutable $du, DateTimeImmutable $au, ?array $agentIds = null): array
    {
        $params = ['du' => Clock::sql($du), 'au' => Clock::sql($au)];
        $filtre = '';
        if ($agentIds !== null) {
            [$condition, $paramsAgents] = self::dans('sv.agent_id', $agentIds, 'ag');
            $filtre = ' AND ' . $condition;
            $params = [...$params, ...$paramsAgents];
        }

        return $this->lignes(
            "SELECT sv.id, sv.agent_id, sv.site_id, sv.vacation_id, sv.debut, sv.fin, sv.duree_minutes, sv.statut,
                    v.pause_minutes,
                    (pa.statut = 'a_verifier' OR pd.statut = 'a_verifier') AS a_verifier
             FROM services sv
             JOIN pointages pa ON pa.id = sv.pointage_arrivee_id
             LEFT JOIN pointages pd ON pd.id = sv.pointage_depart_id
             LEFT JOIN vacations v ON v.id = sv.vacation_id
             WHERE sv.debut >= :du AND sv.debut < :au AND sv.fin IS NOT NULL AND sv.statut IN ('clos', 'corrige')" . $filtre . '
             ORDER BY sv.agent_id, sv.debut',
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>> services en cours, pour le suivi du jour
     */
    public function enCours(Perimetre $perimetre): array
    {
        [$condSite, $params] = $perimetre->sqlSites('sv.site_id');

        return $this->lignes(
            "SELECT sv.id, sv.agent_id, sv.site_id, sv.debut, sv.vacation_id, s.nom AS site, a.nom, a.prenom, a.matricule,
                    v.fin AS fin_prevue
             FROM services sv JOIN sites s ON s.id = sv.site_id JOIN agents a ON a.id = sv.agent_id
             LEFT JOIN vacations v ON v.id = sv.vacation_id
             WHERE sv.statut = 'ouvert' AND $condSite
             ORDER BY s.nom, a.nom",
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>> agents en service sur un site
     */
    public function presentsSurSite(int $siteId): array
    {
        return $this->lignes(
            "SELECT sv.id, sv.debut, a.nom, a.prenom FROM services sv JOIN agents a ON a.id = sv.agent_id
             WHERE sv.site_id = :site AND sv.statut = 'ouvert' ORDER BY sv.debut",
            ['site' => $siteId],
        );
    }

    /**
     * Services ouverts trop longtemps : 2 h après la fin de la vacation, ou 14 h sans vacation.
     *
     * @return list<array<string, mixed>>
     */
    public function departsManquants(DateTimeImmutable $limiteVacation, DateTimeImmutable $limiteSansVacation): array
    {
        return $this->lignes(
            "SELECT sv.id, sv.agent_id, sv.site_id, sv.debut, a.nom, a.prenom, a.utilisateur_id, s.nom AS site
             FROM services sv JOIN agents a ON a.id = sv.agent_id JOIN sites s ON s.id = sv.site_id
             LEFT JOIN vacations v ON v.id = sv.vacation_id
             WHERE sv.statut = 'ouvert'
               AND ((v.id IS NOT NULL AND v.fin < :limite_vacation) OR (v.id IS NULL AND sv.debut < :limite_sans))",
            ['limite_vacation' => Clock::sql($limiteVacation), 'limite_sans' => Clock::sql($limiteSansVacation)],
        );
    }
}
