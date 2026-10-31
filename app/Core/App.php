<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\Authenticate;
use App\Core\Middleware\BorneAuth;
use App\Core\Middleware\MaintenanceMode;
use App\Core\Middleware\Middleware;
use App\Core\Middleware\RequireConsent;
use App\Core\Middleware\RequirePermission;
use App\Core\Middleware\RequireRole;
use App\Core\Middleware\RequireTwoFactor;
use App\Core\Middleware\Throttle;
use App\Core\Middleware\VerifyCsrf;
use App\Factory;
use Closure;
use LogicException;
use Throwable;

final class App
{
    public function __construct(private readonly Factory $factory)
    {
    }

    public static function boot(string $racine): Factory
    {
        Env::load($racine . '/.env');
        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');

        $factory = new Factory($racine);
        Log::configure($factory->storage() . '/logs');

        $debug = Env::bool('APP_DEBUG');
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);
        set_error_handler(static function (int $niveau, string $message, string $fichier, int $ligne): bool {
            if ((error_reporting() & $niveau) === 0) {
                return false;
            }
            throw new \ErrorException($message, 0, $niveau, $fichier, $ligne);
        });

        return $factory;
    }

    public function handle(Request $request): Response
    {
        try {
            Session::start(str_starts_with($this->factory->appUrl(), 'https://'), $this->factory->storage() . '/sessions');
            $this->factory->audit()->definirContexte(null, $request->ip(), $request->userAgent());

            $router = new Router();
            (require $this->factory->racine() . '/app/config/routes.php')($router);
            $route = $router->match($request->method(), $request->path());
            $request->withParams($route['params']);

            $middleware = array_map(
                fn (string $nom): Middleware => $this->middleware($nom),
                ['maintenance', ...$route['middleware']],
            );

            $response = $this->pipeline($middleware, fn (Request $r): Response => $this->appeler($route['handler'], $r))($request);
        } catch (HttpException $exception) {
            $response = $this->erreur($request, $exception->status(), $exception->getMessage());
        } catch (Throwable $exception) {
            Log::exception($exception);
            $message = Env::bool('APP_DEBUG') ? $exception::class . ' : ' . $exception->getMessage() . ' (' . $exception->getFile() . ':' . $exception->getLine() . ')' : '';
            $response = $this->erreur($request, 500, $message);
        }

        return SecurityHeaders::apply($response, $request->isSecure() || str_starts_with($this->factory->appUrl(), 'https://'));
    }

    /**
     * @param list<Middleware> $middleware
     * @param Closure(Request): Response $coeur
     * @return Closure(Request): Response
     */
    private function pipeline(array $middleware, Closure $coeur): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            static fn (Closure $suivant, Middleware $couche): Closure => static fn (Request $r): Response => $couche->handle($r, $suivant),
            $coeur,
        );
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    private function appeler(array $handler, Request $request): Response
    {
        [$classe, $methode] = $handler;
        $controleur = new $classe($this->factory);

        return $controleur->$methode($request);
    }

    private function middleware(string $definition): Middleware
    {
        [$nom, $arguments] = array_pad(explode(':', $definition, 2), 2, '');
        $liste = $arguments === '' ? [] : explode(',', $arguments);

        return match ($nom) {
            'maintenance' => new MaintenanceMode($this->factory->storage() . '/maintenance'),
            'auth' => new Authenticate($this->factory),
            'consentement' => new RequireConsent($this->factory),
            'csrf' => new VerifyCsrf($this->factory->appUrl()),
            'perm' => new RequirePermission($liste),
            'role' => new RequireRole($liste),
            '2fa' => new RequireTwoFactor(),
            'borne' => new BorneAuth($this->factory),
            'throttle' => new Throttle($this->factory->limiteDebit(), $liste[0], (int) ($liste[1] ?? 10), (int) ($liste[2] ?? 60)),
            default => throw new LogicException(sprintf('Middleware inconnu : %s', $nom)),
        };
    }

    private function erreur(Request $request, int $status, string $message): Response
    {
        $messages = [
            400 => 'La requête est invalide.',
            403 => 'Vous n\'avez pas accès à cette page.',
            404 => 'Cette page n\'existe pas ou n\'est plus disponible.',
            405 => 'Cette action n\'est pas autorisée.',
            419 => 'Votre session a expiré. Rechargez la page.',
            429 => 'Trop de tentatives. Réessayez dans quelques minutes.',
            500 => 'Une erreur est survenue. L\'équipe technique a été prévenue.',
        ];
        // En 500, le message n'est renseigné qu'en mode debug : il complète le texte générique.
        $texte = $status === 500 || $message === '' ? ($messages[$status] ?? $messages[500]) : $message;
        if ($status === 500 && $message !== '') {
            $texte .= ' ' . $message;
        }

        if ($request->wantsJson()) {
            return Response::json(['erreur' => $texte], $status);
        }

        try {
            $html = $this->factory->view()->render('erreurs/page', ['statut' => $status, 'message' => $texte]);
        } catch (Throwable) {
            $html = '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur</title><p>' . htmlspecialchars($texte) . '</p></html>';
        }

        return Response::html($html, $status);
    }
}
