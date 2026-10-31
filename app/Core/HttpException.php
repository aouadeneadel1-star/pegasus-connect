<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $message = '',
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'Page introuvable.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Accès refusé.'): self
    {
        return new self(403, $message);
    }

    public static function badRequest(string $message = 'Requête invalide.'): self
    {
        return new self(400, $message);
    }

    public static function tooManyRequests(string $message = 'Trop de tentatives. Réessayez dans quelques minutes.'): self
    {
        return new self(429, $message);
    }

    public static function methodNotAllowed(): self
    {
        return new self(405, 'Méthode non autorisée.');
    }

    public function status(): int
    {
        return $this->status;
    }
}
