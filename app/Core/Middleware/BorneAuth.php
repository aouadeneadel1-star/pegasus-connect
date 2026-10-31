<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Factory;
use Closure;

/**
 * Authentifie une borne par son cookie d'appareil (HttpOnly, SameSite=Strict) et vérifie
 * qu'elle appartient au site de l'URL. Les requêtes d'une autre origine sont refusées.
 */
final class BorneAuth implements Middleware
{
    public const COOKIE = 'pgs_borne';
    public const COOKIE_SECURISE = '__Host-pgs_borne';

    public function __construct(private readonly Factory $factory)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origine = $request->header('Origin');
        if ($origine !== null && rtrim($origine, '/') !== self::origine($this->factory->appUrl())) {
            return Response::json(['erreur' => 'Origine refusée.'], 403);
        }

        $jeton = $request->cookie(self::COOKIE_SECURISE) ?? $request->cookie(self::COOKIE);
        $borne = $jeton === null || strlen($jeton) < 32 ? null : $this->factory->bornes()->trouverParJeton($jeton);
        if ($borne === null || $borne['site_code'] !== $request->param('code') || !(bool) $borne['site_actif']) {
            return Response::json(['erreur' => 'Borne non reconnue. Un nouvel enrôlement est nécessaire.', 'enrolement' => true], 401);
        }

        $request->withAttribute('borne', $borne);

        return $next($request);
    }

    private static function origine(string $url): string
    {
        $parties = parse_url($url);

        return ($parties['scheme'] ?? 'https') . '://' . ($parties['host'] ?? '') . (isset($parties['port']) ? ':' . $parties['port'] : '');
    }
}
