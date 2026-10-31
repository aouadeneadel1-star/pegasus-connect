<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);

        return $this->vue('notifications/index', [
            ...self::espace($request),
            'notifications' => $this->factory->notificationsRepository()->recentes($utilisateur->id, 100),
        ]);
    }

    /**
     * Ouvre la notification : marquée lue, puis redirection vers la page concernée.
     */
    public function ouvrir(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $id = $request->intParam('id');
        $notification = $this->factory->notificationsRepository()->trouver($id, $utilisateur->id) ?? throw HttpException::notFound();

        $this->factory->notificationsRepository()->marquerLue($id, $utilisateur->id);
        $lien = (string) ($notification['lien'] ?? '');

        // Seuls les chemins internes sont suivis.
        return Response::redirect(str_starts_with($lien, '/') && !str_starts_with($lien, '//') ? $lien : self::espace($request)['prefixe'] . '/notifications');
    }

    public function toutLire(Request $request): Response
    {
        $this->factory->notificationsRepository()->toutMarquerLu($this->utilisateur($request)->id);

        return $request->wantsJson() ? $this->json(['ok' => true]) : $this->rediriger(self::espace($request)['prefixe'] . '/notifications');
    }
}
