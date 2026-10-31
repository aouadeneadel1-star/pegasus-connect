<?php

declare(strict_types=1);

namespace App\Services\Taches;

use App\Core\Clock;
use App\Models\Perimetre;
use App\Repositories\AgentRepository;
use App\Repositories\ConformiteRepository;
use App\Services\Agents\Echeance;
use App\Services\Destinataires;
use App\Services\Notifications;
use App\Services\Parametres;

/**
 * Alertes d'expiration des cartes professionnelles et des qualifications aux seuils
 * J-90, J-30, J-7 et à l'échéance (tâche quotidienne). Une alerte n'est émise qu'une fois par seuil.
 */
final class AlertesEcheances
{
    public function __construct(
        private readonly ConformiteRepository $conformite,
        private readonly AgentRepository $agents,
        private readonly Parametres $parametres,
        private readonly Notifications $notifications,
        private readonly Destinataires $destinataires,
    ) {
    }

    public function executer(): int
    {
        $seuils = array_map('intval', (array) $this->parametres->get('echeances.seuils_jours', [90, 30, 7, 0]));
        sort($seuils);
        $horizon = max($seuils);
        $limite = Clock::now()->setTimezone(Clock::local())->modify('+' . $horizon . ' days')->format('Y-m-d');

        $emises = [];
        foreach ($this->conformite->echeances(Perimetre::tout(), $limite) as $echeance) {
            $jours = Echeance::statut((string) $echeance['echeance'])['jours'] ?? null;
            if ($jours === null) {
                continue;
            }

            // Seuil le plus proche déjà franchi : 25 jours restants → seuil 30.
            $seuil = null;
            foreach ($seuils as $candidat) {
                if ($jours <= $candidat) {
                    $seuil = $candidat;
                    break;
                }
            }
            if ($seuil === null || $this->conformite->alerteDejaEnvoyee((string) $echeance['objet_type'], (int) $echeance['objet_id'], (string) $echeance['echeance'], $seuil)) {
                continue;
            }

            $this->conformite->enregistrerAlerte((string) $echeance['objet_type'], (int) $echeance['objet_id'], (string) $echeance['echeance'], $seuil);
            $emises[] = $echeance;

            $utilisateurId = $this->agents->utilisateurDe((int) $echeance['agent_id']);
            if ($utilisateurId !== null) {
                $this->notifications->envoyer(
                    [$utilisateurId],
                    'echeance',
                    $jours < 0 ? $echeance['libelle'] . ' expirée' : $echeance['libelle'] . ' : échéance dans ' . $jours . ' jours',
                    sprintf('Date d\'échéance : %s. Transmettez le justificatif de renouvellement au service RH.', date_courte((string) $echeance['echeance'])),
                    '/profil',
                    'notification',
                );
            }
        }

        if ($emises !== []) {
            $expirees = count(array_filter($emises, static fn (array $e): bool => (string) $e['echeance'] < Clock::now()->setTimezone(Clock::local())->format('Y-m-d')));
            $this->notifications->envoyer(
                $this->destinataires->rh(),
                'echeances_rh',
                sprintf('%d échéance(s) à traiter', count($emises)),
                $expirees > 0 ? sprintf('Dont %d déjà expirée(s).', $expirees) : 'Cartes professionnelles et recyclages à anticiper.',
                '/admin/echeances',
                'notification',
            );
        }

        return count($emises);
    }
}
