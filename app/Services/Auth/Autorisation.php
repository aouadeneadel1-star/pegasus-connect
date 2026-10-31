<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\HttpException;
use App\Models\Perimetre;
use App\Models\Role;
use App\Models\UtilisateurConnecte;
use App\Repositories\PerimetreRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UtilisateurRepository;

final class Autorisation
{
    /** @var array<string, Perimetre> */
    private array $perimetres = [];

    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly PermissionRepository $permissions,
        private readonly PerimetreRepository $perimetreRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $ligne
     */
    public function utilisateurConnecte(array $ligne): UtilisateurConnecte
    {
        $role = Role::fromId((int) $ligne['role_id']);

        return new UtilisateurConnecte(
            (int) $ligne['id'],
            $role,
            (string) $ligne['nom_complet'],
            (string) $ligne['email'],
            $ligne['agent_id'] === null ? null : (int) $ligne['agent_id'],
            $ligne['client_id'] === null ? null : (int) $ligne['client_id'],
            (bool) $ligne['totp_actif'],
            $this->permissions->pourRole($role->id()),
        );
    }

    public function charger(int $utilisateurId): ?UtilisateurConnecte
    {
        $ligne = $this->utilisateurs->trouver($utilisateurId);

        return $ligne === null ? null : $this->utilisateurConnecte($ligne);
    }

    public function exiger(UtilisateurConnecte $utilisateur, string $permission): void
    {
        if (!$utilisateur->peut($permission)) {
            throw HttpException::forbidden();
        }
    }

    /**
     * Périmètre couvert par une permission. Lève une erreur 403 si la permission manque.
     */
    public function perimetre(UtilisateurConnecte $utilisateur, string $permission): Perimetre
    {
        $portee = $utilisateur->portee($permission) ?? throw HttpException::forbidden();
        $cle = $utilisateur->id . ':' . $portee;

        return $this->perimetres[$cle] ??= match ($portee) {
            'tout' => Perimetre::tout(),
            'soi' => Perimetre::soi($utilisateur->agentId ?? throw HttpException::forbidden()),
            'equipe' => $this->perimetreEquipe($utilisateur),
            'client' => Perimetre::client(
                $this->perimetreRepository->sitesDuClient($utilisateur->clientId ?? throw HttpException::forbidden()),
            ),
            default => throw HttpException::forbidden(),
        };
    }

    private function perimetreEquipe(UtilisateurConnecte $utilisateur): Perimetre
    {
        $agentId = $utilisateur->agentId ?? throw HttpException::forbidden();
        $sites = $this->perimetreRepository->sitesDuChef($agentId);

        return Perimetre::equipe($sites, $this->perimetreRepository->agentsDuChef($agentId, $sites));
    }
}
