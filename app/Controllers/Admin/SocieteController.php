<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Perimetre;
use App\Models\Role;
use App\Repositories\DocumentSocieteRepository;
use App\Services\Stockage\FichierRefuseException;
use App\Services\Stockage\Stockage;

/**
 * Documents de la société : versions, ciblage et accusés de lecture (cahier des charges, § 3.9).
 */
final class SocieteController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->vue('admin/societe/index', [
            'documents' => $this->factory->documentsSociete()->lister((string) $request->query('statut', '')),
            'statut' => (string) $request->query('statut', ''),
            'categories' => DocumentSocieteRepository::CATEGORIES,
        ]);
    }

    public function nouveau(Request $request): Response
    {
        return $this->vue('admin/societe/formulaire', $this->donneesFormulaire(null, []));
    }

    public function creer(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $validation = $this->valider($request);
        $fichier = $request->file('fichier');
        if ($fichier === null) {
            $validation->addError('fichier', 'Déposez le document au format PDF.');
        }
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/societe/formulaire', $validation->errors(), $this->donneesFormulaire(null, []));
        }

        $donnees = $validation->validated();
        try {
            $documentId = $this->factory->stockage()->deposer($fichier, Stockage::PDF, 20 * 1048576, 'societe', null, (string) $donnees['titre'], $utilisateur->id, ['visible_agent' => false]);
        } catch (FichierRefuseException $exception) {
            return $this->formulaireInvalide($request, 'admin/societe/formulaire', ['fichier' => $exception->getMessage()], $this->donneesFormulaire(null, []));
        }

        $depot = $this->factory->documentsSociete();
        $id = $depot->creer([...$this->meta($request, $donnees), 'created_by' => $utilisateur->id]);
        $depot->definirCibles($id, $this->cibles($request));
        $depot->ajouterVersion($id, $documentId, null, $donnees['date_effet']);
        $this->factory->audit()->journaliser('societe.creer', 'document_societe', $id);

        return $this->rediriger('/admin/societe/' . $id, 'Document créé en brouillon. Vérifiez le ciblage puis publiez-le.');
    }

    public function voir(Request $request): Response
    {
        $document = $this->document($request);
        $depot = $this->factory->documentsSociete();
        $versions = $depot->versions((int) $document['id']);
        foreach ($versions as &$version) {
            $version['url'] = $this->factory->urlSignee()->pour((int) $version['document_id'], $this->utilisateur($request)->id, true);
        }
        unset($version);

        return $this->vue('admin/societe/fiche', [
            'document' => $document,
            'versions' => $versions,
            'brouillon' => $depot->brouillonVersion((int) $document['id']),
            'suivi' => $document['version_courante_id'] === null ? [] : $depot->suiviAccuses((int) $document['version_courante_id'], $depot->destinataires((int) $document['id'])),
            ...$this->donneesFormulaire($document, $depot->cibles((int) $document['id'])),
        ]);
    }

    public function enregistrer(Request $request): Response
    {
        $document = $this->document($request);
        $validation = $this->valider($request);
        if ($validation->fails()) {
            return $this->redirigerAvecErreur('/admin/societe/' . $document['id'], self::premiereErreur($validation));
        }

        $this->factory->documentsSociete()->modifier((int) $document['id'], $this->meta($request, $validation->validated()));
        $this->factory->documentsSociete()->definirCibles((int) $document['id'], $this->cibles($request));
        $this->factory->audit()->journaliser('societe.modifier', 'document_societe', (int) $document['id']);

        return $this->rediriger('/admin/societe/' . $document['id'], 'Document enregistré.');
    }

    /**
     * Nouvelle version : elle remplace la précédente à sa publication et réinitialise les accusés.
     */
    public function version(Request $request): Response
    {
        $document = $this->document($request);
        $fichier = $request->file('fichier');
        if ($fichier === null) {
            return $this->redirigerAvecErreur('/admin/societe/' . $document['id'], 'Déposez la nouvelle version au format PDF.');
        }

        try {
            $documentId = $this->factory->stockage()->deposer($fichier, Stockage::PDF, 20 * 1048576, 'societe', null, (string) $document['titre'], $this->utilisateur($request)->id, ['visible_agent' => false]);
        } catch (FichierRefuseException $exception) {
            return $this->redirigerAvecErreur('/admin/societe/' . $document['id'], $exception->getMessage());
        }

        $note = mb_substr(trim((string) $request->input('note_version', '')), 0, 255);
        $dateEffet = Validator::make($request->all(), ['date_effet' => 'date'])->validated()['date_effet'] ?? null;
        $this->factory->documentsSociete()->ajouterVersion((int) $document['id'], $documentId, $note === '' ? null : $note, $dateEffet);
        $this->factory->audit()->journaliser('societe.version', 'document_societe', (int) $document['id']);

        return $this->rediriger('/admin/societe/' . $document['id'], 'Nouvelle version déposée. Publiez-la pour la diffuser.');
    }

    public function publier(Request $request): Response
    {
        $document = $this->document($request);
        $depot = $this->factory->documentsSociete();
        $version = $depot->brouillonVersion((int) $document['id']);
        if ($version === null) {
            return $this->redirigerAvecErreur('/admin/societe/' . $document['id'], 'Aucune version à publier.');
        }

        $depot->publier((int) $document['id'], (int) $version['id'], $this->utilisateur($request)->id);
        $this->factory->audit()->journaliser('societe.publier', 'document_societe', (int) $document['id'], ['version' => (int) $version['version']]);

        $destinataires = $depot->destinataires((int) $document['id']);
        $this->factory->notifications()->envoyer(
            $destinataires,
            'document_societe',
            ((int) $version['version'] > 1 ? 'Nouvelle version : ' : 'Nouveau document : ') . $document['titre'],
            (bool) $document['accuse_requis'] ? 'Lecture et confirmation demandées.' : null,
            '/societe/' . $version['id'],
            (bool) $document['accuse_requis'] ? 'notification' : null,
        );

        return $this->rediriger('/admin/societe/' . $document['id'], sprintf('Document publié auprès de %d destinataire(s).', count($destinataires)));
    }

    public function archiver(Request $request): Response
    {
        $document = $this->document($request);
        $this->factory->documentsSociete()->modifier((int) $document['id'], ['statut' => 'archive']);
        $this->factory->audit()->journaliser('societe.archiver', 'document_societe', (int) $document['id']);

        return $this->rediriger('/admin/societe', 'Document archivé : il n\'est plus visible des agents.');
    }

    public function relancer(Request $request): Response
    {
        $document = $this->document($request);
        if ($document['version_courante_id'] === null) {
            return $this->redirigerAvecErreur('/admin/societe/' . $document['id'], 'Ce document n\'est pas publié.');
        }

        $depot = $this->factory->documentsSociete();
        $enRetard = array_map(
            static fn (array $ligne): int => (int) $ligne['id'],
            array_filter($depot->suiviAccuses((int) $document['version_courante_id'], $depot->destinataires((int) $document['id'])), static fn (array $l): bool => $l['accuse_le'] === null),
        );
        $this->factory->notifications()->envoyer(
            array_values($enRetard),
            'document_societe_relance',
            'Rappel : ' . $document['titre'],
            'Merci de lire ce document et d\'en confirmer la lecture.',
            '/societe/' . $document['version_courante_id'],
            'notification',
        );
        $this->factory->audit()->journaliser('societe.relancer', 'document_societe', (int) $document['id'], ['destinataires' => count($enRetard)]);

        return $this->rediriger('/admin/societe/' . $document['id'], sprintf('%d relance(s) envoyée(s).', count($enRetard)));
    }

    /**
     * @return array<string, mixed>
     */
    private function document(Request $request): array
    {
        return $this->factory->documentsSociete()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
    }

    private function valider(Request $request): Validator
    {
        return Validator::make($request->all(), [
            'titre' => 'required|max:150',
            'categorie' => 'required|in:' . implode(',', array_keys(DocumentSocieteRepository::CATEGORIES)),
            'description' => 'max:2000',
            'date_effet' => 'date',
        ], ['titre' => 'Le titre', 'categorie' => 'La catégorie', 'description' => 'La description', 'date_effet' => 'La date d\'effet']);
    }

    /**
     * @param array<string, ?string> $donnees
     * @return array<string, scalar|null>
     */
    private function meta(Request $request, array $donnees): array
    {
        return [
            'titre' => (string) $donnees['titre'],
            'categorie' => (string) $donnees['categorie'],
            'description' => $donnees['description'],
            'accuse_requis' => $request->boolean('accuse_requis') ? 1 : 0,
            'ciblage_global' => $request->input('ciblage', 'global') === 'global' ? 1 : 0,
        ];
    }

    /**
     * @return list<array{type: string, id: int}>
     */
    private function cibles(Request $request): array
    {
        if ($request->input('ciblage', 'global') === 'global') {
            return [];
        }

        $cibles = [];
        foreach (['site' => 'sites', 'equipe' => 'equipes', 'role' => 'roles'] as $type => $champ) {
            $valeurs = $request->raw($champ);
            foreach (is_array($valeurs) ? $valeurs : [] as $id) {
                if ((int) $id > 0) {
                    $cibles[] = ['type' => $type, 'id' => (int) $id];
                }
            }
        }

        return $cibles;
    }

    /**
     * @param array<string, mixed>|null $document
     * @param list<array{type: string, id: int}> $cibles
     * @return array<string, mixed>
     */
    private function donneesFormulaire(?array $document, array $cibles): array
    {
        return [
            'document' => $document,
            'cibles' => $cibles,
            'categories' => DocumentSocieteRepository::CATEGORIES,
            'sites' => $this->factory->sites()->choix(Perimetre::tout()),
            'equipes' => $this->factory->agents()->equipes(),
            'roles' => array_map(static fn (Role $role): array => ['id' => $role->id(), 'nom' => $role->libelle()], Role::cases()),
        ];
    }
}
