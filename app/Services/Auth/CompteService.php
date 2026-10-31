<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Role;
use App\Repositories\JetonCompteRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Mail\FileEmails;
use App\Services\ValidationException;

/**
 * Comptes sans fiche agent : service RH, administrateurs et contacts des clients.
 * Les comptes des agents et chefs d'équipe sont créés depuis leur fiche.
 */
final class CompteService
{
    private const DUREE_INVITATION = 72 * 3600;

    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly JetonCompteRepository $jetons,
        private readonly FileEmails $emails,
        private readonly AuditLogger $audit,
        private readonly string $appUrl,
    ) {
    }

    public function creer(Role $role, string $email, string $nomComplet, ?int $clientId, bool $inviter): int
    {
        if ($role->estTerrain()) {
            throw ValidationException::champ('role', 'Les agents et chefs d\'équipe se créent depuis leur fiche agent.');
        }
        if ($role === Role::Client && $clientId === null) {
            throw ValidationException::champ('client_id', 'Choisissez le client rattaché à ce compte.');
        }
        if ($this->utilisateurs->emailUtilise($email)) {
            throw ValidationException::champ('email', 'Cette adresse e-mail est déjà utilisée.');
        }

        $id = $this->utilisateurs->creer($role, $email, $nomComplet, $role === Role::Client ? $clientId : null);
        $this->audit->journaliser('utilisateur.creer', 'utilisateur', $id, ['role' => $role->value]);
        if ($inviter) {
            $this->inviter($id);
        }

        return $id;
    }

    public function modifier(int $id, string $email, string $nomComplet, Role $role, ?int $clientId): void
    {
        $avant = $this->utilisateurs->trouver($id) ?? throw ValidationException::champ('utilisateur', 'Compte introuvable.');
        if ($this->utilisateurs->emailUtilise($email, $id)) {
            throw ValidationException::champ('email', 'Cette adresse e-mail est déjà utilisée.');
        }
        $roleAvant = Role::fromId((int) $avant['role_id']);
        if ($roleAvant->estTerrain() !== $role->estTerrain()) {
            throw ValidationException::champ('role', 'Un compte agent ne peut pas devenir un compte de bureau, et inversement.');
        }
        if ($role === Role::Client && $clientId === null) {
            throw ValidationException::champ('client_id', 'Choisissez le client rattaché à ce compte.');
        }

        $this->utilisateurs->modifierIdentite($id, $email, $nomComplet);
        if ($roleAvant !== $role || (int) ($avant['client_id'] ?? 0) !== (int) $clientId) {
            $this->utilisateurs->changerRole($id, $role, $role === Role::Client ? $clientId : null);
            // Les droits changent : les sessions ouvertes sont fermées.
            $this->utilisateurs->invaliderSessions($id);
            $this->audit->journaliser('utilisateur.role', 'utilisateur', $id, ['avant' => $roleAvant->value, 'apres' => $role->value]);
        }
        $this->audit->journaliser('utilisateur.modifier', 'utilisateur', $id);
    }

    public function changerStatut(int $id, string $statut): void
    {
        if (!in_array($statut, ['actif', 'suspendu', 'desactive'], true)) {
            throw ValidationException::champ('statut', 'Statut inconnu.');
        }

        $this->utilisateurs->changerStatut($id, $statut);
        $this->utilisateurs->invaliderSessions($id);
        if ($statut !== 'actif') {
            $this->jetons->invaliderPour($id, 'appareil_confiance');
        }
        $this->audit->journaliser('utilisateur.statut', 'utilisateur', $id, ['statut' => $statut]);
    }

    public function inviter(int $id): void
    {
        $utilisateur = $this->utilisateurs->trouver($id) ?? throw ValidationException::champ('utilisateur', 'Compte introuvable.');
        if ($utilisateur['statut'] !== 'invite') {
            throw ValidationException::champ('utilisateur', 'Ce compte est déjà activé.');
        }

        $this->jetons->invaliderPour($id, 'invitation');
        $jeton = $this->jetons->creer($id, 'invitation', self::DUREE_INVITATION);
        $this->emails->ajouter((string) $utilisateur['email'], 'Votre accès à Pegasus Connect', 'invitation', [
            'nom' => (string) $utilisateur['nom_complet'],
            'lien' => $this->appUrl . '/invitation/' . $jeton,
            'heures' => intdiv(self::DUREE_INVITATION, 3600),
        ]);
        $this->audit->journaliser('utilisateur.inviter', 'utilisateur', $id);
    }

    /**
     * Téléphone perdu : la double authentification est retirée, l'utilisateur la reconfigure
     * à sa prochaine connexion.
     */
    public function reinitialiserDeuxFacteurs(int $id): void
    {
        $this->utilisateurs->definirTotp($id, null, false, null);
        $this->jetons->invaliderPour($id, 'appareil_confiance');
        $this->utilisateurs->invaliderSessions($id);
        $this->audit->journaliser('utilisateur.2fa.reinitialiser', 'utilisateur', $id);
    }
}
