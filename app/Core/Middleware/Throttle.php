<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Securite\LimiteDebit;
use Closure;

/**
 * Limite le nombre de requêtes par adresse IP (« throttle:connexion,10,60 »).
 */
final class Throttle implements Middleware
{
    public function __construct(
        private readonly LimiteDebit $limites,
        private readonly string $compartiment,
        private readonly int $maximum,
        private readonly int $fenetreSecondes,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() === 'POST'
            && !$this->limites->essayer($this->compartiment . ':' . $request->ip(), $this->maximum, $this->fenetreSecondes)) {
            throw HttpException::tooManyRequests();
        }

        return $next($request);
    }
}
