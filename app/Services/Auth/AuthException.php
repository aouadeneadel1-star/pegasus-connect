<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/**
 * Erreur d'authentification dont le message peut être affiché tel quel.
 */
final class AuthException extends RuntimeException
{
}
