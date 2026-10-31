<?php

declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Request;
use App\Core\Response;

final class AccueilController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $agentId = (int) $utilisateur->agentId;
        $service = $this->factory->servicesTravail()->ouvertPourAgent($agentId);
        $siteId = $service === null ? null : (int) $service['site_id'];

        return $this->vue('agent/accueil', [
            'service' => $service,
            'vacation' => $this->factory->vacations()->prochaine($agentId),
            'ronde' => $this->factory->rondes()->enCoursPourAgent($agentId),
            'rondesADemarrer' => $siteId === null ? [] : $this->factory->rondes()->aDemarrer($siteId, Clock::now()),
            'documentsNonLus' => $this->factory->documentsSociete()->nombreNonLus($utilisateur),
            'carte' => $this->factory->conformite()->carteProCourante($agentId),
            'badge' => $this->factory->badges()->actifPourAgent($agentId),
        ]);
    }

    public function plus(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);

        return $this->vue('agent/plus', [
            'accesAdmin' => $utilisateur->peut('pointages.suivre') || $utilisateur->peut('planning.voir'),
            'telephone' => (string) $this->factory->parametres()->get('contact.telephone_permanence', ''),
        ]);
    }

    /**
     * Page servie par le Service Worker quand une page n'est pas en cache et que le réseau manque.
     */
    public function horsLigne(Request $request): Response
    {
        return $this->vue('agent/hors-ligne');
    }
}
