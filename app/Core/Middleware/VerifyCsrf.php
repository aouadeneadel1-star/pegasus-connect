<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

final class VerifyCsrf implements Middleware
{
    public function __construct(private readonly string $appUrl)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'POST' || Csrf::isValid($request, $this->appUrl)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json(['erreur' => 'Votre session a expiré. Rechargez la page.'], 419);
        }

        Session::flash('erreur', 'Le formulaire a expiré. Merci de le renvoyer.');

        // Retour à la page d'origine, sans jamais suivre un Referer externe.
        $origine = parse_url((string) $request->header('Referer'), PHP_URL_PATH);

        return Response::redirect(is_string($origine) && str_starts_with($origine, '/') ? $origine : '/');
    }
}
