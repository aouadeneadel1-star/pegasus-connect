<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Droit d'accès : export des données d'un agent. Le mot de passe de l'archive est affiché
 * une seule fois et doit être transmis à l'agent par un autre canal que l'archive.
 * L'archive n'est téléchargeable qu'une fois, dans les 10 minutes, depuis la même session.
 */
final class RgpdController extends Controller
{
    private const CLE_SESSION = 'export_rgpd';

    public function exporter(Request $request): Response
    {
        $agentId = $request->intParam('id');
        if ($this->factory->agents()->trouver($agentId) === null) {
            throw HttpException::notFound();
        }

        $export = $this->factory->rgpd()->exporter($agentId);
        $jeton = bin2hex(random_bytes(16));
        Session::set(self::CLE_SESSION, [
            'jeton' => $jeton,
            'chemin' => $export['chemin'],
            'nom' => $export['nom'],
            'expire' => Clock::now()->getTimestamp() + 600,
        ]);

        return $this->vue('admin/rgpd-export', [
            'agentId' => $agentId,
            'motDePasse' => $export['mot_de_passe'],
            'lien' => '/admin/rgpd/archive/' . $jeton,
        ]);
    }

    public function telecharger(Request $request): Response
    {
        $export = Session::get(self::CLE_SESSION);
        Session::set(self::CLE_SESSION, null);
        if (!is_array($export) || !hash_equals((string) $export['jeton'], $request->param('jeton'))
            || (int) $export['expire'] < Clock::now()->getTimestamp() || !is_file((string) $export['chemin'])) {
            throw HttpException::notFound();
        }

        return Response::file((string) $export['chemin'], (string) $export['nom'], 'application/zip', false, true);
    }
}
