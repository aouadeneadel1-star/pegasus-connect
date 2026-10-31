<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Réserve un espace à certains rôles (« role:agent,chef »).
 */
final class RequireRole implements Middleware
{
    /**
     * @param list<string> $roles
     */
    public function __construct(private readonly array $roles)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->requireUser()->role->value, $this->roles, true)) {
            throw HttpException::forbidden();
        }

        return $next($request);
    }
}
