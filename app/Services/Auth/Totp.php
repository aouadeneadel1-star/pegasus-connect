<?php

declare(strict_types=1);

namespace App\Services\Auth;

use PragmaRX\Google2FA\Google2FA;

/**
 * Codes TOTP (RFC 6238) pour la double authentification.
 */
final class Totp
{
    private const FENETRE = 1;

    private readonly Google2FA $google2fa;

    public function __construct(private readonly string $emetteur)
    {
        $this->google2fa = new Google2FA();
    }

    public function genererSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function urlProvisionnement(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($this->emetteur, $email, $secret);
    }

    /**
     * Vérifie le code et refuse un code déjà utilisé (anti-rejeu).
     *
     * @return int|null horodatage TOTP accepté, à conserver pour le prochain contrôle
     */
    public function verifier(string $secret, string $code, ?int $dernierHorodatage): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return null;
        }

        $resultat = $this->google2fa->verifyKeyNewer($secret, $code, $dernierHorodatage ?? 0, self::FENETRE);

        return is_int($resultat) ? $resultat : null;
    }
}
