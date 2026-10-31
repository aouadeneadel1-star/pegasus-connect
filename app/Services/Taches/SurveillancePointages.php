<?php

declare(strict_types=1);

namespace App\Services\Taches;

use App\Core\Clock;
use App\Repositories\AlerteRepository;
use App\Repositories\BorneRepository;
use App\Repositories\PointageRepository;
use App\Repositories\ServiceTravailRepository;
use App\Services\Destinataires;
use App\Services\Notifications;
use App\Services\Parametres;
use DateTimeImmutable;

/**
 * Absences, départs manquants et bornes muettes (tâche planifiée toutes les 5 minutes).
 */
final class SurveillancePointages
{
    public function __construct(
        private readonly PointageRepository $pointages,
        private readonly ServiceTravailRepository $services,
        private readonly BorneRepository $bornes,
        private readonly AlerteRepository $alertes,
        private readonly Parametres $parametres,
        private readonly Notifications $notifications,
        private readonly Destinataires $destinataires,
    ) {
    }

    /**
     * @return array{absences: int, departs_manquants: int, bornes: int}
     */
    public function executer(?DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= Clock::now();

        return [
            'absences' => $this->absences($maintenant),
            'departs_manquants' => $this->departsManquants($maintenant),
            'bornes' => $this->bornesMuettes($maintenant),
        ];
    }

    private function absences(DateTimeImmutable $maintenant): int
    {
        $delai = $this->parametres->int('pointage.delai_absence_min', 30);
        $nombre = 0;
        foreach ($this->pointages->vacationsSansArrivee($maintenant->modify('-12 hours'), $maintenant->modify('-' . $delai . ' minutes')) as $vacation) {
            if (!$this->alertes->reserver('absence', (string) $vacation['id'])) {
                continue;
            }
            ++$nombre;
            $heure = Clock::fromSql((string) $vacation['debut'])?->setTimezone(Clock::local())->format('H:i');
            $this->notifications->envoyer(
                $this->destinataires->pourSite((int) $vacation['site_id']),
                'absence',
                'Absence possible sur ' . $vacation['site'],
                sprintf('%s %s n\'a pas pointé son arrivée (vacation de %s).', $vacation['prenom'], $vacation['nom'], $heure),
                '/admin/pointages',
                'notification',
            );
            $this->notifications->envoyer(
                [(int) $vacation['utilisateur_id']],
                'absence_agent',
                'Arrivée non pointée',
                sprintf('Votre vacation sur %s a commencé à %s. Pointez votre arrivée ou prévenez votre chef d\'équipe.', $vacation['site'], $heure),
                '/pointer',
            );
        }

        return $nombre;
    }

    private function departsManquants(DateTimeImmutable $maintenant): int
    {
        $apresFin = $this->parametres->int('pointage.depart_manquant_apres_fin_h', 2);
        $sansVacation = $this->parametres->int('pointage.depart_manquant_sans_vacation_h', 14);
        $nombre = 0;

        foreach ($this->services->departsManquants($maintenant->modify('-' . $apresFin . ' hours'), $maintenant->modify('-' . $sansVacation . ' hours')) as $service) {
            $this->services->definirStatut((int) $service['id'], 'depart_manquant');
            ++$nombre;
            $this->notifications->envoyer(
                $this->destinataires->pourSite((int) $service['site_id']),
                'depart_manquant',
                'Départ non pointé sur ' . $service['site'],
                sprintf('Le service de %s %s commencé le %s n\'a pas de départ. Une correction est nécessaire.',
                    $service['prenom'], $service['nom'], Clock::fromSql((string) $service['debut'])?->setTimezone(Clock::local())->format('d/m à H:i')),
                '/admin/pointages?vue=services',
            );
            $this->notifications->envoyer(
                [(int) $service['utilisateur_id']],
                'depart_manquant_agent',
                'Départ non pointé',
                'Votre dernier service sur ' . $service['site'] . ' n\'a pas de départ. Votre chef d\'équipe va le corriger.',
                '/historique',
            );
        }

        return $nombre;
    }

    private function bornesMuettes(DateTimeImmutable $maintenant): int
    {
        $minutes = $this->parametres->int('borne.alerte_silence_min', 30);
        $nombre = 0;
        foreach ($this->bornes->silencieuses($maintenant->modify('-' . $minutes . ' minutes')) as $borne) {
            if (!$this->alertes->reserver('borne_muette', $borne['id'] . ':' . ($borne['derniere_activite'] ?? 'jamais'))) {
                continue;
            }
            ++$nombre;
            $this->notifications->envoyer(
                $this->destinataires->pourSite((int) $borne['site_id']),
                'borne_hors_ligne',
                'Borne hors ligne : ' . $borne['site_nom'],
                sprintf('La borne « %s » n\'a pas donné signe de vie depuis plus de %d minutes. Les agents peuvent pointer par badge hors ligne ou demander une correction.', $borne['libelle'], $minutes),
                '/admin/sites/' . $borne['site_id'] . '?onglet=bornes',
                'notification',
            );
        }

        return $nombre;
    }
}
