<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Core\Clock;
use App\Repositories\AgentRepository;
use App\Repositories\ConformiteRepository;
use App\Repositories\SiteRepository;
use App\Repositories\VacationRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications;
use App\Services\Parametres;
use App\Services\ValidationException;
use DateTimeImmutable;

/**
 * Saisie et publication des vacations, avec contrôle des durées maximales et des repos
 * (cahier des charges, § 3.6). Les dépassements peuvent être forcés avec un motif ;
 * un chevauchement ne le peut pas.
 */
final class PlanningService
{
    public function __construct(
        private readonly VacationRepository $vacations,
        private readonly AgentRepository $agents,
        private readonly SiteRepository $sites,
        private readonly ConformiteRepository $conformite,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
    ) {
    }

    /**
     * Contrôles d'une vacation projetée. Renvoie les avertissements forçables ;
     * lève une exception pour les erreurs bloquantes.
     *
     * @return list<string>
     */
    public function controler(int $agentId, int $siteId, DateTimeImmutable $debut, DateTimeImmutable $fin, int $pause, ?int $saufId): array
    {
        if ($fin <= $debut) {
            throw ValidationException::champ('fin', 'La fin doit suivre le début.');
        }
        $minutes = intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60);
        if ($minutes > 24 * 60) {
            throw ValidationException::champ('fin', 'Une vacation ne peut pas dépasser 24 heures.');
        }
        if ($pause < 0 || $pause >= $minutes) {
            throw ValidationException::champ('pause_minutes', 'La pause doit être inférieure à la durée de la vacation.');
        }

        $semaine = self::debutSemaine($debut);
        $voisines = $this->vacations->intervallesAgent($agentId, $semaine->modify('-2 days'), $semaine->modify('+9 days'), $saufId);
        foreach ($voisines as $autre) {
            if ($autre['debut'] < $fin && $autre['fin'] > $debut) {
                throw ValidationException::champ('debut', sprintf(
                    'Cette vacation chevauche une autre vacation de l\'agent (%s – %s).',
                    $autre['debut']->setTimezone(Clock::local())->format('d/m H:i'),
                    $autre['fin']->setTimezone(Clock::local())->format('H:i'),
                ));
            }
        }

        $avertissements = [];
        $maxJour = $this->parametres->int('planning.max_jour_h', 12);
        if ($minutes - $pause > $maxJour * 60) {
            $avertissements[] = sprintf('Durée de travail supérieure à %d h.', $maxJour);
        }

        $reposMin = $this->parametres->int('planning.repos_quotidien_min_h', 11) * 3600;
        foreach ($voisines as $autre) {
            $ecart = $autre['fin'] <= $debut
                ? $debut->getTimestamp() - $autre['fin']->getTimestamp()
                : $autre['debut']->getTimestamp() - $fin->getTimestamp();
            if ($ecart >= 0 && $ecart < $reposMin) {
                $avertissements[] = sprintf('Repos de %s seulement avec la vacation du %s (minimum %d h).',
                    duree(intdiv($ecart, 60)), $autre['debut']->setTimezone(Clock::local())->format('d/m'), intdiv($reposMin, 3600));
                break;
            }
        }

        $finSemaine = $semaine->modify('+7 days');
        $total = 0;
        foreach ([...$voisines, ['debut' => $debut, 'fin' => $fin]] as $intervalle) {
            $chevauchement = min($intervalle['fin']->getTimestamp(), $finSemaine->getTimestamp()) - max($intervalle['debut']->getTimestamp(), $semaine->getTimestamp());
            $total += max(0, $chevauchement);
        }
        $maxSemaine = $this->parametres->int('planning.max_semaine_h', 48);
        if ($total > $maxSemaine * 3600) {
            $avertissements[] = sprintf('%s planifiées sur la semaine (maximum %d h).', duree(intdiv($total, 60)), $maxSemaine);
        }

        if (!$this->agents->estAffecte($agentId, $siteId)) {
            $avertissements[] = 'L\'agent n\'est pas affecté à ce site.';
        }

        $jour = $debut->setTimezone(Clock::local())->format('Y-m-d');
        if ($this->conformite->carteProValideLe($agentId, $jour) !== true) {
            $avertissements[] = 'La carte professionnelle de l\'agent ne sera pas valide à cette date.';
        }

        $site = $this->sites->trouver($siteId);
        $requises = json_decode((string) ($site['qualifications_requises'] ?? '[]'), true) ?: [];
        if ($requises !== []) {
            $codes = [];
            foreach ($this->conformite->typesQualification() as $type) {
                $codes[(int) $type['id']] = (string) $type['code'];
            }
            $codesRequis = array_values(array_intersect_key($codes, array_flip(array_map('intval', $requises))));
            if (array_diff($codesRequis, $this->conformite->qualificationsValides($agentId, $jour)) !== []) {
                $avertissements[] = 'L\'agent ne détient pas toutes les qualifications requises sur ce site.';
            }
        }

        return $avertissements;
    }

    /**
     * @param array{agent_id: int, site_id: int, debut: DateTimeImmutable, fin: DateTimeImmutable, pause_minutes: int, poste: ?string, commentaire: ?string} $saisie
     * @return array{id: int, avertissements: list<string>}
     */
    public function enregistrer(array $saisie, ?int $vacationId, int $auteurId, bool $forcer, ?string $motif): array
    {
        $avertissements = $this->controler($saisie['agent_id'], $saisie['site_id'], $saisie['debut'], $saisie['fin'], $saisie['pause_minutes'], $vacationId);
        if ($avertissements !== [] && (!$forcer || trim((string) $motif) === '')) {
            throw new ValidationException(['avertissements' => implode("\n", $avertissements)]);
        }

        $donnees = [
            'agent_id' => $saisie['agent_id'],
            'site_id' => $saisie['site_id'],
            'debut' => Clock::sql($saisie['debut']),
            'fin' => Clock::sql($saisie['fin']),
            'pause_minutes' => $saisie['pause_minutes'],
            'poste' => $saisie['poste'],
            'commentaire' => $saisie['commentaire'],
            'force_motif' => $avertissements === [] ? null : mb_substr((string) $motif, 0, 255),
        ];

        if ($vacationId === null) {
            $id = $this->vacations->creer([...$donnees, 'statut' => 'brouillon', 'created_by' => $auteurId]);
            $this->audit->journaliser('vacation.creer', 'vacation', $id, ['force' => $avertissements !== []]);

            return ['id' => $id, 'avertissements' => $avertissements];
        }

        $avant = $this->vacations->trouver($vacationId);
        $this->vacations->modifier($vacationId, $donnees);
        $this->audit->journaliser('vacation.modifier', 'vacation', $vacationId, ['force' => $avertissements !== []]);

        if ($avant !== null && $avant['statut'] === 'publiee') {
            $this->prevenirModification($avant, $saisie['debut']);
        }

        return ['id' => $vacationId, 'avertissements' => $avertissements];
    }

    public function supprimer(int $vacationId): void
    {
        $vacation = $this->vacations->trouver($vacationId);
        if ($vacation === null) {
            return;
        }

        if ($vacation['statut'] === 'brouillon') {
            $this->vacations->supprimerBrouillon($vacationId);
        } else {
            $this->vacations->annuler($vacationId);
            $this->prevenirModification($vacation, Clock::fromSql((string) $vacation['debut']));
        }
        $this->audit->journaliser('vacation.supprimer', 'vacation', $vacationId, ['statut' => $vacation['statut']]);
    }

    /**
     * @param list<int> $siteIds
     * @return array{vacations: int, agents: int}
     */
    public function publier(array $siteIds, DateTimeImmutable $du, DateTimeImmutable $au, int $auteurId): array
    {
        $resultat = $this->vacations->publier($siteIds, $du, $au, $auteurId);
        if ($resultat['vacations'] === 0) {
            return ['vacations' => 0, 'agents' => 0];
        }

        $this->audit->journaliser('planning.publier', 'publication', $resultat['publication'], ['vacations' => $resultat['vacations']]);
        $this->notifications->envoyer(
            $this->agents->utilisateursDe($resultat['agents']),
            'planning_publie',
            'Votre planning est disponible',
            sprintf('Le planning à partir du %s a été publié.', $du->setTimezone(Clock::local())->format('d/m/Y')),
            '/planning?date=' . $du->setTimezone(Clock::local())->format('Y-m-d'),
            'notification',
        );

        return ['vacations' => $resultat['vacations'], 'agents' => count($resultat['agents'])];
    }

    /**
     * Copie les vacations d'une semaine sur la suivante, en brouillon. Les vacations qui
     * entreraient en conflit avec une vacation existante ne sont pas copiées.
     *
     * @param list<int> $siteIds
     * @return array{copiees: int, ignorees: int}
     */
    public function dupliquerSemaine(array $siteIds, DateTimeImmutable $semaine, int $auteurId): array
    {
        $copiees = 0;
        $ignorees = 0;
        foreach ($this->vacations->pourSites($siteIds, $semaine, $semaine->modify('+7 days')) as $vacation) {
            $debut = Clock::fromSql((string) $vacation['debut'])->setTimezone(Clock::local())->modify('+7 days')->setTimezone(Clock::utc());
            $fin = Clock::fromSql((string) $vacation['fin'])->setTimezone(Clock::local())->modify('+7 days')->setTimezone(Clock::utc());
            try {
                $avertissements = $this->controler((int) $vacation['agent_id'], (int) $vacation['site_id'], $debut, $fin, (int) $vacation['pause_minutes'], null);
            } catch (ValidationException) {
                ++$ignorees;
                continue;
            }

            $this->vacations->creer([
                'agent_id' => (int) $vacation['agent_id'],
                'site_id' => (int) $vacation['site_id'],
                'debut' => Clock::sql($debut),
                'fin' => Clock::sql($fin),
                'pause_minutes' => (int) $vacation['pause_minutes'],
                'poste' => $vacation['poste'],
                'commentaire' => $vacation['commentaire'],
                'force_motif' => $avertissements === [] ? null : 'Copie de la semaine précédente',
                'statut' => 'brouillon',
                'created_by' => $auteurId,
            ]);
            ++$copiees;
        }

        $this->audit->journaliser('planning.dupliquer', null, null, ['semaine' => $semaine->format('Y-m-d'), 'copiees' => $copiees]);

        return ['copiees' => $copiees, 'ignorees' => $ignorees];
    }

    /**
     * Lundi 00:00 (heure locale) de la semaine contenant l'instant, exprimé en UTC.
     */
    public static function debutSemaine(DateTimeImmutable $instant): DateTimeImmutable
    {
        $local = $instant->setTimezone(Clock::local());

        return $local->modify('-' . ((int) $local->format('N') - 1) . ' days')->setTime(0, 0)->setTimezone(Clock::utc());
    }

    /**
     * @param array<string, mixed> $vacation
     */
    private function prevenirModification(array $vacation, ?DateTimeImmutable $debut): void
    {
        $this->notifications->envoyer(
            [(int) $vacation['utilisateur_id']],
            'planning_modifie',
            'Votre planning a changé',
            'Une vacation sur ' . $vacation['site'] . ' a été modifiée ou annulée.',
            '/planning?date=' . ($debut ?? Clock::now())->setTimezone(Clock::local())->format('Y-m-d'),
            'notification',
        );
    }
}
