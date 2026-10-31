<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Activé par bin/deploy.sh pendant une mise à jour (fichier storage/maintenance).
 * Les appels de l'application mobile reçoivent un 503 et restent dans leur file hors ligne.
 */
final class MaintenanceMode implements Middleware
{
    public function __construct(private readonly string $fichierDrapeau)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!is_file($this->fichierDrapeau)) {
            return $next($request);
        }

        $response = $request->wantsJson()
            ? Response::json(['erreur' => 'Maintenance en cours. Réessayez dans quelques minutes.'], 503)
            : Response::html(
                '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Maintenance</title>'
                . '<p>Pegasus Connect est en cours de mise à jour. Réessayez dans quelques minutes.</p></html>',
                503,
            );

        return $response->withHeader('Retry-After', '120');
    }
}
