<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Refus métier rattaché à un ou plusieurs champs de formulaire.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $erreurs champ => message
     */
    public function __construct(private readonly array $erreurs)
    {
        parent::__construct((string) reset($erreurs));
    }

    public static function champ(string $champ, string $message): self
    {
        return new self([$champ => $message]);
    }

    /**
     * @return array<string, string>
     */
    public function erreurs(): array
    {
        return $this->erreurs;
    }
}
