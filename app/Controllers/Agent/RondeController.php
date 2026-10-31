<?php

declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AnomalieRepository;

final class RondeController extends Controller
{
    public function index(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;
        $service = $this->factory->servicesTravail()->ouvertPourAgent($agentId);

        return $this->vue('agent/rondes', [
            'service' => $service,
            'enCours' => $this->factory->rondes()->enCoursPourAgent($agentId),
            'aDemarrer' => $service === null ? [] : $this->factory->rondes()->aDemarrer((int) $service['site_id'], Clock::now()),
            'recentes' => $this->factory->rondes()->recentesPourAgent($agentId, 10),
        ]);
    }

    public function voir(Request $request): Response
    {
        $ronde = $this->factory->rondes()->trouver($request->intParam('id'));
        if ($ronde === null || (int) $ronde['agent_id'] !== $this->utilisateur($request)->agentId) {
            throw HttpException::notFound();
        }

        return $this->vue('agent/ronde', [
            'etat' => $this->factory->rondeService()->etat((int) $ronde['id']),
            'geolocalisation' => $this->geolocalisationAutorisee($request),
        ]);
    }

    public function anomalie(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;
        $ronde = $this->factory->rondes()->enCoursPourAgent($agentId);
        $service = $this->factory->servicesTravail()->ouvertPourAgent($agentId);
        $siteId = $ronde !== null ? (int) $ronde['site_id'] : ($service === null ? null : (int) $service['site_id']);

        return $this->vue('agent/anomalie', [
            'ronde' => $ronde,
            'service' => $service,
            'points' => $siteId === null ? [] : $this->factory->pointsControle()->pourSite($siteId),
            'pointId' => (int) $request->query('point', '0'),
            'categories' => AnomalieRepository::CATEGORIES,
            'gravites' => AnomalieRepository::GRAVITES,
        ]);
    }

    private function geolocalisationAutorisee(Request $request): bool
    {
        $consentement = $this->factory->consentements()->dernier($this->utilisateur($request)->id, 'geolocalisation');

        return $consentement === null || (bool) $consentement['accorde'];
    }
}
