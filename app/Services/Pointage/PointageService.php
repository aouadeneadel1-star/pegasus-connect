<?php

declare(strict_types=1);

namespace App\Services\Pointage;

use App\Core\Clock;
use App\Core\Uuid;
use App\Models\UtilisateurConnecte;
use App\Repositories\AgentRepository;
use App\Repositories\AppareilRepository;
use App\Repositories\BorneRepository;
use App\Repositories\ConformiteRepository;
use App\Repositories\PointageRepository;
use App\Repositories\ServiceTravailRepository;
use App\Repositories\SiteRepository;
use App\Repositories\VacationRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Destinataires;
use App\Services\Notifications;
use App\Services\Parametres;
use App\Services\Qr\ContenuQr;
use App\Services\Qr\JetonInvalideException;
use App\Services\Qr\JetonQr;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Enregistrement d'un pointage d'arrivée ou de départ (cahier des charges, § 3.4 et § 5.6.2).
 *
 * L'identité vient toujours de la session (QR) ou du couple badge + PIN (borne) ;
 * le QR ne désigne que le lieu. Le type est déduit du service ouvert.
 */
final class PointageService
{
    /** Indicateurs qui placent le pointage dans la file « à vérifier ». */
    private const INDICATEURS_A_VERIFIER = [
        'hors_zone', 'imprecise', 'vitesse', 'appareil_partage', 'sync_tardive', 'pin_hors_ligne', 'hors_affectation',
    ];

    private const RAISONS = [
        'hors_zone' => 'Position éloignée du site',
        'imprecise' => 'Position GPS imprécise',
        'vitesse' => 'Déplacement incohérent depuis le pointage précédent',
        'appareil_partage' => 'Téléphone utilisé par un autre agent',
        'sync_tardive' => 'Transmis plus de 24 h après le scan',
        'pin_hors_ligne' => 'Code PIN refusé à la synchronisation',
        'hors_affectation' => 'Site hors de vos affectations',
        'carte_pro_expiree' => 'Carte professionnelle expirée',
        'hors_planning' => 'Aucune vacation prévue',
        'retard' => 'Arrivée après l\'heure prévue',
        'depart_anticipe' => 'Départ avant l\'heure prévue',
        'doublon' => 'Pointage déjà enregistré',
        'geoloc_statique' => 'Position indisponible sur un site à QR fixe',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PointageRepository $pointages,
        private readonly ServiceTravailRepository $services,
        private readonly VacationRepository $vacations,
        private readonly SiteRepository $sites,
        private readonly BorneRepository $bornes,
        private readonly AgentRepository $agents,
        private readonly ConformiteRepository $conformite,
        private readonly AppareilRepository $appareils,
        private readonly JetonQr $jetons,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
        private readonly Destinataires $destinataires,
    ) {
    }

    /**
     * Pointage par scan d'un QR de borne ou d'un QR fixe.
     *
     * @param array{jeton: string, uuid: string, lat?: ?float, lng?: ?float, precision?: ?int, geoloc?: ?string,
     *              horodatage_appareil?: ?string, appareil_uuid?: ?string, changer_site?: bool} $saisie
     * @return array<string, mixed>
     */
    public function pointerQr(UtilisateurConnecte $utilisateur, array $saisie, bool $horsLigne, string $userAgent): array
    {
        $agentId = $utilisateur->agentId ?? throw new PointageRefuseException('Seuls les agents peuvent pointer.', 'role');
        $uuid = $this->uuidValide($saisie['uuid']);

        $existant = $this->pointages->parUuid($uuid);
        if ($existant !== null) {
            return $this->resultatExistant($existant, $agentId);
        }

        $reception = Clock::now();
        $appareil = self::date($saisie['horodatage_appareil'] ?? null);

        try {
            $qr = JetonQr::lire($saisie['jeton']);
        } catch (JetonInvalideException $exception) {
            throw new PointageRefuseException($exception->getMessage(), 'jeton_illisible');
        }

        [$site, $borneId, $fenetre, $horodatage] = match ($qr->type) {
            'D' => $this->verifierDynamique($qr, $horsLigne, $reception, $appareil),
            'S' => $this->verifierStatique($qr, $horsLigne, $reception, $appareil),
            'R' => throw new PointageRefuseException('Ce QR code est celui d\'un point de ronde. Scannez le QR de la borne ou du poste.', 'jeton_ronde'),
            default => throw new PointageRefuseException('QR code illisible.', 'jeton_illisible'),
        };

        $indicateurs = [];
        $geo = $this->geolocalisation($site, $saisie, $indicateurs);
        if ($site['mode_pointage'] === 'statique' && $qr->type === 'S' && !in_array($geo['geoloc_statut'], ['ok', 'hors_zone', 'imprecise'], true)) {
            $indicateurs[] = 'geoloc_statique';
        }

        $appareilId = null;
        $appareilUuid = (string) ($saisie['appareil_uuid'] ?? '');
        if (Uuid::estValide($appareilUuid)) {
            $appareilId = $this->appareils->enregistrer($appareilUuid, $utilisateur->id, $userAgent);
            if ($this->appareils->partageDepuis($appareilId, $utilisateur->id, $reception->modify('-12 hours'))) {
                $indicateurs[] = 'appareil_partage';
            }
        }

        return $this->enregistrer(
            agentId: $agentId,
            site: $site,
            mode: $qr->type === 'D' ? 'qr_dynamique' : 'qr_statique',
            horodatage: $horodatage,
            reception: $reception,
            appareil: $appareil,
            horsLigne: $horsLigne,
            uuid: $uuid,
            indicateurs: $indicateurs,
            geo: $geo,
            borneId: $borneId,
            badgeId: null,
            appareilId: $appareilId,
            fenetre: $fenetre,
            changerSite: (bool) ($saisie['changer_site'] ?? false),
        );
    }

    /**
     * Pointage à la borne avec un badge déjà reconnu et, si exigé, son PIN vérifié.
     *
     * @param array<string, mixed> $borne
     * @param list<string> $indicateurs
     * @return array<string, mixed>
     */
    public function pointerBadge(
        array $borne,
        int $badgeId,
        int $agentId,
        string $uuid,
        DateTimeImmutable $horodatage,
        bool $horsLigne,
        array $indicateurs,
    ): array {
        $uuid = $this->uuidValide($uuid);
        $existant = $this->pointages->parUuid($uuid);
        if ($existant !== null) {
            return $this->resultatExistant($existant, $agentId);
        }

        $site = $this->sites->trouver((int) $borne['site_id']) ?? throw new PointageRefuseException('Site inconnu.', 'site');
        $reception = Clock::now();
        $retenu = $horodatage > $reception ? $reception : $horodatage;
        if ($horsLigne && $reception->getTimestamp() - $retenu->getTimestamp() > $this->parametres->int('hors_ligne.retard_max_h', 24) * 3600) {
            $indicateurs[] = 'sync_tardive';
        }

        if (!$this->agents->estAffecte($agentId, (int) $site['id'])) {
            if ($this->parametres->get('badge.hors_affectation', 'accepter_a_verifier') === 'refuser') {
                throw new PointageRefuseException('Ce site ne fait pas partie de vos affectations.', 'hors_affectation');
            }
            $indicateurs[] = 'hors_affectation';
        }

        return $this->enregistrer(
            agentId: $agentId,
            site: $site,
            mode: 'badge_nfc',
            horodatage: $horsLigne ? $retenu : $reception,
            reception: $reception,
            appareil: $horsLigne ? $horodatage : null,
            horsLigne: $horsLigne,
            uuid: $uuid,
            indicateurs: $indicateurs,
            geo: ['latitude' => null, 'longitude' => null, 'precision_m' => null, 'distance_site_m' => null, 'geoloc_statut' => 'ok'],
            borneId: (int) $borne['id'],
            badgeId: $badgeId,
            appareilId: null,
            fenetre: null,
            // À la borne, l'agent qui change de site sans avoir pointé son départ est un cas de relève :
            // le service précédent est clos automatiquement.
            changerSite: true,
        );
    }

    /**
     * Annulation par l'agent d'un pointage erroné, dans les deux minutes.
     *
     * @return array<string, mixed>
     */
    public function annuler(UtilisateurConnecte $utilisateur, int $pointageId, string $motif): array
    {
        $pointage = $this->pointages->trouver($pointageId);
        if ($pointage === null || (int) $pointage['agent_id'] !== $utilisateur->agentId) {
            throw new PointageRefuseException('Pointage introuvable.', 'introuvable');
        }
        if ($pointage['statut'] === 'annule') {
            return ['annule' => true, 'type' => $pointage['type']];
        }

        $delai = $this->parametres->int('pointage.annulation_min', 2) * 60;
        $recu = Clock::fromSql((string) $pointage['horodatage_reception']);
        if ($recu === null || Clock::now()->getTimestamp() - $recu->getTimestamp() > $delai) {
            throw new PointageRefuseException('Le délai d\'annulation est dépassé. Demandez une correction à votre chef d\'équipe.', 'delai');
        }

        $this->pdo->beginTransaction();
        try {
            $service = $this->services->parPointage($pointageId);
            if ($service !== null) {
                if ($pointage['type'] === 'arrivee') {
                    if ($service['pointage_depart_id'] !== null) {
                        throw new PointageRefuseException('Ce service est déjà clos.', 'service_clos');
                    }
                    $this->services->definirStatut((int) $service['id'], 'annule');
                } else {
                    $ouvert = $this->services->ouvertPourAgent((int) $pointage['agent_id'], true);
                    if ($ouvert !== null) {
                        throw new PointageRefuseException('Un autre service est déjà ouvert.', 'service_ouvert');
                    }
                    $this->services->rouvrir((int) $service['id']);
                }
            }
            $this->pointages->definirStatut($pointageId, 'annule');
            $this->audit->journaliser('pointage.annuler', 'pointage', $pointageId, ['type' => $pointage['type'], 'motif' => mb_substr($motif, 0, 120)]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return ['annule' => true, 'type' => $pointage['type']];
    }

    /**
     * @return array<string, mixed>
     */
    public function resultatPublic(array $pointage, bool $idempotent = false): array
    {
        $indicateurs = self::indicateurs($pointage['indicateurs'] ?? null);
        $horodatage = Clock::fromSql((string) $pointage['horodatage']);
        $recu = Clock::fromSql((string) $pointage['horodatage_reception']);

        return [
            'id' => (int) $pointage['id'],
            'type' => $pointage['type'],
            'site' => $pointage['site'],
            'horodatage' => $horodatage?->format(DATE_ATOM),
            'heure' => $horodatage?->setTimezone(Clock::local())->format('H:i'),
            'statut' => $pointage['statut'],
            'hors_ligne' => (bool) $pointage['hors_ligne'],
            'indicateurs' => $indicateurs,
            'raisons' => array_values(array_map(static fn (string $code): string => self::RAISONS[$code] ?? $code, array_filter(
                $indicateurs,
                static fn (string $code): bool => in_array($code, [...self::INDICATEURS_A_VERIFIER, 'carte_pro_expiree', 'hors_planning', 'retard', 'depart_anticipe'], true),
            ))),
            'annulable_jusqua' => $recu?->modify('+' . $this->parametres->int('pointage.annulation_min', 2) . ' minutes')->format(DATE_ATOM),
            'idempotent' => $idempotent,
        ];
    }

    public static function libelleIndicateur(string $code): string
    {
        return self::RAISONS[$code] ?? $code;
    }

    /**
     * @return list<string>
     */
    public static function indicateurs(?string $valeur): array
    {
        return $valeur === null || $valeur === '' ? [] : explode(',', $valeur);
    }

    /**
     * @param array<string, mixed> $site
     * @param list<string> $indicateurs
     * @param array{latitude: ?float, longitude: ?float, precision_m: ?int, distance_site_m: ?int, geoloc_statut: string} $geo
     * @return array<string, mixed>
     */
    private function enregistrer(
        int $agentId,
        array $site,
        string $mode,
        DateTimeImmutable $horodatage,
        DateTimeImmutable $reception,
        ?DateTimeImmutable $appareil,
        bool $horsLigne,
        string $uuid,
        array $indicateurs,
        array $geo,
        ?int $borneId,
        ?int $badgeId,
        ?int $appareilId,
        ?int $fenetre,
        bool $changerSite,
    ): array {
        $siteId = (int) $site['id'];
        $dateLocale = $horodatage->setTimezone(Clock::local())->format('Y-m-d');

        $carteValide = $this->conformite->carteProValideLe($agentId, $dateLocale);
        if ($carteValide !== true) {
            if ($this->parametres->bool('pointage.bloquer_carte_pro_expiree', false)) {
                throw new PointageRefuseException('Votre carte professionnelle n\'est pas valide. Contactez le service RH.', 'carte_pro');
            }
            $indicateurs[] = 'carte_pro_expiree';
        }

        $this->pdo->beginTransaction();
        try {
            $ouvert = $this->services->ouvertPourAgent($agentId, true);

            if ($ouvert !== null && (int) $ouvert['site_id'] !== $siteId) {
                if (!$changerSite) {
                    $this->pdo->rollBack();

                    return [
                        'confirmation_requise' => true,
                        'site_ouvert' => $ouvert['site'],
                        'site' => $site['nom'],
                        'message' => sprintf('Un service est ouvert sur %s. Le clôturer et ouvrir %s ?', $ouvert['site'], $site['nom']),
                    ];
                }
                $this->cloreServiceAutreSite($ouvert, $agentId, $horodatage, $reception, $horsLigne);
                $ouvert = null;
            }

            $type = $ouvert === null ? 'arrivee' : 'depart';

            $dernier = $this->pointages->dernierPourAgent($agentId);
            if ($dernier !== null && $this->estDoublon($dernier, $siteId, $horodatage)) {
                $this->pdo->rollBack();
                $dernier['site'] = $site['nom'];

                return $this->resultatPublic($dernier, true) + ['doublon' => true];
            }
            if ($dernier !== null && $this->vitesseIncoherente($dernier, $site, $geo, $horodatage)) {
                $indicateurs[] = 'vitesse';
            }

            $vacation = $this->vacations->rapprocher($agentId, $siteId, $horodatage);
            $indicateurs = [...$indicateurs, ...$this->indicateursPlanning($vacation, $type, $horodatage)];

            $indicateurs = array_values(array_unique($indicateurs));
            $aVerifier = array_intersect($indicateurs, [...self::INDICATEURS_A_VERIFIER, 'geoloc_statique']) !== [];
            $stockes = array_values(array_diff($indicateurs, ['geoloc_statique']));

            $pointageId = $this->pointages->creer([
                'agent_id' => $agentId,
                'site_id' => $siteId,
                'borne_id' => $borneId,
                'vacation_id' => $vacation === null ? null : (int) $vacation['id'],
                'type' => $type,
                'mode' => $mode,
                'badge_id' => $badgeId,
                'horodatage' => Clock::sql($horodatage, true),
                'horodatage_reception' => Clock::sql($reception, true),
                'horodatage_appareil' => $appareil === null ? null : Clock::sql($appareil, true),
                'hors_ligne' => $horsLigne ? 1 : 0,
                'latitude' => $geo['latitude'],
                'longitude' => $geo['longitude'],
                'precision_m' => $geo['precision_m'],
                'distance_site_m' => $geo['distance_site_m'],
                'geoloc_statut' => $geo['geoloc_statut'],
                'appareil_id' => $appareilId,
                'uuid_client' => $uuid,
                'jeton_fenetre' => $fenetre,
                'statut' => $aVerifier ? 'a_verifier' : 'valide',
                'indicateurs' => $stockes === [] ? null : implode(',', $stockes),
            ]);

            if ($type === 'arrivee') {
                $this->services->ouvrir($agentId, $siteId, $vacation === null ? null : (int) $vacation['id'], $pointageId, $horodatage);
            } else {
                $this->services->clore((int) $ouvert['id'], $pointageId, $horodatage);
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Deux envois simultanés du même scan : le second retrouve le premier.
            $existant = $exception->getCode() === '23000' ? $this->pointages->parUuid($uuid) : null;
            if ($existant !== null) {
                return $this->resultatExistant($existant, $agentId);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $this->apresEnregistrement($pointageId, $agentId, $site, $indicateurs, $aVerifier, $mode);

        $pointage = $this->pointages->trouver($pointageId) ?? [];

        return $this->resultatPublic($pointage);
    }

    /**
     * @param array<string, mixed> $ouvert
     */
    /**
     * Départ non pointé sur le site précédent : il est enregistré juste avant la nouvelle arrivée
     * et placé dans la file « à vérifier » pour que le chef d'équipe confirme l'heure.
     *
     * @param array<string, mixed> $ouvert
     */
    private function cloreServiceAutreSite(
        array $ouvert,
        int $agentId,
        DateTimeImmutable $horodatage,
        DateTimeImmutable $reception,
        bool $horsLigne,
    ): void {
        $depart = $horodatage->modify('-1 second');
        $pointageId = $this->pointages->creer([
            'agent_id' => $agentId,
            'site_id' => (int) $ouvert['site_id'],
            'vacation_id' => $ouvert['vacation_id'],
            'type' => 'depart',
            'mode' => 'manuel',
            'horodatage' => Clock::sql($depart, true),
            'horodatage_reception' => Clock::sql($reception, true),
            'hors_ligne' => $horsLigne ? 1 : 0,
            'geoloc_statut' => 'indisponible',
            'uuid_client' => Uuid::v4(),
            'statut' => 'a_verifier',
        ]);
        $this->services->clore((int) $ouvert['id'], $pointageId, $depart);
        $this->audit->journaliser('pointage.changement_site', 'service', (int) $ouvert['id'], ['site_precedent' => (int) $ouvert['site_id']]);
    }

    /**
     * @param array<string, mixed> $site
     * @param list<string> $indicateurs
     */
    private function apresEnregistrement(int $pointageId, int $agentId, array $site, array $indicateurs, bool $aVerifier, string $mode): void
    {
        try {
            if ($aVerifier) {
                $this->audit->journaliser('pointage.a_verifier', 'pointage', $pointageId, ['indicateurs' => $indicateurs, 'mode' => $mode]);
            }

            $agent = $this->agents->trouver($agentId);
            $nom = $agent === null ? 'Un agent' : $agent['prenom'] . ' ' . $agent['nom'];

            if (in_array('carte_pro_expiree', $indicateurs, true) && $this->parametres->bool('pointage.alerte_carte_pro_expiree', true)) {
                $this->notifications->envoyer(
                    $this->destinataires->rh(),
                    'carte_pro_pointage',
                    'Pointage avec une carte professionnelle non valide',
                    $nom . ' a pointé sur ' . $site['nom'] . ' sans carte professionnelle valide.',
                    '/admin/agents/' . $agentId . '?onglet=conformite',
                );
            }
            if (in_array('appareil_partage', $indicateurs, true)) {
                $this->notifications->envoyer(
                    $this->destinataires->pourSite((int) $site['id']),
                    'appareil_partage',
                    'Téléphone partagé entre deux agents',
                    $nom . ' a pointé sur ' . $site['nom'] . ' avec un téléphone utilisé par un autre agent dans les 12 dernières heures.',
                    '/admin/pointages?statut=a_verifier',
                );
            }
        } catch (Throwable) {
            // Le pointage est enregistré : une alerte manquée ne doit pas le faire échouer.
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: int, 2: int, 3: DateTimeImmutable}
     */
    private function verifierDynamique(ContenuQr $qr, bool $horsLigne, DateTimeImmutable $reception, ?DateTimeImmutable $appareil): array
    {
        [$siteId, $borneId, $fenetre] = $qr->valeurs;
        $borne = $this->bornes->trouver($borneId);
        if ($borne === null || (int) $borne['site_id'] !== $siteId || !$this->jetons->signatureDynamiqueValide($qr, (int) $borne['cle_version'])) {
            throw new PointageRefuseException('QR code non reconnu. Scannez le QR affiché sur la borne.', 'signature');
        }
        if ($borne['statut'] !== 'active') {
            throw new PointageRefuseException('Cette borne n\'est plus active. Prévenez votre chef d\'équipe.', 'borne');
        }

        $site = $this->siteActif($siteId);

        if (!$horsLigne) {
            $courante = JetonQr::fenetre($reception->getTimestamp());
            if ($fenetre !== $courante && $fenetre !== $courante - 1) {
                throw new PointageRefuseException('QR expiré, scannez à nouveau.', 'expire');
            }

            return [$site, $borneId, $fenetre, $reception];
        }

        // Hors ligne : le jeton est comparé à l'heure du téléphone, qui est elle-même bornée par la fenêtre du jeton.
        $debutFenetre = $fenetre * JetonQr::FENETRE_SECONDES;
        $reference = $appareil ?? $reception;
        $ecart = $reference->getTimestamp() - $debutFenetre;
        if ($ecart < -2 * JetonQr::FENETRE_SECONDES || $ecart > 3 * JetonQr::FENETRE_SECONDES) {
            throw new PointageRefuseException('QR expiré au moment du scan.', 'expire');
        }
        $borneInf = (new DateTimeImmutable('@' . ($debutFenetre - 120)))->setTimezone(Clock::utc());
        $borneSup = (new DateTimeImmutable('@' . ($debutFenetre + JetonQr::FENETRE_SECONDES + 120)))->setTimezone(Clock::utc());
        $retenu = max($borneInf, min($borneSup, $reference, $reception));

        return [$site, $borneId, $fenetre, $retenu];
    }

    /**
     * @return array{0: array<string, mixed>, 1: null, 2: null, 3: DateTimeImmutable}
     */
    private function verifierStatique(ContenuQr $qr, bool $horsLigne, DateTimeImmutable $reception, ?DateTimeImmutable $appareil): array
    {
        [$siteId, $version] = $qr->valeurs;
        if (!$this->jetons->signatureStatiqueValide($qr)) {
            throw new PointageRefuseException('QR code non reconnu.', 'signature');
        }

        $site = $this->siteActif($siteId);
        if ((int) $site['qr_statique_version'] !== $version) {
            throw new PointageRefuseException('Cette étiquette a été remplacée. Prévenez votre chef d\'équipe.', 'version');
        }
        if ($site['mode_pointage'] !== 'statique') {
            throw new PointageRefuseException('Ce site dispose d\'une borne : scannez le QR affiché à l\'écran.', 'mode');
        }

        $retenu = $horsLigne && $appareil !== null ? min($appareil, $reception) : $reception;

        return [$site, null, null, $retenu];
    }

    /**
     * @return array<string, mixed>
     */
    private function siteActif(int $siteId): array
    {
        $site = $this->sites->trouver($siteId);
        if ($site === null || !(bool) $site['actif']) {
            throw new PointageRefuseException('Ce site n\'est plus actif.', 'site');
        }

        return $site;
    }

    /**
     * @param array<string, mixed> $site
     * @param array<string, mixed> $saisie
     * @param list<string> $indicateurs
     * @return array{latitude: ?float, longitude: ?float, precision_m: ?int, distance_site_m: ?int, geoloc_statut: string}
     */
    private function geolocalisation(array $site, array $saisie, array &$indicateurs): array
    {
        $lat = isset($saisie['lat']) && is_numeric($saisie['lat']) ? (float) $saisie['lat'] : null;
        $lng = isset($saisie['lng']) && is_numeric($saisie['lng']) ? (float) $saisie['lng'] : null;
        $precision = isset($saisie['precision']) && is_numeric($saisie['precision']) ? (int) round((float) $saisie['precision']) : null;

        if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
            return [
                'latitude' => null,
                'longitude' => null,
                'precision_m' => null,
                'distance_site_m' => null,
                'geoloc_statut' => ($saisie['geoloc'] ?? '') === 'refusee' ? 'refusee' : 'indisponible',
            ];
        }

        $distance = Geo::distance($lat, $lng, (float) $site['latitude'], (float) $site['longitude']);
        $rayon = (int) ($site['rayon_metres'] ?: $this->parametres->int('pointage.rayon_defaut_m', 150));
        $statut = 'ok';
        if ($precision !== null && $precision > $this->parametres->int('pointage.precision_max_m', 100)) {
            $statut = 'imprecise';
            $indicateurs[] = 'imprecise';
        }
        if ($distance > $rayon + ($precision ?? 0)) {
            $statut = 'hors_zone';
            $indicateurs[] = 'hors_zone';
        }

        return [
            'latitude' => round($lat, 6),
            'longitude' => round($lng, 6),
            'precision_m' => $precision === null ? null : min($precision, 65535),
            'distance_site_m' => $distance,
            'geoloc_statut' => $statut,
        ];
    }

    /**
     * @param array<string, mixed> $dernier
     */
    private function estDoublon(array $dernier, int $siteId, DateTimeImmutable $horodatage): bool
    {
        $precedent = Clock::fromSql((string) $dernier['horodatage']);

        return (int) $dernier['site_id'] === $siteId
            && $precedent !== null
            && abs($horodatage->getTimestamp() - $precedent->getTimestamp()) < $this->parametres->int('pointage.doublon_min', 5) * 60;
    }

    /**
     * @param array<string, mixed> $dernier
     * @param array<string, mixed> $site
     * @param array{latitude: ?float, longitude: ?float} $geo
     */
    private function vitesseIncoherente(array $dernier, array $site, array $geo, DateTimeImmutable $horodatage): bool
    {
        $precedent = Clock::fromSql((string) $dernier['horodatage']);
        if ($precedent === null) {
            return false;
        }

        $distance = Geo::distance(
            (float) ($dernier['latitude'] ?? $dernier['site_latitude']),
            (float) ($dernier['longitude'] ?? $dernier['site_longitude']),
            (float) ($geo['latitude'] ?? $site['latitude']),
            (float) ($geo['longitude'] ?? $site['longitude']),
        );

        // En dessous d'un kilomètre, l'imprécision du GPS suffit à expliquer l'écart.
        return $distance > 1000
            && Geo::vitesseKmh($distance, abs($horodatage->getTimestamp() - $precedent->getTimestamp())) > $this->parametres->int('pointage.vitesse_max_kmh', 150);
    }

    /**
     * @param array<string, mixed>|null $vacation
     * @return list<string>
     */
    private function indicateursPlanning(?array $vacation, string $type, DateTimeImmutable $horodatage): array
    {
        if ($vacation === null) {
            return ['hors_planning'];
        }

        if ($type === 'arrivee') {
            $limite = Clock::fromSql((string) $vacation['debut'])?->modify('+' . $this->parametres->int('pointage.tolerance_retard_min', 5) . ' minutes');

            return $limite !== null && $horodatage > $limite ? ['retard'] : [];
        }

        $limite = Clock::fromSql((string) $vacation['fin'])?->modify('-' . $this->parametres->int('pointage.tolerance_depart_min', 5) . ' minutes');

        return $limite !== null && $horodatage < $limite ? ['depart_anticipe'] : [];
    }

    /**
     * @param array<string, mixed> $existant
     * @return array<string, mixed>
     */
    private function resultatExistant(array $existant, int $agentId): array
    {
        if ((int) $existant['agent_id'] !== $agentId) {
            throw new PointageRefuseException('Identifiant de pointage déjà utilisé.', 'uuid');
        }

        return $this->resultatPublic($existant, true);
    }

    private function uuidValide(string $uuid): string
    {
        if (!Uuid::estValide($uuid)) {
            throw new PointageRefuseException('Requête invalide.', 'uuid');
        }

        return strtolower($uuid);
    }

    private static function date(?string $valeur): ?DateTimeImmutable
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($valeur))->setTimezone(Clock::utc());
        } catch (Throwable) {
            return null;
        }
    }
}
