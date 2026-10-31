<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Log;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AnomalieRepository;
use App\Services\Pointage\PointageRefuseException;
use App\Services\ValidationException;
use DateTimeImmutable;
use Throwable;

/**
 * API JSON de l'application agent (pointage, rondes, synchronisation hors ligne).
 * Les réponses d'erreur portent un code « raison » exploitable par l'interface.
 */
final class AgentApiController extends Controller
{
    private const LOT_MAX = 200;

    public function etat(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $agentId = (int) $utilisateur->agentId;
        $service = $this->factory->servicesTravail()->ouvertPourAgent($agentId);
        $vacation = $this->factory->vacations()->prochaine($agentId);
        $ronde = $this->factory->rondes()->enCoursPourAgent($agentId);

        return $this->json([
            'heure_serveur' => Clock::now()->format(DATE_ATOM),
            'service' => $service === null ? null : [
                'site' => $service['site'],
                'site_id' => (int) $service['site_id'],
                'debut' => Clock::fromSql((string) $service['debut'])?->format(DATE_ATOM),
            ],
            'vacation' => $vacation === null ? null : [
                'site' => $vacation['site'],
                'debut' => Clock::fromSql((string) $vacation['debut'])?->format(DATE_ATOM),
                'fin' => Clock::fromSql((string) $vacation['fin'])?->format(DATE_ATOM),
            ],
            'ronde' => $ronde === null ? null : ['id' => (int) $ronde['id'], 'site' => $ronde['site']],
            'notifications' => $this->factory->notificationsRepository()->nonLues($utilisateur->id),
        ]);
    }

    public function pointer(Request $request): Response
    {
        return $this->executer(fn (): array => $this->factory->pointage()->pointerQr(
            $this->utilisateur($request),
            $this->saisiePointage($request->all()),
            false,
            $request->userAgent(),
        ));
    }

    public function annuler(Request $request): Response
    {
        return $this->executer(fn (): array => $this->factory->pointage()->annuler(
            $this->utilisateur($request),
            $request->intParam('id'),
            (string) $request->input('motif', 'Erreur de type'),
        ));
    }

    public function demarrerRonde(Request $request): Response
    {
        $rondeId = (int) $request->input('ronde_id', '0');

        return $this->executer(fn (): array => $this->factory->rondeService()->demarrer(
            $this->utilisateur($request),
            $rondeId > 0 ? $rondeId : null,
            (string) $request->input('uuid', ''),
        ));
    }

    public function ronde(Request $request): Response
    {
        $ronde = $this->factory->rondes()->trouver($request->intParam('id'));
        if ($ronde === null || (int) $ronde['agent_id'] !== $this->utilisateur($request)->agentId) {
            return $this->json(['erreur' => 'Ronde introuvable.', 'raison' => 'ronde'], 404);
        }

        return $this->json($this->factory->rondeService()->etat((int) $ronde['id']));
    }

    public function passage(Request $request): Response
    {
        return $this->executer(fn (): array => $this->factory->rondeService()->passage(
            $this->utilisateur($request),
            $request->intParam('id'),
            $this->saisiePointage($request->all()),
            false,
        ));
    }

    public function cloturer(Request $request): Response
    {
        return $this->executer(fn (): array => $this->factory->rondeService()->cloturer(
            $this->utilisateur($request),
            $request->intParam('id'),
            $request->input('commentaire'),
        ));
    }

    /**
     * Signalement d'anomalie : formulaire multipart (photos) ou JSON (rejeu hors ligne, sans photo).
     */
    public function anomalie(Request $request): Response
    {
        $saisie = $request->all();
        $erreur = $this->erreurAnomalie($saisie);
        if ($erreur !== null) {
            return $this->json(['erreur' => $erreur, 'raison' => 'validation'], 422);
        }

        return $this->executer(fn (): array => ['id' => $this->factory->rondeService()->signaler(
            $this->utilisateur($request),
            self::saisieAnomalie($saisie),
            $request->files('photos'),
        )]);
    }

    /**
     * Lot d'événements enregistrés hors ligne. Chaque événement est traité indépendamment ;
     * la réponse donne le résultat par UUID pour que l'application vide sa file.
     */
    public function synchroniser(Request $request): Response
    {
        $evenements = $request->raw('evenements');
        if (!is_array($evenements) || count($evenements) > self::LOT_MAX) {
            return $this->json(['erreur' => 'Lot invalide.', 'raison' => 'validation'], 422);
        }

        $utilisateur = $this->utilisateur($request);
        $resultats = [];
        foreach ($evenements as $evenement) {
            $type = is_array($evenement) ? (string) ($evenement['type'] ?? '') : '';
            $charge = is_array($evenement) && is_array($evenement['donnees'] ?? null) ? $evenement['donnees'] : [];
            $uuid = (string) ($charge['uuid'] ?? '');

            try {
                $resultat = match ($type) {
                    'pointage' => $this->factory->pointage()->pointerQr($utilisateur, [...$this->saisiePointage($charge), 'changer_site' => true], true, $request->userAgent()),
                    'passage' => $this->factory->rondeService()->passage($utilisateur, (int) ($charge['ronde_id'] ?? 0), $this->saisiePointage($charge), true),
                    'anomalie' => ['id' => $this->factory->rondeService()->signaler($utilisateur, self::saisieAnomalie($charge), [])],
                    default => throw new PointageRefuseException('Événement inconnu.', 'type'),
                };
                $resultats[] = ['uuid' => $uuid, 'ok' => true, 'resultat' => $resultat];
            } catch (PointageRefuseException | ValidationException $exception) {
                // Refus définitif : l'événement est retiré de la file et l'agent en est informé.
                $resultats[] = ['uuid' => $uuid, 'ok' => false, 'definitif' => true, 'erreur' => $exception->getMessage()];
            } catch (Throwable $exception) {
                Log::exception($exception);
                $resultats[] = ['uuid' => $uuid, 'ok' => false, 'definitif' => false, 'erreur' => 'Erreur temporaire, nouvel essai plus tard.'];
            }
        }

        return $this->json(['resultats' => $resultats]);
    }

    public function planning(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;
        $du = self::instant($request->query('debut')) ?? Clock::now()->modify('-1 day');
        $au = self::instant($request->query('fin')) ?? $du->modify('+14 days');
        if ($au <= $du || $au->getTimestamp() - $du->getTimestamp() > 62 * 86400) {
            return $this->json(['erreur' => 'Période invalide.', 'raison' => 'validation'], 422);
        }

        return $this->json(['vacations' => array_map(static fn (array $v): array => [
            'id' => (int) $v['id'],
            'site' => $v['site'],
            'adresse' => trim($v['adresse'] . ', ' . $v['ville']),
            'debut' => Clock::fromSql((string) $v['debut'])?->format(DATE_ATOM),
            'fin' => Clock::fromSql((string) $v['fin'])?->format(DATE_ATOM),
            'poste' => $v['poste'],
            'pause_minutes' => (int) $v['pause_minutes'],
        ], $this->factory->vacations()->pourAgent($agentId, $du, $au))]);
    }

    public function notifications(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);

        return $this->json([
            'non_lues' => $this->factory->notificationsRepository()->nonLues($utilisateur->id),
            'notifications' => array_map(static fn (array $n): array => [
                'id' => (int) $n['id'],
                'titre' => $n['titre'],
                'message' => $n['message'],
                'lue' => $n['lue_le'] !== null,
                'date' => Clock::fromSql((string) $n['created_at'])?->format(DATE_ATOM),
            ], $this->factory->notificationsRepository()->recentes($utilisateur->id, 20)),
        ]);
    }

    /**
     * @param callable(): array<string, mixed> $action
     */
    private function executer(callable $action): Response
    {
        try {
            return $this->json($action());
        } catch (PointageRefuseException $exception) {
            return $this->json(['erreur' => $exception->getMessage(), 'raison' => $exception->raison], 422);
        } catch (ValidationException $exception) {
            return $this->json(['erreur' => $exception->getMessage(), 'raison' => 'validation', 'erreurs' => $exception->erreurs()], 422);
        }
    }

    /**
     * @param array<string, mixed> $saisie
     * @return array<string, mixed>
     */
    private function saisiePointage(array $saisie): array
    {
        $nombre = static fn (mixed $valeur): ?float => is_numeric($valeur) ? (float) $valeur : null;

        return [
            'jeton' => is_string($saisie['jeton'] ?? null) ? mb_substr($saisie['jeton'], 0, 200) : '',
            'uuid' => is_string($saisie['uuid'] ?? null) ? $saisie['uuid'] : '',
            'lat' => $nombre($saisie['lat'] ?? null),
            'lng' => $nombre($saisie['lng'] ?? null),
            'precision' => $nombre($saisie['precision'] ?? null),
            'geoloc' => is_string($saisie['geoloc'] ?? null) ? $saisie['geoloc'] : null,
            'horodatage_appareil' => is_string($saisie['horodatage_appareil'] ?? null) ? $saisie['horodatage_appareil'] : null,
            'appareil_uuid' => is_string($saisie['appareil_uuid'] ?? null) ? $saisie['appareil_uuid'] : null,
            'changer_site' => filter_var($saisie['changer_site'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param array<string, mixed> $saisie
     */
    private function erreurAnomalie(array $saisie): ?string
    {
        return match (true) {
            !array_key_exists((string) ($saisie['categorie'] ?? ''), AnomalieRepository::CATEGORIES) => 'Choisissez une catégorie.',
            !array_key_exists((string) ($saisie['gravite'] ?? ''), AnomalieRepository::GRAVITES) => 'Choisissez une gravité.',
            trim((string) ($saisie['description'] ?? '')) === '' => 'Décrivez ce que vous avez constaté.',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $saisie
     * @return array{categorie: string, gravite: string, description: string, uuid: string, ronde_id: ?int, point_id: ?int}
     */
    private static function saisieAnomalie(array $saisie): array
    {
        $entier = static fn (mixed $valeur): ?int => is_numeric($valeur) && (int) $valeur > 0 ? (int) $valeur : null;
        $categorie = (string) ($saisie['categorie'] ?? '');
        $gravite = (string) ($saisie['gravite'] ?? '');
        if (!array_key_exists($categorie, AnomalieRepository::CATEGORIES) || !array_key_exists($gravite, AnomalieRepository::GRAVITES)) {
            throw ValidationException::champ('categorie', 'Signalement incomplet.');
        }

        return [
            'categorie' => $categorie,
            'gravite' => $gravite,
            'description' => (string) ($saisie['description'] ?? ''),
            'uuid' => (string) ($saisie['uuid'] ?? ''),
            'ronde_id' => $entier($saisie['ronde_id'] ?? null),
            'point_id' => $entier($saisie['point_id'] ?? null),
        ];
    }

    private static function instant(?string $valeur): ?DateTimeImmutable
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($valeur, Clock::local()))->setTimezone(Clock::utc());
        } catch (Throwable) {
            return null;
        }
    }
}
