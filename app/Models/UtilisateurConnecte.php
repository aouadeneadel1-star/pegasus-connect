<?php

declare(strict_types=1);

namespace App\Models;

final class UtilisateurConnecte
{
    /**
     * @param array<string, string> $permissions code => portée (soi, equipe, client, tout)
     */
    public function __construct(
        public readonly int $id,
        public readonly Role $role,
        public readonly string $nomComplet,
        public readonly string $email,
        public readonly ?int $agentId,
        public readonly ?int $clientId,
        public readonly bool $totpActif,
        private readonly array $permissions,
    ) {
    }

    public function peut(string $permission): bool
    {
        return isset($this->permissions[$permission]);
    }

    public function portee(string $permission): ?string
    {
        return $this->permissions[$permission] ?? null;
    }

    public function prenom(): string
    {
        return explode(' ', $this->nomComplet)[0];
    }

    public function initiales(): string
    {
        $parts = preg_split('/[\s-]+/', $this->nomComplet) ?: [];
        $initiales = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initiales .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initiales;
    }
}
