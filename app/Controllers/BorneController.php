<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Log;
use App\Core\Middleware\BorneAuth;
use App\Core\Request;
use App\Core\Response;
use App\Services\Pointage\PointageRefuseException;
use App\Services\Qr\JetonQr;
use DateTimeImmutable;
use Throwable;

/**
 * Borne de pointage (cahier des charges, § 3.4.1 et § 3.4.8) : tablette fixe sur le site,
 * qui affiche le QR dynamique et lit les badges NFC. Elle n'a pas de session utilisateur.
 */
final class BorneController extends Controller
{
    private const DUREE_COOKIE = 400 * 86400;

    public function afficher(Request $request): Response
    {
        $site = $this->factory->sites()->trouverParCode($request->param('code'));
        if ($site === null || !(bool) $site['actif']) {
            throw HttpException::notFound();
        }

        $jeton = $request->cookie(BorneAuth::COOKIE_SECURISE) ?? $request->cookie(BorneAuth::COOKIE);
        $borne = $jeton === null ? null : $this->factory->bornes()->trouverParJeton($jeton);
        $enrolee = $borne !== null && (int) $borne['site_id'] === (int) $site['id'];

        return $this->vue('borne/borne', [
            'site' => $site,
            'borne' => $enrolee ? $borne : null,
            'badgeActif' => $enrolee && (bool) $site['badge_nfc_actif'] && $borne['lecteur_nfc'] !== 'aucun',
            'pinRequis' => (bool) $site['badge_pin_requis'],
            'dureeConfirmation' => $this->factory->parametres()->int('badge.confirmation_affichage_s', 4),
        ])->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Enrôlement par le code à usage unique saisi sur la tablette. La borne reçoit son jeton
     * d'appareil (cookie) et sa clé, qu'elle conserve pour générer ses QR même sans réseau.
     */
    public function enrolement(Request $request): Response
    {
        $site = $this->factory->sites()->trouverParCode($request->param('code'));
        if ($site === null || !(bool) $site['actif']) {
            return $this->json(['erreur' => 'Site inconnu.'], 404);
        }

        $code = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $request->input('code', '')));
        $borne = strlen($code) === 8 ? $this->factory->bornes()->trouverParCodeEnrolement((int) $site['id'], $code) : null;
        if ($borne === null) {
            $this->factory->audit()->journaliser('borne.enrolement.echec', 'site', (int) $site['id']);

            return $this->json(['erreur' => 'Code invalide ou expiré. Demandez un nouveau code à l\'administrateur.'], 422);
        }

        $jeton = bin2hex(random_bytes(32));
        $version = $this->factory->bornes()->enroler((int) $borne['id'], $jeton, null, $request->ip(), $request->userAgent());
        $this->factory->audit()->journaliser('borne.enroler', 'borne', (int) $borne['id'], ['site' => (int) $site['id']]);

        $https = str_starts_with($this->factory->appUrl(), 'https://');

        return $this->json([
            'borne_id' => (int) $borne['id'],
            'site_id' => (int) $site['id'],
            'cle' => base64_encode($this->factory->jetonsQr()->cleBorne((int) $borne['id'], $version)),
            'cle_version' => $version,
            'fenetre_s' => JetonQr::FENETRE_SECONDES,
        ])->withCookie($https ? BorneAuth::COOKIE_SECURISE : BorneAuth::COOKIE, $jeton, [
            'expires' => Clock::now()->getTimestamp() + self::DUREE_COOKIE,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function battement(Request $request): Response
    {
        $borne = $this->borne($request);
        $lecteur = $request->raw('lecteur_nfc_ok');
        $this->factory->bornes()->battement((int) $borne['id'], $request->ip(), is_bool($lecteur) ? $lecteur : null);
        $attente = $this->factory->badges()->associationEnAttente((int) $borne['id']);

        return $this->json([
            'heure_serveur' => (int) floor(microtime(true) * 1000),
            'cle_version' => (int) $borne['cle_version'],
            'statut' => $borne['statut'],
            'association' => $attente === null ? null : [
                'numero' => $attente['numero'],
                'expire' => Clock::fromSql((string) $attente['association_expire_le'])?->format(DATE_ATOM),
            ],
        ]);
    }

    public function badge(Request $request): Response
    {
        $borne = $this->borne($request);

        try {
            $resultat = $this->factory->badgeService()->lecture(
                $borne,
                (string) $request->input('uid', ''),
                self::pin($request->input('pin')),
                (string) $request->input('uuid', ''),
                null,
                false,
            );
        } catch (PointageRefuseException $exception) {
            return $this->json(['etat' => 'refus', 'erreur' => $exception->getMessage(), 'raison' => $exception->raison], 422);
        }

        return $this->json($resultat);
    }

    /**
     * Badgeages enregistrés pendant une coupure réseau. Chaque événement est signé par la borne
     * avec sa clé : un événement modifié ou fabriqué hors de la borne est rejeté.
     */
    public function synchroniser(Request $request): Response
    {
        $borne = $this->borne($request);
        $evenements = $request->raw('evenements');
        if (!is_array($evenements) || count($evenements) > 500) {
            return $this->json(['erreur' => 'Lot invalide.'], 422);
        }

        $cle = $this->factory->jetonsQr()->cleBorne((int) $borne['id'], (int) $borne['cle_version']);
        $retardMax = $this->factory->parametres()->int('badge.hors_ligne_conservation_h', 24) * 3600;
        $resultats = [];

        foreach ($evenements as $evenement) {
            $uuid = is_array($evenement) ? (string) ($evenement['uuid'] ?? '') : '';
            try {
                if (!is_array($evenement)) {
                    throw new PointageRefuseException('Événement illisible.', 'format');
                }
                $uid = (string) ($evenement['uid'] ?? '');
                $horodatage = (string) ($evenement['horodatage_borne'] ?? '');
                $signature = (string) ($evenement['signature'] ?? '');
                if (!hash_equals(JetonQr::signer($uid . '|' . $uuid . '|' . $horodatage, $cle), $signature)) {
                    throw new PointageRefuseException('Signature de la borne invalide.', 'signature');
                }

                $instant = new DateTimeImmutable($horodatage);
                if (Clock::now()->getTimestamp() - $instant->getTimestamp() > $retardMax) {
                    throw new PointageRefuseException('Badgeage trop ancien pour être transmis.', 'trop_ancien');
                }

                $resultat = $this->factory->badgeService()->lecture($borne, $uid, self::pin($evenement['pin'] ?? null), $uuid, $instant, true);
                $resultats[] = ['uuid' => $uuid, 'ok' => true, 'resultat' => $resultat];
            } catch (PointageRefuseException $exception) {
                $resultats[] = ['uuid' => $uuid, 'ok' => false, 'definitif' => true, 'erreur' => $exception->getMessage()];
            } catch (Throwable $exception) {
                Log::exception($exception);
                $resultats[] = ['uuid' => $uuid, 'ok' => false, 'definitif' => false];
            }
        }

        return $this->json(['resultats' => $resultats]);
    }

    /**
     * @return array<string, mixed>
     */
    private function borne(Request $request): array
    {
        $borne = $request->attribute('borne');
        if (!is_array($borne)) {
            throw HttpException::forbidden();
        }

        return $borne;
    }

    private static function pin(mixed $valeur): ?string
    {
        return is_string($valeur) && preg_match('/^\d{4}$/', $valeur) === 1 ? $valeur : null;
    }
}
