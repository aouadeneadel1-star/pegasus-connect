<?php

declare(strict_types=1);

namespace App\Services\Crypto;

use RuntimeException;

/**
 * Dérive une clé par usage à partir de la clé maître (APP_KEY). Une clé compromise
 * pour un usage (ex. QR de ronde) ne permet pas d'en déduire une autre.
 */
final class Cles
{
    private readonly string $maitre;

    public function __construct(string $appKey)
    {
        $maitre = base64_decode($appKey, true);
        if ($maitre === false || strlen($maitre) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY doit contenir 32 octets encodés en base64 (php bin/generer-cle.php).');
        }

        $this->maitre = $maitre;
    }

    public function pour(string $usage): string
    {
        return hash_hmac('sha256', 'pegasus:' . $usage, $this->maitre, true);
    }

    /**
     * Empreinte déterministe pour rechercher une donnée chiffrée sans la déchiffrer.
     */
    public function indexAveugle(string $usage, string $valeur): string
    {
        return hash_hmac('sha256', $valeur, $this->pour('index:' . $usage));
    }
}
