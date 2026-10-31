<?php

declare(strict_types=1);

namespace App\Services\Rapports;

use App\Core\Clock;
use App\Repositories\AgentRepository;
use App\Repositories\ConformiteRepository;
use App\Repositories\PointageRepository;
use App\Repositories\RapportRepository;
use App\Repositories\RondeRepository;
use App\Repositories\ServiceTravailRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Heures\HeuresService;
use App\Services\Notifications;
use App\Services\Parametres;
use App\Services\Pdf\GenerateurPdf;
use App\Services\Pointage\PointageService;
use App\Services\Stockage\Stockage;
use App\Services\ValidationException;
use PDO;
use Throwable;

/**
 * Rapport PDF mensuel d'un agent (cahier des charges, § 3.10) : heures planifiées et pointées,
 * majorations, retards, absences, rondes, détail des services. Le NIR n'y figure jamais.
 */
final class RapportMensuel
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RapportRepository $rapports,
        private readonly AgentRepository $agents,
        private readonly ConformiteRepository $conformite,
        private readonly ServiceTravailRepository $services,
        private readonly PointageRepository $pointages,
        private readonly RondeRepository $rondes,
        private readonly HeuresService $heures,
        private readonly GenerateurPdf $pdf,
        private readonly Stockage $stockage,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
    ) {
    }

    public function generer(int $agentId, int $annee, int $mois, int $auteurId): int
    {
        $agent = $this->agents->trouver($agentId) ?? throw ValidationException::champ('agent', 'Agent introuvable.');
        [$du, $au] = HeuresService::bornesMois($annee, $mois);
        if ($du > Clock::now()) {
            throw ValidationException::champ('mois', 'Ce mois n\'a pas commencé.');
        }

        $totaux = $this->heures->totauxMois([$agentId], $annee, $mois)[$agentId];
        $retards = $this->pointages->retards($agentId, $du, $au);
        $absences = $this->pointages->absences($agentId, $du, $au);
        $rondes = $this->rondes->totauxAgent($agentId, $du, $au);
        $carte = $this->conformite->carteProCourante($agentId);

        $rapportId = $this->rapports->creer([
            'agent_id' => $agentId,
            'annee' => $annee,
            'mois' => $mois,
            'minutes_planifiees' => $totaux['planifiees'],
            'minutes_pointees' => $totaux['travaillees'],
            'minutes_nuit' => $totaux['nuit'],
            'minutes_dimanche' => $totaux['dimanche'],
            'minutes_ferie' => $totaux['ferie'],
            'minutes_hs25' => $totaux['hs25'],
            'minutes_hs50' => $totaux['hs50'],
            'nb_retards' => $retards['nombre'],
            'minutes_retard' => $retards['minutes'],
            'nb_absences' => $absences,
            'nb_rondes' => $rondes['rondes'],
            'nb_points_controles' => $rondes['points'],
            'carte_pro_numero' => $carte['numero'] ?? null,
            'statut' => 'en_file',
            'genere_par' => $auteurId,
        ]);

        try {
            $services = array_map(static function (array $service): array {
                $service['indicateurs'] = array_values(array_unique([
                    ...PointageService::indicateurs($service['indicateurs_arrivee'] ?? null),
                    ...PointageService::indicateurs($service['indicateurs_depart'] ?? null),
                ]));
                $service['a_verifier'] = ($service['statut_arrivee'] ?? '') === 'a_verifier' || ($service['statut_depart'] ?? '') === 'a_verifier';

                return $service;
            }, $this->services->pourAgentEntre($agentId, $du, $au));

            $contenu = $this->pdf->rendre('rapport-mensuel', [
                'agent' => $agent,
                'annee' => $annee,
                'mois' => $mois,
                'totaux' => $totaux,
                'retards' => $retards,
                'absences' => $absences,
                'rondes' => $rondes,
                'carte' => $carte,
                'services' => $services,
                'societe' => (string) $this->parametres->get('societe.raison_sociale', 'Pegasus Insure'),
                'reference' => sprintf('RM-%04d%02d-%s', $annee, $mois, $agent['matricule']),
                'genereLe' => Clock::now(),
            ]);

            $documentId = $this->stockage->enregistrer(
                $contenu,
                sprintf('rapport-%04d-%02d-%s.pdf', $annee, $mois, strtolower((string) $agent['matricule'])),
                'application/pdf',
                'rapport_mensuel',
                $agentId,
                sprintf('Rapport mensuel %s %d', nom_mois($mois), $annee),
                $auteurId,
                ['visible_agent' => false, 'date_document' => $au->modify('-1 second')->setTimezone(Clock::local())->format('Y-m-d')],
            );
            $this->rapports->marquerGenere($rapportId, $documentId, hash('sha256', $contenu));
        } catch (Throwable $exception) {
            $this->rapports->marquerErreur($rapportId);
            throw $exception;
        }

        $this->audit->journaliser('rapport.generer', 'rapport_mensuel', $rapportId, ['agent' => $agentId, 'periode' => sprintf('%04d-%02d', $annee, $mois)]);

        return $rapportId;
    }

    public function envoyer(int $rapportId): void
    {
        $rapport = $this->rapports->trouver($rapportId) ?? throw ValidationException::champ('rapport', 'Rapport introuvable.');
        if ($rapport['document_id'] === null) {
            throw ValidationException::champ('rapport', 'Le rapport n\'a pas encore été généré.');
        }

        $this->pdo->prepare('UPDATE documents SET visible_agent = 1 WHERE id = :id')->execute(['id' => (int) $rapport['document_id']]);
        $this->rapports->marquerEnvoye($rapportId);
        $this->audit->journaliser('rapport.envoyer', 'rapport_mensuel', $rapportId);

        $this->notifications->envoyer(
            [(int) $rapport['utilisateur_id']],
            'rapport_mensuel',
            sprintf('Votre rapport d\'activité de %s %d est disponible', nom_mois((int) $rapport['mois']), (int) $rapport['annee']),
            null,
            '/documents',
            'notification',
        );
    }
}
