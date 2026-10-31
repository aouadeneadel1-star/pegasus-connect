<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Factory;
use Closure;

/**
 * Les agents prennent connaissance de la notice d'information (pointage, géolocalisation,
 * rondes) avant d'utiliser l'application. La géolocalisation reste refusable ensuite.
 */
final class RequireConsent implements Middleware
{
    public function __construct(private readonly Factory $factory)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->requireUser();
        if (!$utilisateur->role->estTerrain() || $request->path() === '/consentements') {
            return $next($request);
        }

        $version = (string) $this->factory->parametres()->get('rgpd.version_notice', '2026-10');
        $dernier = $this->factory->consentements()->dernier($utilisateur->id, 'information_salarie');

        if ($dernier === null || $dernier['version_texte'] !== $version) {
            return $request->wantsJson()
                ? Response::json(['erreur' => 'Prenez connaissance de la notice d\'information.'], 403)
                : Response::redirect('/consentements');
        }

        return $next($request);
    }
}
