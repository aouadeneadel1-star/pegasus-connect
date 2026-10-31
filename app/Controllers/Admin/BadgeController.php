<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ValidationException;

final class BadgeController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = ['q' => (string) $request->query('q', ''), 'statut' => (string) $request->query('statut', '')];

        return $this->vue('admin/badges', [
            'badges' => $this->factory->badges()->lister($filtres),
            'filtres' => $filtres,
            'prochainNumero' => $this->factory->badges()->prochainNumero(),
        ]);
    }

    public function emettre(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $agentId = $request->intParam('id');
        $this->agentAccessible($request, $agentId);
        $retour = '/admin/agents/' . $agentId . '?onglet=badge';

        $borneId = (int) $request->input('borne_id', '0');
        try {
            $this->factory->badgeService()->emettre($agentId, (string) $request->input('numero', ''), $utilisateur->id, $borneId > 0 ? $this->borneAutorisee($agentId, $borneId) : null);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur($retour, $exception->getMessage());
        }

        return $this->rediriger($retour, $borneId > 0 ? 'Badge émis. Présentez la carte sur la borne choisie dans les 2 minutes.' : 'Badge émis.');
    }

    public function associer(Request $request): Response
    {
        $badge = $this->badge($request);
        $agentId = (int) $badge['agent_id'];
        $borneId = $this->borneAutorisee($agentId, (int) $request->input('borne_id', '0'));
        $this->factory->badgeService()->demarrerAssociation((int) $badge['id'], $borneId);

        return $this->rediriger('/admin/agents/' . $agentId . '?onglet=badge', 'La borne attend la carte pendant 2 minutes.');
    }

    /**
     * Suivi de l'association en cours (sondage depuis la fiche agent).
     */
    public function etat(Request $request): Response
    {
        $badge = $this->badge($request);
        $expiration = Clock::fromSql($badge['association_expire_le'] === null ? null : (string) $badge['association_expire_le']);

        return $this->json([
            'associe' => $badge['uid_fin'] !== null && $badge['association_borne_id'] === null,
            'en_attente' => $expiration !== null && $expiration > Clock::now(),
            'uid_fin' => $badge['uid_fin'],
        ]);
    }

    public function reinitialiserPin(Request $request): Response
    {
        $badge = $this->badge($request);
        $this->factory->badgeService()->reinitialiserPin((int) $badge['id']);
        if ($badge['utilisateur_id'] !== null) {
            $this->factory->notifications()->envoyer([(int) $badge['utilisateur_id']], 'badge_pin', 'Définissez un nouveau code PIN pour votre badge', null, '/profil/badge');
        }

        return $this->rediriger('/admin/agents/' . $badge['agent_id'] . '?onglet=badge', 'Code PIN réinitialisé : l\'agent doit en définir un nouveau.');
    }

    public function statut(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $badge = $this->badge($request);
        $statut = (string) $request->input('statut');
        if (!$utilisateur->peut('badges.gerer') && !in_array($statut, ['perdu', 'vole'], true)) {
            throw HttpException::forbidden();
        }

        try {
            $this->factory->badgeService()->terminer((int) $badge['id'], $statut, mb_substr(trim((string) $request->input('motif', '')), 0, 255));
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/agents/' . $badge['agent_id'] . '?onglet=badge', $exception->getMessage());
        }

        return $this->rediriger('/admin/agents/' . $badge['agent_id'] . '?onglet=badge', 'Badge désactivé : il est refusé par toutes les bornes.');
    }

    /**
     * @return array<string, mixed>
     */
    private function badge(Request $request): array
    {
        $badge = $this->factory->badges()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        if ($badge['agent_id'] !== null) {
            $this->agentAccessible($request, (int) $badge['agent_id']);
        }

        return $badge;
    }

    private function agentAccessible(Request $request, int $agentId): void
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'agents.voir');
        if ($this->factory->agents()->trouver($agentId) === null || !$perimetre->couvreAgent($agentId)) {
            throw HttpException::notFound();
        }
    }

    /**
     * La borne doit être active, équipée d'un lecteur et située sur un site de l'agent.
     */
    private function borneAutorisee(int $agentId, int $borneId): int
    {
        $bornes = $this->factory->bornes()->avecLecteurPourSites($this->factory->agents()->sitesAffectes($agentId));
        if (!in_array($borneId, array_map(static fn (array $b): int => (int) $b['id'], $bornes), true)) {
            throw HttpException::notFound();
        }

        return $borneId;
    }
}
