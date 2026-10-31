<?php

declare(strict_types=1);

namespace App\Models;

enum Role: string
{
    case Agent = 'agent';
    case Chef = 'chef';
    case Rh = 'rh';
    case Admin = 'admin';
    case Client = 'client';

    public static function fromId(int $id): self
    {
        return match ($id) {
            1 => self::Agent,
            2 => self::Chef,
            3 => self::Rh,
            4 => self::Admin,
            5 => self::Client,
            default => throw new \ValueError(sprintf('Rôle inconnu : %d', $id)),
        };
    }

    public function id(): int
    {
        return match ($this) {
            self::Agent => 1,
            self::Chef => 2,
            self::Rh => 3,
            self::Admin => 4,
            self::Client => 5,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Agent => 'Agent',
            self::Chef => 'Chef d\'équipe',
            self::Rh => 'RH',
            self::Admin => 'Administrateur',
            self::Client => 'Client',
        };
    }

    /**
     * Les agents et chefs d'équipe ont une fiche agent : ils pointent et font des rondes.
     */
    public function estTerrain(): bool
    {
        return $this === self::Agent || $this === self::Chef;
    }

    public function accueil(): string
    {
        return match ($this) {
            self::Agent, self::Chef => '/',
            self::Rh, self::Admin => '/admin',
            self::Client => '/client',
        };
    }

    /**
     * Durée de session : 12 h glissantes sur le terrain pour couvrir une vacation,
     * 30 min d'inactivité et 8 h au plus pour les postes de bureau.
     *
     * @return array{inactivite: int, maximum: int} en secondes
     */
    public function dureeSession(): array
    {
        return $this->estTerrain()
            ? ['inactivite' => 12 * 3600, 'maximum' => 12 * 3600]
            : ['inactivite' => 30 * 60, 'maximum' => 8 * 3600];
    }
}
