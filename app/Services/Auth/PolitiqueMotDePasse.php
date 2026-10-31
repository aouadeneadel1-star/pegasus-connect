<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * 12 caractères minimum, sans expiration périodique (recommandation ANSSI/CNIL),
 * et refus des mots de passe courants ou dérivés de l'identité de l'utilisateur.
 */
final class PolitiqueMotDePasse
{
    public const LONGUEUR_MINIMALE = 12;

    /** @var array<string, true>|null */
    private ?array $courants = null;

    public function __construct(private readonly string $fichierCourants)
    {
    }

    /**
     * @param list<string> $identite e-mail, nom, prénom : le mot de passe ne doit pas s'en servir
     */
    public function erreur(string $motDePasse, array $identite = []): ?string
    {
        if (mb_strlen($motDePasse) < self::LONGUEUR_MINIMALE) {
            return sprintf('Le mot de passe doit contenir au moins %d caractères.', self::LONGUEUR_MINIMALE);
        }
        if (mb_strlen($motDePasse) > 128) {
            return 'Le mot de passe ne doit pas dépasser 128 caractères.';
        }
        if (count(array_unique(mb_str_split($motDePasse))) < 5) {
            return 'Le mot de passe est trop répétitif.';
        }

        $normalise = self::normaliser($motDePasse);
        if (isset($this->courants()[$normalise]) || isset($this->courants()[mb_strtolower($motDePasse)])) {
            return 'Ce mot de passe est trop courant. Choisissez-en un autre.';
        }

        foreach ($identite as $element) {
            $element = self::normaliser(explode('@', $element)[0]);
            if (mb_strlen($element) >= 4 && str_contains($normalise, $element)) {
                return 'Le mot de passe ne doit pas reprendre votre nom ou votre adresse e-mail.';
            }
        }

        return null;
    }

    public static function hacher(string $motDePasse): string
    {
        return password_hash($motDePasse, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT, self::options());
    }

    public static function doitRehacher(string $hash): bool
    {
        return password_needs_rehash($hash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT, self::options());
    }

    /**
     * @return array<string, int>
     */
    private static function options(): array
    {
        return defined('PASSWORD_ARGON2ID') ? [] : ['cost' => 12];
    }

    /**
     * « Azerty2024! » est aussi faible que « azerty » : on retire chiffres et symboles finaux.
     */
    private static function normaliser(string $valeur): string
    {
        $valeur = (string) preg_replace('/[\d\W_]+$/u', '', mb_strtolower($valeur));

        return strtr($valeur, ['@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '$' => 's', '!' => 'i']);
    }

    /**
     * @return array<string, true>
     */
    private function courants(): array
    {
        if ($this->courants === null) {
            $lignes = is_file($this->fichierCourants) ? file($this->fichierCourants, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            $this->courants = [];
            foreach ($lignes ?: [] as $ligne) {
                $this->courants[mb_strtolower(trim($ligne))] = true;
                $this->courants[self::normaliser(trim($ligne))] = true;
            }
        }

        return $this->courants;
    }
}
