<?php

declare(strict_types=1);

namespace App\Services\Pointage;

use App\Core\Clock;
use App\Repositories\AgentRepository;
use App\Repositories\BadgeRepository;
use App\Repositories\ConformiteRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Crypto\Cles;
use App\Services\Destinataires;
use App\Services\Notifications;
use App\Services\Parametres;
use App\Services\ValidationException;
use DateTimeImmutable;

/**
 * Cycle de vie des badges NFC et lecture à la borne (cahier des charges, § 3.4.8, § 3.18, § 5.6.5).
 */
final class BadgeService
{
    private const DUREE_ASSOCIATION_S = 120;

    private const PINS_INTERDITS = ['0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999',
        '1234', '2345', '3456', '4567', '5678', '6789', '9876', '8765', '7654', '6543', '5432', '4321', '0123', '3210', '1212', '1004', '2580'];

    public function __construct(
        private readonly BadgeRepository $badges,
        private readonly AgentRepository $agents,
        private readonly ConformiteRepository $conformite,
        private readonly Cles $cles,
        private readonly PointageService $pointage,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
        private readonly Destinataires $destinataires,
    ) {
    }

    /**
     * UID hexadécimal en majuscules, sans séparateur. Les puces courantes ont un UID de 4, 7 ou 10 octets.
     */
    public static function normaliserUid(string $uid): ?string
    {
        $uid = strtoupper((string) preg_replace('/[^0-9a-fA-F]/', '', $uid));

        return preg_match('/^(?:[0-9A-F]{8}|[0-9A-F]{14}|[0-9A-F]{20})$/', $uid) === 1 ? $uid : null;
    }

    public function empreinte(string $uidNormalise): string
    {
        return $this->cles->indexAveugle('badge', $uidNormalise);
    }

    /**
     * Lecture d'une carte sur une borne.
     *
     * @param array<string, mixed> $borne ligne de BorneRepository::trouverParJeton()
     * @return array<string, mixed>
     */
    public function lecture(array $borne, string $uidBrut, ?string $pin, string $uuid, ?DateTimeImmutable $horodatageBorne, bool $horsLigne): array
    {
        if (!(bool) $borne['badge_nfc_actif']) {
            throw new PointageRefuseException('Le badgeage n\'est pas activé sur ce site.', 'badge_inactif_site');
        }

        $uid = self::normaliserUid($uidBrut) ?? throw new PointageRefuseException('Carte illisible, présentez-la à nouveau.', 'uid');
        $empreinte = $this->empreinte($uid);
        $badge = $this->badges->parEmpreinte($empreinte);

        if ($badge === null) {
            $attente = $horsLigne ? null : $this->badges->associationEnAttente((int) $borne['id']);
            if ($attente !== null) {
                $this->badges->associer((int) $attente['id'], $empreinte, substr($uid, -4));
                $this->audit->journaliser('badge.associer', 'badge', (int) $attente['id'], ['borne' => (int) $borne['id']]);

                return ['etat' => 'association', 'message' => 'Badge ' . $attente['numero'] . ' associé à ' . self::nomCourt($attente)];
            }

            throw new PointageRefuseException('Badge inconnu. Contactez votre chef d\'équipe.', 'badge_inconnu');
        }

        if ($badge['statut'] !== 'actif') {
            if (in_array($badge['statut'], ['vole', 'perdu'], true)) {
                $this->alerterBadgeDeclare($badge, $borne);
            }
            throw new PointageRefuseException('Badge inactif, contactez votre chef d\'équipe.', 'badge_' . $badge['statut']);
        }

        $verrou = Clock::fromSql($badge['verrouille_jusqu_a'] === null ? null : (string) $badge['verrouille_jusqu_a']);
        if ($verrou !== null && $verrou > Clock::now() && !$horsLigne) {
            $minutes = (int) ceil(($verrou->getTimestamp() - Clock::now()->getTimestamp()) / 60);
            throw new PointageRefuseException(sprintf('Badge verrouillé. Réessayez dans %d min.', $minutes), 'badge_verrouille');
        }

        $agentId = (int) $badge['agent_id'];
        $indicateurs = [];

        if ((bool) $borne['badge_pin_requis']) {
            if ($badge['pin_hash'] === null || (bool) $badge['pin_a_changer']) {
                throw new PointageRefuseException('Définissez votre code PIN dans l\'application, rubrique Profil.', 'pin_non_defini');
            }
            if ($pin === null || $pin === '') {
                return ['etat' => 'pin', 'prenom' => self::nomCourt($badge)];
            }
            if (!password_verify($pin, (string) $badge['pin_hash'])) {
                if ($horsLigne) {
                    $indicateurs[] = 'pin_hors_ligne';
                    $this->alerterPinHorsLigne($badge, $borne);
                } else {
                    $this->echecPin($badge, $borne);
                }
            }
        }

        $resultat = $this->pointage->pointerBadge(
            $borne,
            (int) $badge['id'],
            $agentId,
            $uuid,
            $horsLigne && $horodatageBorne !== null ? $horodatageBorne : Clock::now(),
            $horsLigne,
            $indicateurs,
        );
        $this->badges->enregistrerUtilisation((int) $badge['id']);
        $this->audit->journaliser('badge.utiliser', 'badge', (int) $badge['id'], ['borne' => (int) $borne['id'], 'hors_ligne' => $horsLigne]);

        return [
            'etat' => 'enregistre',
            'prenom' => self::nomCourt($badge),
            'type' => $resultat['type'] ?? null,
            'heure' => $resultat['heure'] ?? null,
            'statut' => $resultat['statut'] ?? null,
            'idempotent' => $resultat['idempotent'] ?? false,
        ];
    }

    /**
     * @return int identifiant du badge émis
     */
    public function emettre(int $agentId, string $numero, int $auteurId, ?int $borneId): int
    {
        $numero = strtoupper(trim($numero));
        if (preg_match('/^B-\d{6}$/', $numero) !== 1) {
            throw ValidationException::champ('numero', 'Le numéro doit avoir la forme B-000123.');
        }
        if ($this->badges->numeroUtilise($numero)) {
            throw ValidationException::champ('numero', 'Ce numéro de badge est déjà attribué.');
        }
        if ($this->badges->actifPourAgent($agentId) !== null) {
            throw ValidationException::champ('numero', 'L\'agent a déjà un badge actif. Désactivez-le avant d\'en émettre un nouveau.');
        }

        $carte = $this->conformite->carteProCourante($agentId);
        $id = $this->badges->emettre($agentId, $numero, $carte === null ? null : (string) $carte['numero'], $auteurId);
        $this->audit->journaliser('badge.emettre', 'badge', $id, ['agent' => $agentId, 'numero' => $numero]);

        if ($borneId !== null) {
            $this->demarrerAssociation($id, $borneId);
        }

        $utilisateurId = $this->agents->utilisateurDe($agentId);
        if ($utilisateurId !== null) {
            $this->notifications->envoyer(
                [$utilisateurId],
                'badge_emis',
                'Votre badge est prêt',
                'Définissez votre code PIN à 4 chiffres avant votre premier badgeage.',
                '/profil/badge',
            );
        }

        return $id;
    }

    public function demarrerAssociation(int $badgeId, int $borneId): void
    {
        $this->badges->demarrerAssociation($badgeId, $borneId, Clock::now()->modify('+' . self::DUREE_ASSOCIATION_S . ' seconds'));
        $this->audit->journaliser('badge.association.demarrer', 'badge', $badgeId, ['borne' => $borneId]);
    }

    public function terminer(int $badgeId, string $statut, ?string $motif): void
    {
        if (!in_array($statut, ['perdu', 'vole', 'desactive', 'restitue'], true)) {
            throw ValidationException::champ('statut', 'Statut inconnu.');
        }

        $this->badges->terminer($badgeId, $statut, $motif === '' ? null : $motif);
        $this->audit->journaliser('badge.statut', 'badge', $badgeId, ['statut' => $statut]);
    }

    public function reinitialiserPin(int $badgeId): void
    {
        $this->badges->definirPin($badgeId, null);
        $this->audit->journaliser('badge.pin.reinitialiser', 'badge', $badgeId);
    }

    /**
     * Code PIN choisi par l'agent dans son application.
     */
    public function definirPin(int $agentId, string $pin, string $confirmation): void
    {
        $badge = $this->badges->actifPourAgent($agentId) ?? throw ValidationException::champ('pin', 'Vous n\'avez pas de badge actif.');

        if (preg_match('/^\d{4}$/', $pin) !== 1) {
            throw ValidationException::champ('pin', 'Le code PIN comporte exactement 4 chiffres.');
        }
        if (!hash_equals($pin, $confirmation)) {
            throw ValidationException::champ('pin_confirmation', 'Les deux saisies ne correspondent pas.');
        }
        if (in_array($pin, self::PINS_INTERDITS, true)) {
            throw ValidationException::champ('pin', 'Ce code est trop facile à deviner. Choisissez-en un autre.');
        }

        $this->badges->definirPin((int) $badge['id'], password_hash($pin, PASSWORD_DEFAULT));
        $this->audit->journaliser('badge.pin.definir', 'badge', (int) $badge['id']);
    }

    /**
     * Déclaration de perte par l'agent lui-même.
     */
    public function declarerPerte(int $agentId): void
    {
        $badge = $this->badges->actifPourAgent($agentId) ?? throw ValidationException::champ('badge', 'Vous n\'avez pas de badge actif.');
        $this->terminer((int) $badge['id'], 'perdu', 'Déclaré par l\'agent');

        $this->notifications->envoyer(
            $this->destinataires->rh(),
            'badge_perdu',
            'Badge déclaré perdu',
            'Le badge ' . $badge['numero'] . ' a été déclaré perdu par son titulaire. Il est désactivé.',
            '/admin/agents/' . $agentId . '?onglet=badge',
        );
    }

    /**
     * @param array<string, mixed> $badge
     * @param array<string, mixed> $borne
     */
    private function echecPin(array $badge, array $borne): never
    {
        $maximum = $this->parametres->int('badge.pin_essais_max', 5);
        $echecs = $this->badges->enregistrerEchecPin((int) $badge['id']);
        $this->audit->journaliser('badge.pin.echec', 'badge', (int) $badge['id'], ['borne' => (int) $borne['id'], 'echecs' => $echecs]);

        if ($echecs < $maximum) {
            throw new PointageRefuseException(sprintf('Code PIN incorrect (%d essai(s) restant(s)).', $maximum - $echecs), 'pin_incorrect');
        }

        $minutes = $this->parametres->int('badge.verrouillage_min', 15);
        $this->badges->verrouiller((int) $badge['id'], Clock::now()->modify('+' . $minutes . ' minutes'));
        $this->notifications->envoyer(
            $this->destinataires->pourSite((int) $borne['site_id']),
            'badge_verrouille',
            'Badge verrouillé après des codes PIN erronés',
            sprintf('Le badge %s (%s) a été verrouillé %d min sur %s.', $badge['numero'], self::nomCourt($badge), $minutes, $borne['site_nom']),
            '/admin/agents/' . $badge['agent_id'] . '?onglet=badge',
        );

        throw new PointageRefuseException(sprintf('Trop d\'essais : badge verrouillé %d min.', $minutes), 'badge_verrouille');
    }

    /**
     * @param array<string, mixed> $badge
     * @param array<string, mixed> $borne
     */
    private function alerterBadgeDeclare(array $badge, array $borne): void
    {
        $this->audit->journaliser('badge.presente_inactif', 'badge', (int) $badge['id'], ['borne' => (int) $borne['id'], 'statut' => $badge['statut']]);
        $this->notifications->envoyer(
            $this->destinataires->pourSite((int) $borne['site_id']),
            'badge_declare_presente',
            'Badge ' . ($badge['statut'] === 'vole' ? 'volé' : 'perdu') . ' présenté à une borne',
            sprintf('Le badge %s a été présenté sur %s (%s).', $badge['numero'], $borne['site_nom'], $borne['libelle']),
            '/admin/agents/' . $badge['agent_id'] . '?onglet=badge',
            $badge['statut'] === 'vole' ? 'notification' : null,
        );
    }

    /**
     * @param array<string, mixed> $badge
     * @param array<string, mixed> $borne
     */
    private function alerterPinHorsLigne(array $badge, array $borne): void
    {
        $this->notifications->envoyer(
            $this->destinataires->pourSite((int) $borne['site_id']),
            'badge_pin_hors_ligne',
            'Code PIN refusé lors d\'un badgeage hors ligne',
            sprintf('Un badgeage du badge %s sur %s comportait un code PIN erroné. Le pointage est à vérifier.', $badge['numero'], $borne['site_nom']),
            '/admin/pointages?statut=a_verifier',
        );
    }

    /**
     * Prénom et initiale du nom : seule information affichée à l'écran de la borne.
     *
     * @param array<string, mixed> $ligne
     */
    private static function nomCourt(array $ligne): string
    {
        return trim($ligne['prenom'] . ' ' . mb_substr((string) $ligne['nom'], 0, 1) . '.');
    }
}
