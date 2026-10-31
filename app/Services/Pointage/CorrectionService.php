<?php

declare(strict_types=1);

namespace App\Services\Pointage;

use App\Core\Clock;
use App\Core\Uuid;
use App\Models\UtilisateurConnecte;
use App\Repositories\CorrectionRepository;
use App\Repositories\MajorationRepository;
use App\Repositories\PointageRepository;
use App\Repositories\ServiceTravailRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Destinataires;
use App\Services\Notifications;
use App\Services\Parametres;
use App\Services\ValidationException;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Corrections de pointage (cahier des charges, § 3.11). Le chef d'équipe propose, le service RH
 * applique ; le paramètre corrections.validation_directe_chef permet au chef d'appliquer lui-même.
 * Rien n'est supprimé : chaque correction garde l'état avant et après.
 */
final class CorrectionService
{
    public const MOTIFS = [
        'oubli' => 'Oubli de pointage',
        'panne_borne' => 'Borne ou réseau en panne',
        'erreur_type' => 'Erreur arrivée / départ',
        'telephone' => 'Téléphone indisponible',
        'verification' => 'Contrôle effectué',
        'fraude' => 'Pointage non conforme',
        'autre' => 'Autre',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CorrectionRepository $corrections,
        private readonly PointageRepository $pointages,
        private readonly ServiceTravailRepository $services,
        private readonly MajorationRepository $periodes,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
        private readonly Destinataires $destinataires,
    ) {
    }

    public function peutAppliquer(UtilisateurConnecte $utilisateur): bool
    {
        return $utilisateur->peut('pointages.corriger.valider')
            || ($utilisateur->peut('pointages.corriger.proposer') && $this->parametres->bool('corrections.validation_directe_chef', false));
    }

    /**
     * Valide ou rejette un pointage de la file « à vérifier ».
     */
    public function statuer(UtilisateurConnecte $utilisateur, int $pointageId, bool $valide, string $motifCode, string $motifTexte): string
    {
        $pointage = $this->pointage($pointageId);
        if ($pointage['statut'] !== 'a_verifier') {
            throw ValidationException::champ('pointage', 'Ce pointage a déjà été traité.');
        }

        return $this->soumettre($utilisateur, $valide ? 'validation' : 'annulation', $pointage, null, [
            'pointage_id' => $pointageId,
            'agent_id' => (int) $pointage['agent_id'],
            'site_id' => (int) $pointage['site_id'],
            'statut' => $valide ? 'valide' : 'rejete',
        ], $motifCode, $motifTexte);
    }

    /**
     * Change l'heure d'un pointage existant.
     */
    public function modifierHeure(UtilisateurConnecte $utilisateur, int $pointageId, DateTimeImmutable $horodatage, string $motifCode, string $motifTexte): string
    {
        $pointage = $this->pointage($pointageId);
        if (in_array($pointage['statut'], ['annule', 'rejete'], true)) {
            throw ValidationException::champ('pointage', 'Ce pointage est annulé.');
        }
        $service = $this->services->parPointage($pointageId);

        return $this->soumettre($utilisateur, 'modification', $pointage, $service === null ? null : (int) $service['id'], [
            'pointage_id' => $pointageId,
            'agent_id' => (int) $pointage['agent_id'],
            'site_id' => (int) $pointage['site_id'],
            'horodatage' => Clock::sql($horodatage),
        ], $motifCode, $motifTexte);
    }

    /**
     * Ajoute le départ oublié d'un service resté ouvert ou classé « départ manquant ».
     */
    public function ajouterDepart(UtilisateurConnecte $utilisateur, int $serviceId, DateTimeImmutable $horodatage, string $motifCode, string $motifTexte): string
    {
        $service = $this->services->trouver($serviceId) ?? throw ValidationException::champ('service', 'Service introuvable.');
        if ($service['pointage_depart_id'] !== null) {
            throw ValidationException::champ('service', 'Ce service a déjà un départ.');
        }
        if ($horodatage <= Clock::fromSql((string) $service['debut'])) {
            throw ValidationException::champ('horodatage', 'Le départ doit suivre l\'arrivée.');
        }

        return $this->soumettre($utilisateur, 'ajout', $service, $serviceId, [
            'service_id' => $serviceId,
            'agent_id' => (int) $service['agent_id'],
            'site_id' => (int) $service['site_id'],
            'type' => 'depart',
            'horodatage' => Clock::sql($horodatage),
        ], $motifCode, $motifTexte);
    }

    /**
     * Crée un service complet pour un agent qui n'a pas pu pointer du tout.
     */
    public function ajouterService(
        UtilisateurConnecte $utilisateur,
        int $agentId,
        int $siteId,
        DateTimeImmutable $debut,
        DateTimeImmutable $fin,
        string $motifCode,
        string $motifTexte,
    ): string {
        if ($fin <= $debut) {
            throw ValidationException::champ('fin', 'La fin doit suivre le début.');
        }

        return $this->soumettre($utilisateur, 'ajout', null, null, [
            'agent_id' => $agentId,
            'site_id' => $siteId,
            'type' => 'service',
            'debut' => Clock::sql($debut),
            'fin' => Clock::sql($fin),
        ], $motifCode, $motifTexte);
    }

    public function valider(UtilisateurConnecte $utilisateur, int $correctionId): void
    {
        $correction = $this->enAttente($correctionId);
        $this->pdo->beginTransaction();
        try {
            $this->appliquer($correction['action'], json_decode((string) $correction['apres'], true, 512, JSON_THROW_ON_ERROR));
            $this->corrections->decider($correctionId, 'appliquee', $utilisateur->id);
            $this->audit->journaliser('correction.valider', 'correction', $correctionId);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->notifications->envoyer([(int) $correction['demandee_par']], 'correction_validee', 'Correction de pointage appliquée', null, '/admin/pointages');
    }

    public function refuser(UtilisateurConnecte $utilisateur, int $correctionId): void
    {
        $correction = $this->enAttente($correctionId);
        $this->corrections->decider($correctionId, 'refusee', $utilisateur->id);
        $this->audit->journaliser('correction.refuser', 'correction', $correctionId);
        $this->notifications->envoyer([(int) $correction['demandee_par']], 'correction_refusee', 'Correction de pointage refusée', null, '/admin/pointages');
    }

    /**
     * @param array<string, mixed>|null $avant
     * @param array<string, mixed> $apres
     */
    private function soumettre(UtilisateurConnecte $utilisateur, string $action, ?array $avant, ?int $serviceId, array $apres, string $motifCode, string $motifTexte): string
    {
        if (!array_key_exists($motifCode, self::MOTIFS)) {
            throw ValidationException::champ('motif_code', 'Choisissez un motif.');
        }
        if (trim($motifTexte) === '') {
            throw ValidationException::champ('motif_texte', 'Précisez le motif de la correction.');
        }
        $this->verifierPeriodeOuverte((string) ($apres['horodatage'] ?? $apres['debut'] ?? $avant['horodatage'] ?? $avant['debut'] ?? Clock::sql(Clock::now())));

        $direct = $this->peutAppliquer($utilisateur);
        $this->pdo->beginTransaction();
        try {
            if ($direct) {
                $this->appliquer($action, $apres);
            }
            $id = $this->corrections->creer([
                'pointage_id' => $apres['pointage_id'] ?? null,
                'service_id' => $serviceId ?? ($apres['service_id'] ?? null),
                'action' => $action,
                'avant' => $avant === null ? null : json_encode(self::resume($avant), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'apres' => json_encode($apres, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'motif_code' => $motifCode,
                'motif_texte' => mb_substr(trim($motifTexte), 0, 255),
                'statut' => $direct ? 'appliquee' : 'proposee',
                'demandee_par' => $utilisateur->id,
                'validee_par' => $direct ? $utilisateur->id : null,
                'validee_le' => $direct ? Clock::sql(Clock::now()) : null,
            ]);
            $this->audit->journaliser($direct ? 'correction.appliquer' : 'correction.proposer', 'correction', $id, ['action' => $action, 'motif' => $motifCode]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        if (!$direct) {
            $this->notifications->envoyer(
                $this->destinataires->rh(),
                'correction_proposee',
                'Correction de pointage à valider',
                $utilisateur->nomComplet . ' propose une correction : ' . self::MOTIFS[$motifCode] . '.',
                '/admin/pointages?vue=corrections',
            );
        }

        return $direct ? 'appliquee' : 'proposee';
    }

    /**
     * @param array<string, mixed> $apres
     */
    private function appliquer(string $action, array $apres): void
    {
        match ($action) {
            'validation', 'annulation' => $this->appliquerStatut((int) $apres['pointage_id'], (string) $apres['statut']),
            'modification' => $this->appliquerHeure((int) $apres['pointage_id'], Clock::fromSql((string) $apres['horodatage'])),
            'ajout' => $apres['type'] === 'depart'
                ? $this->appliquerDepart((int) $apres['service_id'], Clock::fromSql((string) $apres['horodatage']))
                : $this->appliquerService((int) $apres['agent_id'], (int) $apres['site_id'], Clock::fromSql((string) $apres['debut']), Clock::fromSql((string) $apres['fin'])),
            default => throw ValidationException::champ('action', 'Action inconnue.'),
        };
    }

    private function appliquerStatut(int $pointageId, string $statut): void
    {
        $pointage = $this->pointage($pointageId);
        $this->pointages->definirStatut($pointageId, $statut);
        if ($statut !== 'rejete') {
            return;
        }

        $service = $this->services->parPointage($pointageId);
        if ($service === null) {
            return;
        }
        // Une arrivée rejetée annule le service ; un départ rejeté le laisse sans départ.
        if ($pointage['type'] === 'arrivee') {
            $this->services->definirStatut((int) $service['id'], 'annule');
        } else {
            $this->services->retirerDepart((int) $service['id']);
        }
    }

    private function appliquerHeure(int $pointageId, DateTimeImmutable $horodatage): void
    {
        $pointage = $this->pointage($pointageId);
        $this->pdo->prepare('UPDATE pointages SET horodatage = :horodatage, statut = :statut WHERE id = :id')
            ->execute(['horodatage' => Clock::sql($horodatage, true), 'statut' => 'corrige', 'id' => $pointageId]);

        $service = $this->services->parPointage($pointageId);
        if ($service === null) {
            return;
        }
        $debut = $pointage['type'] === 'arrivee' ? $horodatage : Clock::fromSql((string) $service['debut']);
        $fin = $pointage['type'] === 'depart' ? $horodatage : Clock::fromSql($service['fin'] === null ? null : (string) $service['fin']);
        if ($fin !== null && $fin <= $debut) {
            throw ValidationException::champ('horodatage', 'Le départ doit suivre l\'arrivée.');
        }
        $this->services->corriger((int) $service['id'], $debut, $fin);
    }

    private function appliquerDepart(int $serviceId, DateTimeImmutable $horodatage): void
    {
        $service = $this->services->trouver($serviceId) ?? throw ValidationException::champ('service', 'Service introuvable.');
        $pointageId = $this->pointages->creer($this->pointageManuel((int) $service['agent_id'], (int) $service['site_id'], 'depart', $horodatage, $service['vacation_id'] === null ? null : (int) $service['vacation_id']));
        $this->services->clore($serviceId, $pointageId, $horodatage, 'corrige');
    }

    private function appliquerService(int $agentId, int $siteId, DateTimeImmutable $debut, DateTimeImmutable $fin): void
    {
        $arrivee = $this->pointages->creer($this->pointageManuel($agentId, $siteId, 'arrivee', $debut, null));
        $depart = $this->pointages->creer($this->pointageManuel($agentId, $siteId, 'depart', $fin, null));

        // Le service est inséré directement clos : l'index « un seul service ouvert » n'est pas concerné.
        $this->pdo->prepare(
            'INSERT INTO services (agent_id, site_id, pointage_arrivee_id, pointage_depart_id, debut, fin, duree_minutes, statut)
             VALUES (:agent, :site, :arrivee, :depart, :debut, :fin, :duree, :statut)'
        )->execute([
            'agent' => $agentId,
            'site' => $siteId,
            'arrivee' => $arrivee,
            'depart' => $depart,
            'debut' => Clock::sql($debut, true),
            'fin' => Clock::sql($fin, true),
            'duree' => intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60),
            'statut' => 'corrige',
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function pointageManuel(int $agentId, int $siteId, string $type, DateTimeImmutable $horodatage, ?int $vacationId): array
    {
        return [
            'agent_id' => $agentId,
            'site_id' => $siteId,
            'vacation_id' => $vacationId,
            'type' => $type,
            'mode' => 'manuel',
            'horodatage' => Clock::sql($horodatage, true),
            'horodatage_reception' => Clock::sql(Clock::now(), true),
            'geoloc_statut' => 'indisponible',
            'uuid_client' => Uuid::v4(),
            'statut' => 'corrige',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pointage(int $id): array
    {
        return $this->pointages->trouver($id) ?? throw ValidationException::champ('pointage', 'Pointage introuvable.');
    }

    /**
     * @return array<string, mixed>
     */
    private function enAttente(int $id): array
    {
        $correction = $this->corrections->trouver($id);
        if ($correction === null || $correction['statut'] !== 'proposee') {
            throw ValidationException::champ('correction', 'Cette correction a déjà été traitée.');
        }

        return $correction;
    }

    private function verifierPeriodeOuverte(string $dateSql): void
    {
        $local = Clock::fromSql($dateSql)?->setTimezone(Clock::local()) ?? Clock::now();
        if ($this->periodes->estCloturee((int) $local->format('Y'), (int) $local->format('n'))) {
            throw ValidationException::champ('periode', 'Le mois de ' . nom_mois((int) $local->format('n')) . ' est clôturé : les heures ne peuvent plus être modifiées.');
        }
    }

    /**
     * @param array<string, mixed> $ligne
     * @return array<string, scalar|null>
     */
    private static function resume(array $ligne): array
    {
        return array_intersect_key($ligne, array_flip(['id', 'agent_id', 'site_id', 'type', 'horodatage', 'statut', 'debut', 'fin', 'indicateurs']));
    }
}
