<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Role;
use App\Services\Planning\PlanningService;
use App\Services\ValidationException;
use DateTimeImmutable;

/**
 * Planning hebdomadaire par site : saisie des vacations, duplication, publication.
 */
final class PlanningController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'planning.voir');
        $sites = $this->factory->sites()->choix($perimetre);
        if ($sites === []) {
            return $this->vue('admin/planning', ['sites' => [], 'site' => null]);
        }

        $siteId = (int) $request->query('site', (string) $sites[0]['id']);
        if (!in_array($siteId, array_column($sites, 'id'), true)) {
            $siteId = (int) $sites[0]['id'];
        }
        $lundi = $this->lundi($request->query('semaine'));
        $dimanche = $lundi->modify('+7 days');

        $vacations = $this->factory->vacations()->pourSites([$siteId], $lundi, $dimanche);
        $agents = [];
        foreach ($this->factory->sites()->agentsAffectes($siteId) as $agent) {
            $agents[(int) $agent['id']] = $agent['nom'] . ' ' . $agent['prenom'];
        }
        $grille = [];
        foreach ($vacations as $vacation) {
            $agentId = (int) $vacation['agent_id'];
            $agents[$agentId] ??= $vacation['nom'] . ' ' . $vacation['prenom'];
            $jour = Clock::fromSql((string) $vacation['debut'])->setTimezone(Clock::local())->format('Y-m-d');
            $grille[$agentId][$jour][] = $vacation;
        }
        asort($agents);

        $jours = [];
        for ($i = 0; $i < 7; ++$i) {
            $jours[] = $lundi->setTimezone(Clock::local())->modify('+' . $i . ' days');
        }

        $peutGerer = $utilisateur->peut('planning.gerer') && $this->factory->autorisation()->perimetre($utilisateur, 'planning.gerer')->couvreSite($siteId);
        $saisie = Session::getFlash('planning_saisie');

        return $this->vue('admin/planning', [
            'sites' => $sites,
            'site' => $this->factory->sites()->trouver($siteId),
            'jours' => $jours,
            'agents' => $agents,
            'grille' => $grille,
            'lundi' => $lundi->setTimezone(Clock::local())->format('Y-m-d'),
            'precedente' => $lundi->setTimezone(Clock::local())->modify('-7 days')->format('Y-m-d'),
            'suivante' => $lundi->setTimezone(Clock::local())->modify('+7 days')->format('Y-m-d'),
            'brouillons' => $this->factory->vacations()->nombreBrouillons([$siteId], $lundi, $dimanche),
            'peutGerer' => $peutGerer,
            'peutPublier' => $peutGerer && $this->peutPublier($request),
            'agentsPlanifiables' => $peutGerer ? $this->agentsPlanifiables($request) : [],
            'saisie' => is_array($saisie) ? $saisie : null,
        ]);
    }

    public function enregistrer(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $siteId = (int) $request->input('site_id', '0');
        $lundi = PlanningService::debutSemaine(self::instantLocal((string) $request->input('date', ''), '12:00') ?? Clock::now());
        $retour = '/admin/planning?site=' . $siteId . '&semaine=' . $lundi->setTimezone(Clock::local())->format('Y-m-d');
        $this->verifierSite($request, $siteId);

        $validation = Validator::make($request->all(), [
            'agent_id' => 'required|int',
            'date' => 'required|date',
            'heure_debut' => 'required|time',
            'heure_fin' => 'required|time',
            'pause_minutes' => 'int|between:0,240',
            'poste' => 'max:60',
            'commentaire' => 'max:255',
            'motif' => 'max:255',
        ], ['agent_id' => 'L\'agent', 'date' => 'La date', 'heure_debut' => 'L\'heure de début', 'heure_fin' => 'L\'heure de fin', 'pause_minutes' => 'La pause']);
        if ($validation->fails()) {
            return $this->retourSaisie($request, $retour, [self::premiereErreur($validation)]);
        }

        $donnees = $validation->validated();
        $agentId = (int) $donnees['agent_id'];
        if (!in_array($agentId, array_keys($this->agentsPlanifiables($request)), true)) {
            throw HttpException::notFound();
        }

        $debut = self::instantLocal((string) $donnees['date'], (string) $donnees['heure_debut']);
        $fin = self::instantLocal((string) $donnees['date'], (string) $donnees['heure_fin']);
        if ($debut === null || $fin === null) {
            return $this->retourSaisie($request, $retour, ['Date ou heure invalide.']);
        }
        // Une fin antérieure ou égale au début désigne le lendemain (vacation de nuit).
        if ($fin <= $debut) {
            $fin = self::instantLocal((new DateTimeImmutable((string) $donnees['date']))->modify('+1 day')->format('Y-m-d'), (string) $donnees['heure_fin']);
        }

        $vacationId = (int) $request->input('vacation_id', '0');
        if ($vacationId > 0) {
            $existante = $this->factory->vacations()->trouver($vacationId);
            if ($existante === null || $existante['statut'] === 'annulee') {
                throw HttpException::notFound();
            }
            $this->verifierSite($request, (int) $existante['site_id']);
        }

        try {
            $resultat = $this->factory->planning()->enregistrer([
                'agent_id' => $agentId,
                'site_id' => $siteId,
                'debut' => $debut,
                'fin' => $fin,
                'pause_minutes' => (int) ($donnees['pause_minutes'] ?? 0),
                'poste' => $donnees['poste'],
                'commentaire' => $donnees['commentaire'],
            ], $vacationId > 0 ? $vacationId : null, $utilisateur->id, $request->boolean('forcer'), $donnees['motif']);
        } catch (ValidationException $exception) {
            $erreurs = $exception->erreurs();
            if (isset($erreurs['avertissements'])) {
                return $this->retourSaisie($request, $retour, explode("\n", $erreurs['avertissements']), true);
            }

            return $this->retourSaisie($request, $retour, [$exception->getMessage()]);
        }

        return $this->rediriger($retour, $resultat['avertissements'] === [] ? 'Vacation enregistrée.' : 'Vacation enregistrée malgré les alertes (motif conservé).');
    }

    public function supprimer(Request $request): Response
    {
        $vacation = $this->factory->vacations()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $this->verifierSite($request, (int) $vacation['site_id']);
        $this->factory->planning()->supprimer((int) $vacation['id']);
        $lundi = PlanningService::debutSemaine(Clock::fromSql((string) $vacation['debut']));

        return $this->rediriger(
            '/admin/planning?site=' . $vacation['site_id'] . '&semaine=' . $lundi->setTimezone(Clock::local())->format('Y-m-d'),
            $vacation['statut'] === 'publiee' ? 'Vacation annulée : l\'agent est prévenu.' : 'Vacation supprimée.',
        );
    }

    public function dupliquer(Request $request): Response
    {
        $siteId = (int) $request->input('site_id', '0');
        $this->verifierSite($request, $siteId);
        $lundi = $this->lundi($request->input('semaine'));
        $resultat = $this->factory->planning()->dupliquerSemaine([$siteId], $lundi, $this->utilisateur($request)->id);
        $suivante = $lundi->setTimezone(Clock::local())->modify('+7 days')->format('Y-m-d');

        return $this->rediriger(
            '/admin/planning?site=' . $siteId . '&semaine=' . $suivante,
            sprintf('%d vacation(s) copiée(s) en brouillon sur la semaine suivante%s.', $resultat['copiees'], $resultat['ignorees'] > 0 ? ', ' . $resultat['ignorees'] . ' ignorée(s) pour conflit' : ''),
        );
    }

    public function publier(Request $request): Response
    {
        $siteId = (int) $request->input('site_id', '0');
        $this->verifierSite($request, $siteId);
        if (!$this->peutPublier($request)) {
            throw HttpException::forbidden();
        }
        $lundi = $this->lundi($request->input('semaine'));
        $resultat = $this->factory->planning()->publier([$siteId], $lundi, $lundi->modify('+7 days'), $this->utilisateur($request)->id);

        return $this->rediriger(
            '/admin/planning?site=' . $siteId . '&semaine=' . $lundi->setTimezone(Clock::local())->format('Y-m-d'),
            $resultat['vacations'] === 0 ? 'Aucun brouillon à publier.' : sprintf('%d vacation(s) publiée(s), %d agent(s) prévenu(s).', $resultat['vacations'], $resultat['agents']),
        );
    }

    /**
     * @param list<string> $messages
     */
    private function retourSaisie(Request $request, string $retour, array $messages, bool $forcable = false): Response
    {
        $saisie = $request->only(['vacation_id', 'agent_id', 'date', 'heure_debut', 'heure_fin', 'pause_minutes', 'poste', 'commentaire', 'motif']);
        Session::flash('planning_saisie', [...$saisie, 'messages' => $messages, 'forcable' => $forcable]);

        return Response::redirect($retour);
    }

    private function verifierSite(Request $request, int $siteId): void
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'planning.gerer');
        if ($siteId <= 0 || $this->factory->sites()->trouver($siteId) === null || !$perimetre->couvreSite($siteId)) {
            throw HttpException::notFound();
        }
    }

    private function peutPublier(Request $request): bool
    {
        return $this->utilisateur($request)->role !== Role::Chef || $this->factory->parametres()->bool('planning.publication_par_chef', true);
    }

    /**
     * @return array<int, string>
     */
    private function agentsPlanifiables(Request $request): array
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'planning.gerer');
        $agents = [];
        foreach ($this->factory->agents()->choix($perimetre) as $agent) {
            $agents[$agent['id']] = $agent['nom'];
        }

        return $agents;
    }

    private function lundi(?string $semaine): DateTimeImmutable
    {
        $date = self::date($semaine, Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));

        return PlanningService::debutSemaine(new DateTimeImmutable($date . ' 12:00', Clock::local()));
    }

    private static function instantLocal(string $date, string $heure): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $heure) !== 1) {
            return null;
        }

        return Clock::fromLocal($date . ' ' . substr($heure, 0, 5));
    }
}
