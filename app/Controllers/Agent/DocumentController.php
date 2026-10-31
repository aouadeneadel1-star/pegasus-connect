<?php

declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentSocieteRepository;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $documents = $this->factory->documents()->pourAgent((int) $utilisateur->agentId, true);
        foreach ($documents as &$document) {
            $document['url'] = $this->factory->urlSignee()->pour((int) $document['id'], $utilisateur->id, true);
        }
        unset($document);

        $societe = $this->factory->documentsSociete()->visiblesPour($utilisateur);

        return $this->vue('agent/documents', [
            'documents' => $documents,
            'categories' => [...DocumentRepository::CATEGORIES_AGENT, 'rapport_mensuel' => 'Rapports mensuels'],
            'societe' => $societe,
            'categoriesSociete' => DocumentSocieteRepository::CATEGORIES,
            'fichesNonConsultees' => count(array_filter(
                $this->factory->paie()->fichesAgent((int) $utilisateur->agentId),
                static fn (array $f): bool => $f['premiere_consultation'] === null,
            )),
        ]);
    }

    public function paie(Request $request): Response
    {
        $fiches = $this->factory->paie()->fichesAgent((int) $this->utilisateur($request)->agentId);
        $parAnnee = [];
        foreach ($fiches as $fiche) {
            $parAnnee[(int) $fiche['annee']][] = $fiche;
        }

        return $this->vue('agent/paie', ['parAnnee' => $parAnnee]);
    }

    /**
     * Le lien de téléchargement n'est généré qu'au clic : il expire 5 minutes plus tard.
     */
    public function telechargerPaie(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $fiche = $this->factory->paie()->fiche($request->intParam('id'));
        if ($fiche === null || (int) $fiche['agent_id'] !== $utilisateur->agentId || $fiche['statut'] !== 'publiee') {
            throw HttpException::notFound();
        }

        $this->factory->paie()->marquerConsultee((int) $fiche['id']);

        return Response::redirect($this->factory->urlSignee()->pour((int) $fiche['document_id'], $utilisateur->id, true));
    }

    public function societe(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $version = $this->factory->documentsSociete()->versionVisible($request->intParam('id'), $utilisateur) ?? throw HttpException::notFound();
        $this->factory->documentsSociete()->marquerOuvert((int) $version['id'], $utilisateur->id);

        $accuse = null;
        foreach ($this->factory->documentsSociete()->visiblesPour($utilisateur) as $document) {
            if ((int) $document['version_id'] === (int) $version['id']) {
                $accuse = $document['accuse_le'];
            }
        }

        return $this->vue('agent/document-societe', [
            'version' => $version,
            'url' => $this->factory->urlSignee()->pour((int) $version['document_id'], $utilisateur->id, true),
            'accuseLe' => $accuse,
        ]);
    }

    public function accuser(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $version = $this->factory->documentsSociete()->versionVisible($request->intParam('id'), $utilisateur) ?? throw HttpException::notFound();
        $this->factory->documentsSociete()->accuser((int) $version['id'], $utilisateur->id, $request->ip());
        $this->factory->audit()->journaliser('societe.accuse', 'document_societe_version', (int) $version['id']);

        return $this->rediriger('/documents', 'Lecture confirmée : « ' . $version['titre'] . ' ».');
    }
}
