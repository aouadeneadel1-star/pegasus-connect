<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Role;
use App\Repositories\DemandeRepository;
use App\Services\Stockage\FichierRefuseException;
use App\Services\Stockage\Stockage;

/**
 * Demandes de contact côté demandeur : agents (espace agent) et clients (portail).
 */
final class DemandeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->vue('demandes/index', [
            ...self::espace($request),
            'demandes' => $this->factory->repository(DemandeRepository::class)->duDemandeur($this->utilisateur($request)->id),
            'telephone' => (string) $this->factory->parametres()->get('contact.telephone_permanence', ''),
        ]);
    }

    public function nouveau(Request $request): Response
    {
        return $this->vue('demandes/nouveau', [
            ...self::espace($request),
            'categories' => $this->categories($request),
            'categorie' => (string) $request->query('categorie', ''),
        ]);
    }

    public function creer(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $espace = self::espace($request);
        $categories = $this->categories($request);

        $validation = Validator::make($request->all(), [
            'categorie' => 'required|in:' . implode(',', array_keys($categories)),
            'objet' => 'required|max:150',
            'message' => 'required|max:5000',
        ], ['categorie' => 'La catégorie', 'objet' => 'L\'objet', 'message' => 'Le message']);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'demandes/nouveau', $validation->errors(), [...$espace, 'categories' => $categories, 'categorie' => '']);
        }

        $donnees = $validation->validated();
        $documentId = null;
        $fichier = $request->file('piece_jointe');
        if ($fichier !== null) {
            try {
                $documentId = $this->factory->stockage()->deposer(
                    $fichier, [...Stockage::PDF, ...Stockage::IMAGES], 10 * 1048576, 'autre', $utilisateur->agentId,
                    'Pièce jointe : ' . $donnees['objet'], $utilisateur->id,
                );
            } catch (FichierRefuseException $exception) {
                return $this->formulaireInvalide($request, 'demandes/nouveau', ['piece_jointe' => $exception->getMessage()], [...$espace, 'categories' => $categories, 'categorie' => '']);
            }
        }

        $demandes = $this->factory->repository(DemandeRepository::class);
        $categorie = (string) $donnees['categorie'];
        $id = $demandes->creer($utilisateur->id, $categorie, (string) $donnees['objet'], $categorie === 'urgence' ? 'urgente' : 'normale');
        $demandes->ajouterMessage($id, $utilisateur->id, (string) $donnees['message'], $documentId, false);
        $this->factory->audit()->journaliser('demande.creer', 'demande', $id, ['categorie' => $categorie]);

        $destinataires = $this->factory->destinataires()->rh();
        if (in_array($categorie, ['urgence', 'planning', 'client'], true)) {
            foreach ($this->sitesDuDemandeur($request) as $siteId) {
                $destinataires = [...$destinataires, ...$this->factory->destinataires()->pourSite($siteId, false)];
            }
        }
        $this->factory->notifications()->envoyer(
            $destinataires,
            'demande_nouvelle',
            ($categorie === 'urgence' ? 'Urgence : ' : 'Nouvelle demande : ') . $donnees['objet'],
            DemandeRepository::CATEGORIES[$categorie] . ' — ' . $utilisateur->nomComplet,
            '/admin/demandes/' . $id,
            $categorie === 'urgence' ? 'notification' : null,
        );

        return $this->rediriger($espace['prefixe'] . '/contact/' . $id, 'Votre demande a été transmise.');
    }

    public function voir(Request $request): Response
    {
        $demande = $this->demande($request);
        $messages = $this->factory->repository(DemandeRepository::class)->messages((int) $demande['id'], false);
        foreach ($messages as &$message) {
            $message['url'] = $message['document_id'] === null ? null : $this->factory->urlSignee()->pour((int) $message['document_id'], $this->utilisateur($request)->id);
        }

        return $this->vue('demandes/voir', [
            ...self::espace($request),
            'demande' => $demande,
            'messages' => $messages,
        ]);
    }

    public function repondre(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $demande = $this->demande($request);
        $espace = self::espace($request);
        $retour = $espace['prefixe'] . '/contact/' . $demande['id'];
        if ($demande['statut'] === 'close') {
            return $this->redirigerAvecErreur($retour, 'Cette demande est close. Créez une nouvelle demande si besoin.');
        }

        $message = trim((string) $request->input('message', ''));
        if ($message === '' || mb_strlen($message) > 5000) {
            return $this->redirigerAvecErreur($retour, 'Saisissez un message (5 000 caractères au plus).');
        }

        $demandes = $this->factory->repository(DemandeRepository::class);
        $demandes->ajouterMessage((int) $demande['id'], $utilisateur->id, $message, null, false);
        if (in_array($demande['statut'], ['attente_demandeur', 'resolue'], true)) {
            $demandes->changerStatut((int) $demande['id'], 'en_cours');
        }

        $destinataires = $demande['assignee_a'] !== null ? [(int) $demande['assignee_a']] : $this->factory->destinataires()->rh();
        $this->factory->notifications()->envoyer($destinataires, 'demande_reponse', 'Nouvelle réponse : ' . $demande['objet'], null, '/admin/demandes/' . $demande['id']);

        return $this->rediriger($retour, 'Message envoyé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function demande(Request $request): array
    {
        $demande = $this->factory->repository(DemandeRepository::class)->trouver($request->intParam('id'));
        if ($demande === null || (int) $demande['demandeur_id'] !== $this->utilisateur($request)->id) {
            throw HttpException::notFound();
        }

        return $demande;
    }

    /**
     * @return array<string, string>
     */
    private function categories(Request $request): array
    {
        $codes = $this->utilisateur($request)->role === Role::Client
            ? ['client', 'urgence', 'autre']
            : ['rh', 'paie', 'planning', 'urgence', 'autre'];

        return array_intersect_key(DemandeRepository::CATEGORIES, array_flip($codes));
    }

    /**
     * @return list<int>
     */
    private function sitesDuDemandeur(Request $request): array
    {
        $utilisateur = $this->utilisateur($request);
        if ($utilisateur->agentId !== null) {
            return $this->factory->agents()->sitesAffectes($utilisateur->agentId);
        }
        if ($utilisateur->clientId !== null) {
            return $this->factory->autorisation()->perimetre($utilisateur, 'client.portail')->siteIds;
        }

        return [];
    }
}
