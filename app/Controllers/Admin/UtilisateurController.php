<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Role;
use App\Services\ValidationException;

/**
 * Comptes RH, administrateurs et clients. Les comptes agents se gèrent depuis la fiche agent.
 */
final class UtilisateurController extends Controller
{
    private const ROLES_BUREAU = [Role::Rh, Role::Admin, Role::Client];

    public function index(Request $request): Response
    {
        $filtres = [
            'recherche' => (string) $request->query('q', ''),
            'role' => (string) $request->query('role', ''),
            'statut' => (string) $request->query('statut', ''),
        ];

        return $this->vue('admin/utilisateurs/index', [
            'utilisateurs' => $this->factory->utilisateurs()->lister($filtres),
            'filtres' => $filtres,
            'roles' => Role::cases(),
        ]);
    }

    public function nouveau(Request $request): Response
    {
        return $this->vue('admin/utilisateurs/formulaire', $this->donneesFormulaire(null, (int) $request->query('client', '0')));
    }

    public function creer(Request $request): Response
    {
        [$validation, $role] = $this->valider($request);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/utilisateurs/formulaire', $validation->errors(), $this->donneesFormulaire(null, 0));
        }

        $donnees = $validation->validated();
        try {
            $id = $this->factory->comptes()->creer($role, (string) $donnees['email'], (string) $donnees['nom_complet'], $this->clientId($donnees), $request->boolean('inviter'));
        } catch (ValidationException $exception) {
            return $this->formulaireInvalide($request, 'admin/utilisateurs/formulaire', $exception->erreurs(), $this->donneesFormulaire(null, 0));
        }

        return $this->rediriger('/admin/utilisateurs/' . $id, $request->boolean('inviter') ? 'Compte créé, invitation envoyée.' : 'Compte créé.');
    }

    public function editer(Request $request): Response
    {
        $compte = $this->compte($request);

        return $this->vue('admin/utilisateurs/formulaire', $this->donneesFormulaire($compte, (int) ($compte['client_id'] ?? 0)));
    }

    public function enregistrer(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $compte = $this->compte($request);
        $id = (int) $compte['id'];

        $statut = (string) $request->input('statut', '');
        if ($statut !== '') {
            if ($id === $utilisateur->id) {
                return $this->redirigerAvecErreur('/admin/utilisateurs/' . $id, 'Vous ne pouvez pas modifier le statut de votre propre compte.');
            }
            $this->factory->comptes()->changerStatut($id, $statut);

            return $this->rediriger('/admin/utilisateurs/' . $id, 'Statut mis à jour.');
        }

        [$validation, $role] = $this->valider($request, Role::fromId((int) $compte['role_id']));
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/utilisateurs/formulaire', $validation->errors(), $this->donneesFormulaire($compte, (int) ($compte['client_id'] ?? 0)));
        }
        if ($id === $utilisateur->id && $role !== $utilisateur->role) {
            return $this->redirigerAvecErreur('/admin/utilisateurs/' . $id, 'Vous ne pouvez pas changer votre propre rôle.');
        }

        $donnees = $validation->validated();
        try {
            $this->factory->comptes()->modifier($id, (string) $donnees['email'], (string) $donnees['nom_complet'], $role, $this->clientId($donnees));
        } catch (ValidationException $exception) {
            return $this->formulaireInvalide($request, 'admin/utilisateurs/formulaire', $exception->erreurs(), $this->donneesFormulaire($compte, (int) ($compte['client_id'] ?? 0)));
        }

        return $this->rediriger('/admin/utilisateurs/' . $id, 'Compte enregistré.');
    }

    public function inviter(Request $request): Response
    {
        $compte = $this->compte($request);
        try {
            $this->factory->comptes()->inviter((int) $compte['id']);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/utilisateurs/' . $compte['id'], $exception->getMessage());
        }

        return $this->rediriger('/admin/utilisateurs/' . $compte['id'], 'Invitation envoyée, valable 72 heures.');
    }

    public function reinitialiserDeuxFacteurs(Request $request): Response
    {
        $compte = $this->compte($request);
        $this->factory->comptes()->reinitialiserDeuxFacteurs((int) $compte['id']);

        return $this->rediriger('/admin/utilisateurs/' . $compte['id'], 'Double authentification réinitialisée : l\'utilisateur la configurera à sa prochaine connexion.');
    }

    /**
     * @return array<string, mixed>
     */
    private function compte(Request $request): array
    {
        return $this->factory->utilisateurs()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
    }

    /**
     * @return array{0: Validator, 1: Role}
     */
    private function valider(Request $request, ?Role $actuel = null): array
    {
        $roles = $actuel !== null && $actuel->estTerrain()
            ? [Role::Agent, Role::Chef]
            : self::ROLES_BUREAU;
        $validation = Validator::make($request->all(), [
            'nom_complet' => 'required|max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:' . implode(',', array_map(static fn (Role $r): string => $r->value, $roles)),
            'client_id' => 'int',
        ], ['nom_complet' => 'Le nom', 'email' => 'L\'adresse e-mail', 'role' => 'Le rôle', 'client_id' => 'Le client']);

        $role = Role::tryFrom((string) $request->input('role', '')) ?? $actuel ?? Role::Rh;

        return [$validation, $role];
    }

    /**
     * @param array<string, ?string> $donnees
     */
    private function clientId(array $donnees): ?int
    {
        $id = (int) ($donnees['client_id'] ?? 0);

        return $id > 0 && $this->factory->clients()->trouver($id) !== null ? $id : null;
    }

    /**
     * @param array<string, mixed>|null $compte
     * @return array<string, mixed>
     */
    private function donneesFormulaire(?array $compte, int $clientId): array
    {
        $role = $compte === null ? null : Role::fromId((int) $compte['role_id']);
        $agentId = null;
        if ($role !== null && $role->estTerrain()) {
            $agentId = $this->factory->agents()->idPourUtilisateur((int) $compte['id']);
        }

        return [
            'compte' => $compte,
            'role' => $role,
            'agentId' => $agentId,
            'roles' => $role !== null && $role->estTerrain() ? [Role::Agent, Role::Chef] : self::ROLES_BUREAU,
            'clients' => $this->factory->clients()->choix(),
            'clientDefaut' => $clientId,
        ];
    }
}
