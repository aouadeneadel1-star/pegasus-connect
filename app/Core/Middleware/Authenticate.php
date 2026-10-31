<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Factory;
use App\Models\Role;
use App\Repositories\NotificationRepository;
use App\Services\Auth\AuthService;
use Closure;

final class Authenticate implements Middleware
{
    private const CHEMINS_ENROLEMENT = ['/compte/2fa', '/deconnexion'];

    public function __construct(private readonly Factory $factory)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $auth = Session::get(AuthService::SESSION_CLE);
        if (!is_array($auth) || !isset($auth['id'])) {
            return $this->refuser($request, null);
        }

        $ligne = $this->factory->utilisateurs()->trouver((int) $auth['id']);
        if ($ligne === null || $ligne['statut'] !== 'actif' || (int) $ligne['session_version'] !== (int) $auth['version']) {
            Session::clear();

            return $this->refuser($request, 'Votre session a été fermée. Reconnectez-vous.');
        }

        $duree = Role::fromId((int) $ligne['role_id'])->dureeSession();
        $maintenant = time();
        if ($maintenant - (int) $auth['activite'] > $duree['inactivite'] || $maintenant - (int) $auth['debut'] > $duree['maximum']) {
            Session::clear();

            return $this->refuser($request, 'Votre session a expiré. Reconnectez-vous.');
        }

        $auth['activite'] = $maintenant;
        Session::set(AuthService::SESSION_CLE, $auth);

        $utilisateur = $this->factory->autorisation()->utilisateurConnecte($ligne);
        $request->setUser($utilisateur);
        $this->factory->audit()->definirContexte($utilisateur, $request->ip(), $request->userAgent());
        $this->factory->view()->share('utilisateur', $utilisateur);
        $this->factory->view()->share(
            'notificationsNonLues',
            $this->factory->repository(NotificationRepository::class)->nonLues($utilisateur->id),
        );

        if (($auth['enrolement'] ?? false) === true && !in_array($request->path(), self::CHEMINS_ENROLEMENT, true)) {
            return $request->wantsJson()
                ? Response::json(['erreur' => 'Activez d\'abord la double authentification.'], 403)
                : Response::redirect('/compte/2fa');
        }

        return $next($request);
    }

    private function refuser(Request $request, ?string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['erreur' => $message ?? 'Connexion requise.'], 401);
        }

        if ($message !== null) {
            Session::flash('info', $message);
        }

        $retour = $request->method() === 'GET' && $request->path() !== '/' ? '?retour=' . rawurlencode($request->path()) : '';

        return Response::redirect('/connexion' . $retour);
    }
}
