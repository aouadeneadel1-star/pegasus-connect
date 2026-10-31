<?php

declare(strict_types=1);

// Chargé par les scripts de bin/ : autoload Composer et fichier d'environnement.
// L'option --env=testing charge .env.testing au lieu de .env.

use App\Core\Console;
use App\Core\Env;

$racine = dirname(__DIR__);

if (!is_file($racine . '/vendor/autoload.php')) {
    fwrite(STDERR, "Dépendances absentes : lancez « composer install » à la racine du projet.\n");
    exit(1);
}

require $racine . '/vendor/autoload.php';

$environnement = Console::option($argv, 'env');
if ($environnement !== null && preg_match('/^[a-z]+$/', $environnement) !== 1) {
    Console::error('Valeur invalide pour --env (lettres minuscules uniquement).');
    exit(1);
}

$fichierEnv = $racine . '/.env' . ($environnement === null ? '' : '.' . $environnement);

try {
    Env::load($fichierEnv);
} catch (RuntimeException $exception) {
    Console::error($exception->getMessage());
    exit(1);
}

return $racine;
