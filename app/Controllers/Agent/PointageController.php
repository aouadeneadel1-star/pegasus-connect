<?php

declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Heures\HeuresService;
use App\Services\Pointage\PointageService;

final class PointageController extends Controller
{
    public function scanner(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $geoloc = $this->factory->consentements()->dernier($utilisateur->id, 'geolocalisation');

        return $this->vue('agent/pointer', [
            'service' => $this->factory->servicesTravail()->ouvertPourAgent((int) $utilisateur->agentId),
            'geolocalisation' => $geoloc === null || (bool) $geoloc['accorde'],
        ]);
    }

    public function historique(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;
        [$annee, $mois] = self::mois($request->query('mois'));
        [$du, $au] = HeuresService::bornesMois($annee, $mois);

        $services = array_map(static function (array $service): array {
            $service['indicateurs'] = array_values(array_unique([
                ...PointageService::indicateurs($service['indicateurs_arrivee'] ?? null),
                ...PointageService::indicateurs($service['indicateurs_depart'] ?? null),
            ]));

            return $service;
        }, $this->factory->servicesTravail()->pourAgentEntre($agentId, $du, $au));

        return $this->vue('agent/historique', [
            'annee' => $annee,
            'mois' => $mois,
            'services' => $services,
            'totaux' => $this->factory->heures()->totauxMois([$agentId], $annee, $mois)[$agentId],
        ]);
    }
}
