<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Protège les actions les plus sensibles (révélation du NIR, export RGPD) : même pour
 * un rôle où la double authentification est optionnelle, elle doit être active.
 */
final class RequireTwoFactor implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->requireUser()->totpActif) {
            throw HttpException::forbidden('Activez la double authentification pour accéder à cette fonction.');
        }

        return $next($request);
    }
}
