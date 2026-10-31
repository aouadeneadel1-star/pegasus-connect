<?php

declare(strict_types=1);

namespace App\Services\Pointage;

use RuntimeException;

/**
 * Pointage refusé : le message est affiché tel quel à l'agent ou sur la borne.
 */
final class PointageRefuseException extends RuntimeException
{
    /**
     * @param array<string, scalar|null> $contexte
     */
    public function __construct(string $message, public readonly string $raison, public readonly array $contexte = [])
    {
        parent::__construct($message);
    }
}
