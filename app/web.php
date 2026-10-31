<?php

declare(strict_types=1);

// Traitement d'une requête web, chargé par public/index.php une fois la version de PHP vérifiée.

use App\Core\App;
use App\Core\Env;
use App\Core\Log;
use App\Core\Request;

$racine = dirname(__DIR__);
$public = $racine . '/public';

// Serveur intégré de PHP (développement) : les fichiers statiques sont servis directement.
if (PHP_SAPI === 'cli-server') {
    $fichier = $public . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($fichier) && !str_ends_with($fichier, '.php')) {
        return false;
    }
}

require $racine . '/vendor/autoload.php';

$factory = App::boot($racine);
(new App($factory))->handle(Request::fromGlobals())->send();

// Sans cron sur l'hébergement, les tâches échues tournent ici, une fois la réponse partie :
// le visiteur n'attend pas. Désactivable par TACHES_AUTOMATIQUES=0 si un cron est en place.
$terminer = match (true) {
    function_exists('fastcgi_finish_request') => 'fastcgi_finish_request',
    function_exists('litespeed_finish_request') => 'litespeed_finish_request',
    default => null,
};
if ($terminer !== null && Env::bool('TACHES_AUTOMATIQUES', true)) {
    $terminer();
    ignore_user_abort(true);
    set_time_limit(90);
    try {
        $factory->tachesPlanifiees()->executerEchues(60);
    } catch (Throwable $exception) {
        Log::exception($exception);
    }
}
