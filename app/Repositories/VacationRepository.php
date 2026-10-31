<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

final class VacationRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT v.*, s.nom AS site, a.nom, a.prenom, a.utilisateur_id
             FROM vacations v JOIN sites s ON s.id = v.site_id JOIN agents a ON a.id = v.agent_id
             WHERE v.id = :id',
            ['id' => $id],
        );
    }

    /**
     * Vacation publiée de l'agent sur ce site qui couvre l'instant, à ± marge près.
     *
     * @return array<string, mixed>|null
     */
    public function rapprocher(int $agentId, int $siteId, DateTimeImmutable $instant, int $margeHeures = 2): ?array
    {
        $avant = $instant->modify('+' . $margeHeures . ' hours');
        $apres = $instant->modify('-' . $margeHeures . ' hours');

        return $this->ligne(
            "SELECT * FROM vacations
             WHERE agent_id = :agent AND site_id = :site AND statut = 'publiee'
               AND debut <= :avant AND fin >= :apres
             ORDER BY ABS(TIMESTAMPDIFF(SECOND, debut, :instant)) LIMIT 1",
            [
                'agent' => $agentId,
                'site' => $siteId,
                'avant' => Clock::sql($avant),
                'apres' => Clock::sql($apres),
                'instant' => Clock::sql($instant),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourAgent(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au, bool $publieesSeulement = true): array
    {
        return $this->lignes(
            'SELECT v.*, s.nom AS site, s.adresse, s.ville, s.telephone_poste
             FROM vacations v JOIN sites s ON s.id = v.site_id
             WHERE v.agent_id = :agent AND v.fin > :du AND v.debut < :au AND v.statut ' . ($publieesSeulement ? "= 'publiee'" : "<> 'annulee'") . '
             ORDER BY v.debut',
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }

    /**
     * @return array<string, mixed>|null prochaine vacation publiée, ou celle en cours
     */
    public function prochaine(int $agentId): ?array
    {
        return $this->ligne(
            "SELECT v.*, s.nom AS site, s.adresse, s.ville FROM vacations v JOIN sites s ON s.id = v.site_id
             WHERE v.agent_id = :agent AND v.statut = 'publiee' AND v.fin > :maintenant
             ORDER BY v.debut LIMIT 1",
            ['agent' => $agentId, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    /**
     * @param list<int> $siteIds
     * @return list<array<string, mixed>>
     */
    public function pourSites(array $siteIds, DateTimeImmutable $du, DateTimeImmutable $au): array
    {
        [$condition, $params] = self::dans('v.site_id', $siteIds, 's');

        return $this->lignes(
            "SELECT v.*, s.nom AS site, a.nom, a.prenom, a.matricule
             FROM vacations v JOIN sites s ON s.id = v.site_id JOIN agents a ON a.id = v.agent_id
             WHERE $condition AND v.fin > :du AND v.debut < :au AND v.statut <> 'annulee'
             ORDER BY a.nom, a.prenom, v.debut",
            [...$params, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }

    /**
     * Vacations de l'agent (tous sites) sur une période, pour le contrôle des repos et plafonds.
     *
     * @return list<array{id: int, debut: DateTimeImmutable, fin: DateTimeImmutable}>
     */
    public function intervallesAgent(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au, ?int $saufId): array
    {
        $lignes = $this->lignes(
            "SELECT id, debut, fin FROM vacations
             WHERE agent_id = :agent AND statut <> 'annulee' AND fin > :du AND debut < :au AND id <> :sauf
             ORDER BY debut",
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au), 'sauf' => $saufId ?? 0],
        );

        return array_map(static fn (array $ligne): array => [
            'id' => (int) $ligne['id'],
            'debut' => Clock::fromSql((string) $ligne['debut']),
            'fin' => Clock::fromSql((string) $ligne['fin']),
        ], $lignes);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('vacations', [...$donnees, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('vacations', $id, $donnees);
    }

    public function annuler(int $id): void
    {
        $this->modifierLigne('vacations', $id, ['statut' => 'annulee']);
    }

    public function supprimerBrouillon(int $id): void
    {
        $this->executer("DELETE FROM vacations WHERE id = :id AND statut = 'brouillon'", ['id' => $id]);
    }

    /**
     * Publie les brouillons d'une période. Renvoie les agents concernés pour les prévenir.
     *
     * @param list<int> $siteIds
     * @return array{publication: int, vacations: int, agents: list<int>}
     */
    public function publier(array $siteIds, DateTimeImmutable $du, DateTimeImmutable $au, int $utilisateurId): array
    {
        [$condition, $params] = self::dans('site_id', $siteIds, 's');
        $params = [...$params, 'du' => Clock::sql($du), 'au' => Clock::sql($au)];

        $agents = $this->ids(
            "SELECT DISTINCT agent_id FROM vacations WHERE statut = 'brouillon' AND $condition AND debut >= :du AND debut < :au",
            $params,
        );
        $nombre = (int) $this->valeur(
            "SELECT COUNT(*) FROM vacations WHERE statut = 'brouillon' AND $condition AND debut >= :du AND debut < :au",
            $params,
        );
        if ($nombre === 0) {
            return ['publication' => 0, 'vacations' => 0, 'agents' => []];
        }

        $publication = $this->inserer('publications_planning', [
            'periode_debut' => $du->setTimezone(Clock::local())->format('Y-m-d'),
            'periode_fin' => $au->modify('-1 second')->setTimezone(Clock::local())->format('Y-m-d'),
            'perimetre' => json_encode(['sites' => $siteIds], JSON_THROW_ON_ERROR),
            'nb_vacations' => $nombre,
            'publiee_par' => $utilisateurId,
            'publiee_le' => Clock::sql(Clock::now()),
        ]);

        $this->executer(
            "UPDATE vacations SET statut = 'publiee', publication_id = :publication
             WHERE statut = 'brouillon' AND $condition AND debut >= :du AND debut < :au",
            [...$params, 'publication' => $publication],
        );

        return ['publication' => $publication, 'vacations' => $nombre, 'agents' => $agents];
    }

    public function nombreBrouillons(array $siteIds, DateTimeImmutable $du, DateTimeImmutable $au): int
    {
        [$condition, $params] = self::dans('site_id', $siteIds, 's');

        return (int) $this->valeur(
            "SELECT COUNT(*) FROM vacations WHERE statut = 'brouillon' AND $condition AND debut >= :du AND debut < :au",
            [...$params, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }

    /**
     * Minutes planifiées par agent sur un intervalle (rapport mensuel).
     */
    public function minutesPlanifiees(int $agentId, DateTimeImmutable $du, DateTimeImmutable $au): int
    {
        return (int) $this->valeur(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, debut, fin) - pause_minutes), 0) FROM vacations
             WHERE agent_id = :agent AND statut = 'publiee' AND debut >= :du AND debut < :au",
            ['agent' => $agentId, 'du' => Clock::sql($du), 'au' => Clock::sql($au)],
        );
    }
}
