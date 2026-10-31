<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Exige au moins une des permissions listées (« perm:agents.voir,agents.gerer »).
 * La portée (soi, équipe, client, tout) est appliquée ensuite dans chaque requête SQL.
 */
final class RequirePermission implements Middleware
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(private readonly array $permissions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->requireUser();
        foreach ($this->permissions as $permission) {
            if ($utilisateur->peut($permission)) {
                return $next($request);
            }
        }

        throw HttpException::forbidden();
    }
}
