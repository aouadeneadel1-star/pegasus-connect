<?php

declare(strict_types=1);

namespace App\Services\Crypto;

use RuntimeException;

/**
 * Chiffrement authentifié (XSalsa20-Poly1305). Format stocké :
 * 1 octet de version de clé, puis le nonce, puis le message chiffré.
 * La version permet une rotation : les anciennes valeurs restent lisibles tant que
 * leur clé est conservée.
 */
final class Chiffrement
{
    private const VERSION_COURANTE = 1;

    /** @var array<int, string> */
    private array $cles;

    public function __construct(Cles $cles, private readonly string $usage)
    {
        $this->cles = [self::VERSION_COURANTE => $cles->pour('chiffrement:' . $usage . ':v1')];
    }

    public function chiffrer(string $clair): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return chr(self::VERSION_COURANTE)
            . $nonce
            . sodium_crypto_secretbox($clair, $nonce, $this->cles[self::VERSION_COURANTE]);
    }

    public function dechiffrer(string $chiffre): string
    {
        $version = ord($chiffre[0] ?? "\0");
        $cle = $this->cles[$version] ?? throw new RuntimeException(sprintf(
            'Clé de chiffrement v%d indisponible pour %s.',
            $version,
            $this->usage,
        ));

        $nonce = substr($chiffre, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $message = substr($chiffre, 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $clair = sodium_crypto_secretbox_open($message, $nonce, $cle);

        if ($clair === false) {
            throw new RuntimeException(sprintf('Donnée chiffrée altérée (%s).', $this->usage));
        }

        return $clair;
    }
}
