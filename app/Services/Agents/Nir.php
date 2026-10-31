<?php

declare(strict_types=1);

namespace App\Services\Agents;

/**
 * Numéro d'inscription au répertoire (n° de sécurité sociale) : 13 caractères + clé de 2 chiffres.
 * Les départements corses 2A et 2B sont gérés pour le calcul de la clé.
 */
final class Nir
{
    public static function normaliser(string $nir): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]/', '', $nir));
    }

    public static function estValide(string $nir): bool
    {
        $nir = self::normaliser($nir);
        if (preg_match('/^([1-478])(\d{2})(\d{2})(\d{2}|2A|2B)(\d{3})(\d{3})(\d{2})$/', $nir, $parties) !== 1) {
            return false;
        }

        $corps = substr($nir, 0, 13);
        $correction = 0;
        if (str_contains($corps, 'A')) {
            $corps = str_replace('A', '0', $corps);
            $correction = 1000000;
        } elseif (str_contains($corps, 'B')) {
            $corps = str_replace('B', '0', $corps);
            $correction = 2000000;
        }

        $cle = 97 - (((int) $corps - $correction) % 97);

        return $cle === (int) $parties[7];
    }

    /**
     * Affichage masqué : seule la clé (2 derniers chiffres) reste lisible.
     */
    public static function masquer(?string $fin): string
    {
        return $fin === null ? 'Non renseigné' : '• •• •• •• ••• ••• ' . $fin;
    }

    public static function formater(string $nir): string
    {
        $nir = self::normaliser($nir);

        return implode(' ', [
            substr($nir, 0, 1),
            substr($nir, 1, 2),
            substr($nir, 3, 2),
            substr($nir, 5, 2),
            substr($nir, 7, 3),
            substr($nir, 10, 3),
            substr($nir, 13, 2),
        ]);
    }
}
