<?php

declare(strict_types=1);

namespace App\Services\Auth;

final class ResultatConnexion
{
    public const ECHEC = 'echec';
    public const TROP_DE_TENTATIVES = 'trop_de_tentatives';
    public const SECOND_FACTEUR = 'second_facteur';
    public const CONNECTE = 'connecte';

    private function __construct(
        public readonly string $statut,
        public readonly ?int $utilisateurId = null,
    ) {
    }

    public static function echec(): self
    {
        return new self(self::ECHEC);
    }

    public static function tropDeTentatives(): self
    {
        return new self(self::TROP_DE_TENTATIVES);
    }

    public static function secondFacteur(int $utilisateurId): self
    {
        return new self(self::SECOND_FACTEUR, $utilisateurId);
    }

    public static function connecte(int $utilisateurId): self
    {
        return new self(self::CONNECTE, $utilisateurId);
    }
}
