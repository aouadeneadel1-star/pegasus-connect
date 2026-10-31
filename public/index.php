<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;

$racine = dirname(__DIR__);

// Serveur intégré de PHP (développement) : les fichiers statiques sont servis directement.
if (PHP_SAPI === 'cli-server') {
    $fichier = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($fichier) && !str_ends_with($fichier, '.php')) {
        return false;
    }
}

require $racine . '/vendor/autoload.php';

$factory = App::boot($racine);
(new App($factory))->handle(Request::fromGlobals())->send();
