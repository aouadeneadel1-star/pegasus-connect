<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Téléchargement d'un fichier par lien signé. Le lien expire après 5 minutes, n'est valable
 * que pour l'utilisateur à qui il a été remis, et les droits sont vérifiés à nouveau.
 */
final class FichierController extends Controller
{
    public function servir(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $jeton = $this->factory->urlSignee()->verifier($request->param('jeton'));
        if ($jeton === null || $jeton['utilisateur'] !== $utilisateur->id) {
            throw HttpException::notFound();
        }

        $document = $this->factory->documents()->trouver($jeton['document']);
        if ($document === null || !$this->factory->accesDocuments()->peutLire($utilisateur, $document)) {
            throw HttpException::notFound();
        }

        if (in_array($document['categorie'], ['paie', 'rapport_mensuel', 'identite', 'contrat'], true)) {
            $this->factory->audit()->journaliser('document.telecharger', 'document', (int) $document['id'], ['categorie' => $document['categorie']]);
        }

        $affichage = $jeton['affichage'] && in_array($document['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);

        return Response::file(
            $this->factory->stockage()->chemin($document),
            (string) $document['nom_original'],
            (string) $document['mime'],
            $affichage,
        )->withHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    }
}
