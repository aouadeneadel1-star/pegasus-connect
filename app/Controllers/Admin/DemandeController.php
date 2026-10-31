<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Role;
use App\Models\UtilisateurConnecte;
use App\Repositories\DemandeRepository;

/**
 * File des demandes de contact. Chaque rôle voit les catégories qui le concernent.
 */
final class DemandeController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $filtres = [
            'statut' => (string) $request->query('statut', ''),
            'categorie' => (string) $request->query('categorie', ''),
            'assignee' => $request->query('miennes') === '1' ? $utilisateur->id : 0,
        ];

        return $this->vue('admin/demandes/index', [
            'demandes' => $this->depot()->file($filtres, self::categories($utilisateur)),
            'filtres' => $filtres,
            'categories' => array_intersect_key(DemandeRepository::CATEGORIES, array_flip(self::categories($utilisateur))),
        ]);
    }

    public function voir(Request $request): Response
    {
        $demande = $this->demande($request);
        $messages = $this->depot()->messages((int) $demande['id'], true);
        foreach ($messages as &$message) {
            $message['url'] = $message['document_id'] === null ? null : $this->factory->urlSignee()->pour((int) $message['document_id'], $this->utilisateur($request)->id, true);
        }
        unset($message);

        return $this->vue('admin/demandes/fiche', [
            'demande' => $demande,
            'messages' => $messages,
            'gestionnaires' => $this->factory->utilisateurs()->lister(['statut' => 'actif']),
        ]);
    }

    public function repondre(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $demande = $this->demande($request);
        $message = trim((string) $request->input('message', ''));
        if ($message === '' || mb_strlen($message) > 5000) {
            return $this->redirigerAvecErreur('/admin/demandes/' . $demande['id'], 'Saisissez un message (5 000 caractères au plus).');
        }

        $interne = $request->boolean('interne');
        $this->depot()->ajouterMessage((int) $demande['id'], $utilisateur->id, $message, null, $interne);
        if (!$interne) {
            $this->depot()->changerStatut((int) $demande['id'], (string) $request->input('statut_apres', 'attente_demandeur') === 'resolue' ? 'resolue' : 'attente_demandeur');
            $prefixe = (int) $demande['demandeur_role'] === Role::Client->id() ? '/client' : '';
            $this->factory->notifications()->envoyer(
                [(int) $demande['demandeur_id']],
                'demande_reponse',
                'Réponse à votre demande : ' . $demande['objet'],
                null,
                $prefixe . '/contact/' . $demande['id'],
                'notification',
            );
        }
        if ($demande['assignee_a'] === null) {
            $this->depot()->assigner((int) $demande['id'], $utilisateur->id);
        }

        return $this->rediriger('/admin/demandes/' . $demande['id'], $interne ? 'Note interne ajoutée.' : 'Réponse envoyée.');
    }

    public function statut(Request $request): Response
    {
        $demande = $this->demande($request);
        $statut = (string) $request->input('statut');
        if (array_key_exists($statut, DemandeRepository::STATUTS)) {
            $this->depot()->changerStatut((int) $demande['id'], $statut);
        }
        $assignee = (int) $request->input('assignee', '0');
        $this->depot()->assigner((int) $demande['id'], $assignee > 0 ? $assignee : null);
        $this->factory->audit()->journaliser('demande.statut', 'demande', (int) $demande['id'], ['statut' => $statut]);

        return $this->rediriger('/admin/demandes/' . $demande['id'], 'Demande mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function demande(Request $request): array
    {
        $demande = $this->depot()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        if (!in_array($demande['categorie'], self::categories($this->utilisateur($request)), true)) {
            throw HttpException::notFound();
        }

        return $demande;
    }

    private function depot(): DemandeRepository
    {
        return $this->factory->repository(DemandeRepository::class);
    }

    /**
     * @return list<string>
     */
    private static function categories(UtilisateurConnecte $utilisateur): array
    {
        return match ($utilisateur->role) {
            Role::Chef => ['planning', 'urgence', 'client'],
            Role::Rh => ['rh', 'paie', 'planning', 'urgence', 'autre'],
            default => array_keys(DemandeRepository::CATEGORIES),
        };
    }
}
