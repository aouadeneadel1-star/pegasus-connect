<?php

declare(strict_types=1);

namespace App\Services\Rondes;

use App\Core\Clock;
use App\Core\Uuid;
use App\Models\UtilisateurConnecte;
use App\Repositories\AnomalieRepository;
use App\Repositories\ModeleRondeRepository;
use App\Repositories\PointControleRepository;
use App\Repositories\RondeRepository;
use App\Repositories\ServiceTravailRepository;
use App\Repositories\SiteRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Destinataires;
use App\Services\Notifications;
use App\Services\Parametres;
use App\Services\Pointage\Geo;
use App\Services\Pointage\PointageRefuseException;
use App\Services\Qr\JetonInvalideException;
use App\Services\Qr\JetonQr;
use App\Services\Stockage\FichierRefuseException;
use App\Services\Stockage\Stockage;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Déroulement des rondes : démarrage, scan des points, clôture, signalement d'anomalies
 * (cahier des charges, § 3.5).
 */
final class RondeService
{
    private const INDICATEURS_A_VERIFIER = ['hors_zone', 'trop_rapide', 'scan_groupe', 'sync_tardive'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly RondeRepository $rondes,
        private readonly ModeleRondeRepository $modeles,
        private readonly PointControleRepository $points,
        private readonly ServiceTravailRepository $services,
        private readonly SiteRepository $sites,
        private readonly AnomalieRepository $anomalies,
        private readonly JetonQr $jetons,
        private readonly Stockage $stockage,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
        private readonly Destinataires $destinataires,
    ) {
    }

    /**
     * @return array<string, mixed> état de la ronde démarrée
     */
    public function demarrer(UtilisateurConnecte $utilisateur, ?int $rondeId, string $uuid): array
    {
        $agentId = $utilisateur->agentId ?? throw new PointageRefuseException('Seuls les agents effectuent des rondes.', 'role');
        if (!Uuid::estValide($uuid)) {
            throw new PointageRefuseException('Requête invalide.', 'uuid');
        }

        $existante = $this->rondes->parUuid(strtolower($uuid));
        if ($existante !== null && (int) $existante['agent_id'] === $agentId) {
            return $this->etat((int) $existante['id']);
        }

        $enCours = $this->rondes->enCoursPourAgent($agentId);
        if ($enCours !== null) {
            return $this->etat((int) $enCours['id']);
        }

        $service = $this->services->ouvertPourAgent($agentId);
        if ($service === null && $this->parametres->bool('rondes.exiger_service_ouvert', true)) {
            throw new PointageRefuseException('Pointez votre arrivée avant de démarrer une ronde.', 'service');
        }
        $siteId = $service === null ? null : (int) $service['site_id'];
        $maintenant = Clock::now();

        $this->pdo->beginTransaction();
        try {
            if ($rondeId !== null) {
                $ronde = $this->rondes->trouver($rondeId, true);
                if ($ronde === null || $ronde['statut'] !== 'a_venir' || ($siteId !== null && (int) $ronde['site_id'] !== $siteId)) {
                    throw new PointageRefuseException('Cette ronde n\'est plus disponible.', 'ronde');
                }
                $points = $this->modeles->points((int) $ronde['modele_id']);
                if ($points === []) {
                    throw new PointageRefuseException('Aucun point de contrôle n\'est rattaché à cette ronde.', 'points');
                }
                $this->rondes->demarrer($rondeId, $agentId, $maintenant, $points, strtolower($uuid));
                $id = $rondeId;
            } else {
                if ($siteId === null) {
                    throw new PointageRefuseException('Une ronde libre se fait sur le site où vous êtes en service.', 'service');
                }
                $points = [];
                foreach (array_values($this->points->pourSite($siteId)) as $index => $point) {
                    $points[] = ['point_id' => (int) $point['id'], 'ordre' => $index + 1, 'obligatoire' => false];
                }
                if ($points === []) {
                    throw new PointageRefuseException('Aucun point de contrôle n\'est défini sur ce site.', 'points');
                }
                $id = $this->rondes->creerLibre($siteId, $agentId, $maintenant, $points, strtolower($uuid));
            }
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            // Index unique : une seule ronde en cours par agent.
            $enCours = $exception->getCode() === '23000' ? $this->rondes->enCoursPourAgent($agentId) : null;
            if ($enCours !== null) {
                return $this->etat((int) $enCours['id']);
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit->journaliser('ronde.demarrer', 'ronde', $id, ['libre' => $rondeId === null]);

        return $this->etat($id);
    }

    /**
     * @param array{jeton: string, uuid: string, lat?: ?float, lng?: ?float, precision?: ?int, horodatage_appareil?: ?string} $saisie
     * @return array<string, mixed>
     */
    public function passage(UtilisateurConnecte $utilisateur, int $rondeId, array $saisie, bool $horsLigne): array
    {
        $agentId = $utilisateur->agentId ?? throw new PointageRefuseException('Seuls les agents effectuent des rondes.', 'role');
        $uuid = strtolower((string) $saisie['uuid']);
        if (!Uuid::estValide($uuid)) {
            throw new PointageRefuseException('Requête invalide.', 'uuid');
        }

        $existant = $this->rondes->passageParUuid($uuid);
        if ($existant !== null) {
            return ['passage' => self::passagePublic($existant), 'idempotent' => true, 'ronde' => $this->etat($rondeId)];
        }

        $ronde = $this->rondes->trouver($rondeId);
        if ($ronde === null || (int) $ronde['agent_id'] !== $agentId) {
            throw new PointageRefuseException('Ronde introuvable.', 'ronde');
        }
        if ($ronde['statut'] !== 'en_cours' && !$horsLigne) {
            throw new PointageRefuseException('Cette ronde est clôturée.', 'ronde_close');
        }

        try {
            $qr = JetonQr::lire((string) $saisie['jeton']);
        } catch (JetonInvalideException $exception) {
            throw new PointageRefuseException($exception->getMessage(), 'jeton_illisible');
        }
        if ($qr->type !== 'R' || !$this->jetons->signaturePointValide($qr)) {
            throw new PointageRefuseException($qr->type === 'R' ? 'QR code non reconnu.' : 'Ce QR code n\'est pas un point de ronde.', 'signature');
        }

        [$pointId, $version] = $qr->valeurs;
        $point = $this->points->trouver($pointId);
        if ($point === null || (int) $point['site_id'] !== (int) $ronde['site_id']) {
            throw new PointageRefuseException('Ce point n\'appartient pas au site de la ronde.', 'point_site');
        }
        if (!(bool) $point['actif'] || (int) $point['qr_version'] !== $version) {
            throw new PointageRefuseException('Cette étiquette n\'est plus valide. Signalez-la à votre chef d\'équipe.', 'point_version');
        }

        $prevus = json_decode((string) $ronde['points_prevus'], true) ?: [];
        $idsPrevus = array_map(static fn (array $p): int => (int) $p['point_id'], $prevus);
        if (!in_array($pointId, $idsPrevus, true)) {
            throw new PointageRefuseException('Ce point ne fait pas partie de cette ronde.', 'point_hors_ronde');
        }

        $dejaScanne = $this->rondes->passageDuPoint($rondeId, $pointId);
        if ($dejaScanne !== null) {
            return ['passage' => self::passagePublic($dejaScanne), 'idempotent' => true, 'ronde' => $this->etat($rondeId)];
        }

        $reception = Clock::now();
        $horodatage = $reception;
        $indicateurs = [];
        $appareil = null;
        if ($horsLigne && !empty($saisie['horodatage_appareil'])) {
            try {
                $appareil = (new DateTimeImmutable((string) $saisie['horodatage_appareil']))->setTimezone(Clock::utc());
                $horodatage = min($appareil, $reception);
            } catch (Throwable) {
                $appareil = null;
            }
            if ($reception->getTimestamp() - $horodatage->getTimestamp() > $this->parametres->int('hors_ligne.retard_max_h', 24) * 3600) {
                $indicateurs[] = 'sync_tardive';
            }
        }

        [$geo, $indicateursGeo] = $this->geolocalisation($point, $saisie);
        $indicateurs = [...$indicateurs, ...$indicateursGeo, ...$this->indicateursRythme($ronde, $prevus, $pointId, $agentId, $horodatage)];
        $indicateurs = array_values(array_unique($indicateurs));

        try {
            $passageId = $this->rondes->ajouterPassage([
                'ronde_id' => $rondeId,
                'point_id' => $pointId,
                'agent_id' => $agentId,
                'horodatage' => Clock::sql($horodatage, true),
                'horodatage_reception' => Clock::sql($reception, true),
                'horodatage_appareil' => $appareil === null ? null : Clock::sql($appareil, true),
                'hors_ligne' => $horsLigne ? 1 : 0,
                'latitude' => $geo['latitude'],
                'longitude' => $geo['longitude'],
                'precision_m' => $geo['precision_m'],
                'distance_point_m' => $geo['distance_point_m'],
                'qr_version' => $version,
                'uuid_client' => $uuid,
                'indicateurs' => $indicateurs === [] ? null : implode(',', $indicateurs),
                'statut' => array_intersect($indicateurs, self::INDICATEURS_A_VERIFIER) === [] ? 'valide' : 'a_verifier',
            ]);
        } catch (PDOException $exception) {
            $existant = $exception->getCode() === '23000' ? $this->rondes->passageParUuid($uuid) : null;
            if ($existant === null) {
                throw $exception;
            }

            return ['passage' => self::passagePublic($existant), 'idempotent' => true, 'ronde' => $this->etat($rondeId)];
        }

        $passage = $this->rondes->passageParUuid($uuid) ?? ['id' => $passageId];

        return ['passage' => self::passagePublic($passage), 'idempotent' => false, 'ronde' => $this->etat($rondeId)];
    }

    /**
     * @return array<string, mixed>
     */
    public function cloturer(UtilisateurConnecte $utilisateur, int $rondeId, ?string $commentaire): array
    {
        $ronde = $this->rondes->trouver($rondeId);
        if ($ronde === null || (int) $ronde['agent_id'] !== $utilisateur->agentId) {
            throw new PointageRefuseException('Ronde introuvable.', 'ronde');
        }
        if ($ronde['statut'] !== 'en_cours') {
            return $this->etat($rondeId);
        }

        $statut = $this->statutFinal($ronde);
        if ($statut === 'incomplete' && trim((string) $commentaire) === '') {
            throw new PointageRefuseException('Des points obligatoires n\'ont pas été contrôlés : indiquez pourquoi.', 'commentaire');
        }

        $this->rondes->cloturer($rondeId, $statut, $commentaire === null ? null : mb_substr(trim($commentaire), 0, 500), false, Clock::now());
        $this->audit->journaliser('ronde.cloturer', 'ronde', $rondeId, ['statut' => $statut]);

        if ($statut === 'incomplete') {
            $this->notifications->envoyer(
                $this->destinataires->pourSite((int) $ronde['site_id'], false),
                'ronde_incomplete',
                'Ronde incomplète sur ' . $ronde['site'],
                ($ronde['prenom'] ?? '') . ' ' . ($ronde['nom'] ?? '') . ' a clôturé une ronde sans contrôler tous les points obligatoires.',
                '/admin/rondes/' . $rondeId,
            );
        }

        return $this->etat($rondeId);
    }

    /**
     * Clôture automatique d'une ronde restée ouverte au-delà de sa durée maximale.
     */
    public function cloturerAutomatiquement(int $rondeId): void
    {
        $ronde = $this->rondes->trouver($rondeId);
        if ($ronde === null || $ronde['statut'] !== 'en_cours') {
            return;
        }

        $statut = $this->statutFinal($ronde);
        $this->rondes->cloturer($rondeId, $statut, 'Clôture automatique : durée maximale dépassée.', true, Clock::now());
        $this->audit->journaliser('ronde.cloture_auto', 'ronde', $rondeId, ['statut' => $statut]);
    }

    /**
     * Signalement d'une anomalie, avec photos facultatives.
     *
     * @param array{categorie: string, gravite: string, description: string, uuid: string, ronde_id?: ?int, point_id?: ?int} $saisie
     * @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $photos
     */
    public function signaler(UtilisateurConnecte $utilisateur, array $saisie, array $photos): int
    {
        $agentId = $utilisateur->agentId ?? throw new PointageRefuseException('Seuls les agents signalent des anomalies.', 'role');
        $uuid = strtolower($saisie['uuid']);
        if (!Uuid::estValide($uuid)) {
            throw new PointageRefuseException('Requête invalide.', 'uuid');
        }
        $existante = $this->anomalies->parUuid($uuid);
        if ($existante !== null) {
            return (int) $existante['id'];
        }

        $rondeId = $saisie['ronde_id'] ?? null;
        $siteId = null;
        if ($rondeId !== null) {
            $ronde = $this->rondes->trouver($rondeId);
            if ($ronde === null || (int) $ronde['agent_id'] !== $agentId) {
                throw new PointageRefuseException('Ronde introuvable.', 'ronde');
            }
            $siteId = (int) $ronde['site_id'];
        } else {
            $service = $this->services->ouvertPourAgent($agentId);
            $siteId = $service === null ? null : (int) $service['site_id'];
        }
        if ($siteId === null) {
            throw new PointageRefuseException('Pointez votre arrivée sur le site avant de signaler une anomalie.', 'service');
        }

        $pointId = $saisie['point_id'] ?? null;
        if ($pointId !== null) {
            $point = $this->points->trouver($pointId);
            $pointId = $point !== null && (int) $point['site_id'] === $siteId ? $pointId : null;
        }

        $id = $this->anomalies->creer([
            'site_id' => $siteId,
            'ronde_id' => $rondeId,
            'point_id' => $pointId,
            'agent_id' => $agentId,
            'categorie' => $saisie['categorie'],
            'gravite' => $saisie['gravite'],
            'description' => mb_substr(trim($saisie['description']), 0, 5000),
            'visible_client' => 1,
            'statut' => 'ouverte',
            'uuid_client' => $uuid,
            'created_at' => Clock::sql(Clock::now(), true),
        ]);

        foreach (array_slice($photos, 0, 5) as $index => $photo) {
            try {
                $documentId = $this->stockage->deposer(
                    $photo, Stockage::IMAGES, 10 * 1048576, 'photo_anomalie', null,
                    'Anomalie ' . $id . ' — photo ' . ($index + 1), $utilisateur->id, ['visible_agent' => false, 'dimension_max' => 1920],
                );
                $this->anomalies->ajouterPhoto($id, $documentId);
            } catch (FichierRefuseException) {
                // Une photo illisible ne doit pas faire perdre le signalement.
            }
        }

        $this->audit->journaliser('anomalie.signaler', 'anomalie', $id, ['gravite' => $saisie['gravite'], 'categorie' => $saisie['categorie']]);

        if (in_array($saisie['gravite'], ['majeure', 'critique'], true)) {
            $site = $this->sites->trouver($siteId);
            $this->notifications->envoyer(
                $this->destinataires->pourSite($siteId),
                'anomalie_' . $saisie['gravite'],
                sprintf('Anomalie %s sur %s', AnomalieRepository::GRAVITES[$saisie['gravite']], $site['nom'] ?? 'un site'),
                AnomalieRepository::CATEGORIES[$saisie['categorie']] . ' signalée par ' . $utilisateur->nomComplet . '.',
                '/admin/anomalies/' . $id,
                $saisie['gravite'] === 'critique' ? 'notification' : null,
            );
        }

        return $id;
    }

    /**
     * État d'une ronde pour l'application : points prévus, contrôlés et progression.
     *
     * @return array<string, mixed>
     */
    public function etat(int $rondeId): array
    {
        $ronde = $this->rondes->trouver($rondeId) ?? throw new PointageRefuseException('Ronde introuvable.', 'ronde');
        $prevus = json_decode((string) ($ronde['points_prevus'] ?? '[]'), true) ?: [];
        $details = [];
        foreach ($this->points->plusieurs(array_map(static fn (array $p): int => (int) $p['point_id'], $prevus)) as $point) {
            $details[(int) $point['id']] = $point;
        }

        $passages = [];
        foreach ($this->rondes->passages($rondeId) as $passage) {
            $passages[(int) $passage['point_id']] ??= $passage;
        }

        $points = [];
        foreach ($prevus as $prevu) {
            $id = (int) $prevu['point_id'];
            $passage = $passages[$id] ?? null;
            $points[] = [
                'id' => $id,
                'nom' => $details[$id]['nom'] ?? 'Point supprimé',
                'zone' => $details[$id]['zone'] ?? null,
                'identifiant' => $details[$id]['identifiant'] ?? null,
                'ordre' => (int) $prevu['ordre'],
                'obligatoire' => (bool) $prevu['obligatoire'],
                'controle' => $passage !== null,
                'heure' => $passage === null ? null : Clock::fromSql((string) $passage['horodatage'])?->setTimezone(Clock::local())->format('H:i'),
                'statut' => $passage['statut'] ?? null,
            ];
        }
        usort($points, static fn (array $a, array $b): int => $a['ordre'] <=> $b['ordre']);

        $debut = Clock::fromSql($ronde['demarree_le'] === null ? null : (string) $ronde['demarree_le']);

        return [
            'id' => (int) $ronde['id'],
            'site' => $ronde['site'],
            'modele' => $ronde['modele'] ?? 'Ronde libre',
            'statut' => $ronde['statut'],
            'ordre_impose' => (bool) ($ronde['ordre_impose'] ?? false),
            'demarree_le' => $debut?->format(DATE_ATOM),
            'fin_prevue' => $debut?->modify('+' . (int) ($ronde['duree_max_minutes'] ?? 90) . ' minutes')->format(DATE_ATOM),
            'nb_prevus' => count($points),
            'nb_controles' => count(array_filter($points, static fn (array $p): bool => $p['controle'])),
            'points' => $points,
        ];
    }

    /**
     * Crée les rondes planifiées sur les 24 heures à venir à partir des modèles actifs.
     */
    public function generer(?DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= Clock::now();
        $limite = $maintenant->modify('+24 hours');
        $crees = 0;

        foreach ($this->modeles->actifs() as $modele) {
            $nbPoints = count($this->modeles->points((int) $modele['id']));
            if ($nbPoints === 0) {
                continue;
            }

            foreach (Planification::occurrences($modele, $maintenant->modify('-1 hour'), $limite) as $prevue) {
                if (!$this->rondes->existePrevue((int) $modele['id'], $prevue)) {
                    $this->rondes->creerPrevue((int) $modele['site_id'], (int) $modele['id'], $prevue, $nbPoints);
                    ++$crees;
                }
            }
        }

        return $crees;
    }

    /**
     * Rondes non démarrées et rondes trop longues : alertes et clôtures automatiques.
     *
     * @return array{manquees: int, cloturees: int}
     */
    public function surveiller(?DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= Clock::now();
        $manquees = 0;
        foreach ($this->rondes->nonDemarrees($maintenant) as $ronde) {
            $this->rondes->marquerManquee((int) $ronde['id']);
            ++$manquees;
            $this->notifications->envoyer(
                $this->destinataires->pourSite((int) $ronde['site_id'], false),
                'ronde_manquee',
                'Ronde non démarrée sur ' . $ronde['site'],
                sprintf('La ronde « %s » prévue à %s n\'a pas été démarrée.', $ronde['modele'], Clock::fromSql((string) $ronde['prevue_le'])?->setTimezone(Clock::local())->format('H:i')),
                '/admin/rondes/' . $ronde['id'],
            );
        }

        $cloturees = 0;
        foreach ($this->rondes->depassees($maintenant) as $ronde) {
            $this->cloturerAutomatiquement((int) $ronde['id']);
            ++$cloturees;
            $this->notifications->envoyer(
                $this->destinataires->pourSite((int) $ronde['site_id'], false),
                'ronde_depassee',
                'Ronde clôturée automatiquement sur ' . $ronde['site'],
                'La durée maximale de la ronde a été dépassée.',
                '/admin/rondes/' . $ronde['id'],
            );
        }

        return ['manquees' => $manquees, 'cloturees' => $cloturees];
    }

    /**
     * @param array<string, mixed> $ronde
     */
    private function statutFinal(array $ronde): string
    {
        $prevus = json_decode((string) $ronde['points_prevus'], true) ?: [];
        $controles = [];
        foreach ($this->rondes->passages((int) $ronde['id']) as $passage) {
            if ($passage['statut'] !== 'rejete') {
                $controles[(int) $passage['point_id']] = true;
            }
        }

        foreach ($prevus as $prevu) {
            if ((bool) $prevu['obligatoire'] && !isset($controles[(int) $prevu['point_id']])) {
                return 'incomplete';
            }
        }

        // Ronde libre : au moins un point contrôlé.
        return $controles === [] ? 'incomplete' : 'terminee';
    }

    /**
     * @param array<string, mixed> $point
     * @param array<string, mixed> $saisie
     * @return array{0: array{latitude: ?float, longitude: ?float, precision_m: ?int, distance_point_m: ?int}, 1: list<string>}
     */
    private function geolocalisation(array $point, array $saisie): array
    {
        $lat = isset($saisie['lat']) && is_numeric($saisie['lat']) ? (float) $saisie['lat'] : null;
        $lng = isset($saisie['lng']) && is_numeric($saisie['lng']) ? (float) $saisie['lng'] : null;
        $precision = isset($saisie['precision']) && is_numeric($saisie['precision']) ? (int) round((float) $saisie['precision']) : null;
        $geo = ['latitude' => null, 'longitude' => null, 'precision_m' => $precision, 'distance_point_m' => null];
        if ($lat === null || $lng === null) {
            return [$geo, []];
        }

        $geo['latitude'] = round($lat, 6);
        $geo['longitude'] = round($lng, 6);
        $indicateurs = [];
        if ($precision !== null && $precision > $this->parametres->int('pointage.precision_max_m', 100)) {
            $indicateurs[] = 'imprecise';
        }

        // Points en intérieur : sans coordonnées propres, le contrôle de distance n'a pas de sens.
        if ($point['latitude'] !== null && $point['longitude'] !== null) {
            $distance = Geo::distance($lat, $lng, (float) $point['latitude'], (float) $point['longitude']);
            $geo['distance_point_m'] = $distance;
            $rayon = (int) ($point['rayon_metres'] ?? $this->parametres->int('rondes.rayon_point_m', 50));
            if ($distance > $rayon + ($precision ?? 0)) {
                $indicateurs[] = 'hors_zone';
            }
        }

        return [$geo, $indicateurs];
    }

    /**
     * Ordre imposé, temps minimal entre deux points, scan groupé de plusieurs étiquettes.
     *
     * @param array<string, mixed> $ronde
     * @param list<array{point_id: int, ordre: int, obligatoire: bool}> $prevus
     * @return list<string>
     */
    private function indicateursRythme(array $ronde, array $prevus, int $pointId, int $agentId, DateTimeImmutable $horodatage): array
    {
        $indicateurs = [];
        $passages = $this->rondes->passages((int) $ronde['id']);
        $controles = array_map(static fn (array $p): int => (int) $p['point_id'], $passages);

        if ((bool) ($ronde['ordre_impose'] ?? false)) {
            usort($prevus, static fn (array $a, array $b): int => $a['ordre'] <=> $b['ordre']);
            foreach ($prevus as $prevu) {
                if (!in_array((int) $prevu['point_id'], $controles, true)) {
                    if ((int) $prevu['point_id'] !== $pointId) {
                        $indicateurs[] = 'ordre';
                    }
                    break;
                }
            }
        }

        $dernier = end($passages);
        if ($dernier !== false) {
            $ecart = abs($horodatage->getTimestamp() - (Clock::fromSql((string) $dernier['horodatage'])?->getTimestamp() ?? 0));
            $minimum = (int) ($ronde['temps_min_entre_points_s'] ?? $this->parametres->int('rondes.temps_min_entre_points_s', 30));
            if ($ecart < $minimum) {
                $indicateurs[] = 'trop_rapide';
            }
        }

        $fenetre = $this->parametres->int('rondes.scan_groupe_secondes', 20);
        $seuil = $this->parametres->int('rondes.scan_groupe_points', 3);
        $recents = $this->rondes->derniersPassagesAgent($agentId, $horodatage->modify('-' . $fenetre . ' seconds'));
        if (count($recents) + 1 >= $seuil) {
            $indicateurs[] = 'scan_groupe';
        }

        return $indicateurs;
    }

    /**
     * @param array<string, mixed> $passage
     * @return array<string, mixed>
     */
    private static function passagePublic(array $passage): array
    {
        $horodatage = Clock::fromSql(isset($passage['horodatage']) ? (string) $passage['horodatage'] : null);

        return [
            'id' => (int) $passage['id'],
            'point_id' => isset($passage['point_id']) ? (int) $passage['point_id'] : null,
            'point' => $passage['point'] ?? null,
            'heure' => $horodatage?->setTimezone(Clock::local())->format('H:i'),
            'statut' => $passage['statut'] ?? 'valide',
            'indicateurs' => isset($passage['indicateurs']) && $passage['indicateurs'] !== null && $passage['indicateurs'] !== '' ? explode(',', (string) $passage['indicateurs']) : [],
        ];
    }
}
