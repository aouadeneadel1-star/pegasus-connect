<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Role;
use DateTimeImmutable;

final class UtilisateurRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT u.*, a.id AS agent_id
             FROM utilisateurs u
             LEFT JOIN agents a ON a.utilisateur_id = u.id
             WHERE u.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouverParEmail(string $email): ?array
    {
        return $this->ligne(
            'SELECT u.*, a.id AS agent_id
             FROM utilisateurs u
             LEFT JOIN agents a ON a.utilisateur_id = u.id
             WHERE u.email = :email',
            ['email' => mb_strtolower(trim($email))],
        );
    }

    public function emailUtilise(string $email, ?int $saufId = null): bool
    {
        return (bool) $this->valeur(
            'SELECT COUNT(*) FROM utilisateurs WHERE email = :email AND id <> :sauf',
            ['email' => mb_strtolower(trim($email)), 'sauf' => $saufId ?? 0],
        );
    }

    public function creer(Role $role, string $email, string $nomComplet, ?int $clientId = null): int
    {
        return $this->inserer('utilisateurs', [
            'role_id' => $role->id(),
            'client_id' => $clientId,
            'email' => mb_strtolower(trim($email)),
            'nom_complet' => $nomComplet,
            'statut' => 'invite',
        ]);
    }

    public function modifierIdentite(int $id, string $email, string $nomComplet): void
    {
        $this->modifierLigne('utilisateurs', $id, [
            'email' => mb_strtolower(trim($email)),
            'nom_complet' => $nomComplet,
        ]);
    }

    public function changerRole(int $id, Role $role, ?int $clientId): void
    {
        $this->modifierLigne('utilisateurs', $id, ['role_id' => $role->id(), 'client_id' => $clientId]);
    }

    public function definirMotDePasse(int $id, string $hash): void
    {
        $this->modifierLigne('utilisateurs', $id, [
            'password_hash' => $hash,
            'mdp_modifie_le' => Clock::sql(Clock::now()),
            'echecs_connexion' => 0,
            'verrouille_jusqu_a' => null,
        ]);
    }

    public function activer(int $id): void
    {
        $this->modifierLigne('utilisateurs', $id, ['statut' => 'actif']);
    }

    public function changerStatut(int $id, string $statut): void
    {
        $this->modifierLigne('utilisateurs', $id, ['statut' => $statut]);
    }

    public function enregistrerEchec(int $id, ?DateTimeImmutable $verrouilleJusqua): void
    {
        $this->executer(
            'UPDATE utilisateurs
             SET echecs_connexion = LEAST(echecs_connexion + 1, 255), verrouille_jusqu_a = :jusqua
             WHERE id = :id',
            ['id' => $id, 'jusqua' => $verrouilleJusqua === null ? null : Clock::sql($verrouilleJusqua)],
        );
    }

    public function reinitialiserEchecs(int $id): void
    {
        $this->modifierLigne('utilisateurs', $id, ['echecs_connexion' => 0, 'verrouille_jusqu_a' => null]);
    }

    public function enregistrerConnexion(int $id): void
    {
        $maintenant = Clock::sql(Clock::now());
        $this->modifierLigne('utilisateurs', $id, [
            'derniere_connexion' => $maintenant,
            'auth_forte_le' => $maintenant,
            'echecs_connexion' => 0,
            'verrouille_jusqu_a' => null,
        ]);
    }

    public function enregistrerAuthForte(int $id): void
    {
        $this->modifierLigne('utilisateurs', $id, ['auth_forte_le' => Clock::sql(Clock::now())]);
    }

    public function definirTotp(int $id, ?string $secretChiffre, bool $actif, ?string $codesSecoursJson): void
    {
        $this->modifierLigne('utilisateurs', $id, [
            'totp_secret_chiffre' => $secretChiffre,
            'totp_actif' => $actif ? 1 : 0,
            'codes_secours' => $codesSecoursJson,
        ]);
    }

    public function definirCodesSecours(int $id, string $codesSecoursJson): void
    {
        $this->modifierLigne('utilisateurs', $id, ['codes_secours' => $codesSecoursJson]);
    }

    public function definirDernierTotp(int $id, int $horodatage): void
    {
        $this->modifierLigne('utilisateurs', $id, ['totp_dernier_horodatage' => $horodatage]);
    }

    /**
     * Les sessions ouvertes portent la version : toute session antérieure devient invalide.
     */
    public function invaliderSessions(int $id): void
    {
        $this->executer('UPDATE utilisateurs SET session_version = session_version + 1 WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param list<Role> $roles
     * @return list<int>
     */
    public function idsActifsParRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        $params = [];
        foreach ($roles as $index => $role) {
            $params['r' . $index] = $role->id();
        }

        return $this->ids(
            sprintf(
                "SELECT id FROM utilisateurs WHERE statut = 'actif' AND role_id IN (:%s)",
                implode(', :', array_keys($params)),
            ),
            $params,
        );
    }

    /**
     * @param array{recherche?: string, role?: string, statut?: string} $filtres
     * @return list<array<string, mixed>>
     */
    public function lister(array $filtres): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (($filtres['recherche'] ?? '') !== '') {
            $conditions[] = '(u.nom_complet LIKE :recherche OR u.email LIKE :recherche_email)';
            $params['recherche'] = '%' . $filtres['recherche'] . '%';
            $params['recherche_email'] = '%' . $filtres['recherche'] . '%';
        }
        if (($filtres['role'] ?? '') !== '') {
            $conditions[] = 'r.code = :role';
            $params['role'] = $filtres['role'];
        }
        if (($filtres['statut'] ?? '') !== '') {
            $conditions[] = 'u.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }

        return $this->lignes(
            'SELECT u.id, u.email, u.nom_complet, u.statut, u.totp_actif, u.derniere_connexion,
                    r.code AS role_code, c.raison_sociale AS client
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN clients c ON c.id = u.client_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY u.nom_complet
             LIMIT 500',
            $params,
        );
    }
}
