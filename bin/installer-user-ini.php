<?php

declare(strict_types=1);

// Usage : php bin/installer-user-ini.php [--env=testing]
// Génère public/.user.ini à partir de public/.user.ini.dist.
// Les chemins doivent être absolus : un chemin relatif serait résolu depuis public/,
// donc sous la racine web.

use App\Core\Console;
use App\Core\Env;

$racine = require __DIR__ . '/bootstrap.php';

$storage = Env::get('STORAGE_PATH') ?? $racine . '/storage';
$storage = rtrim($storage, '/');

if (!str_starts_with($storage, '/')) {
    Console::error(sprintf('STORAGE_PATH doit être un chemin absolu (valeur actuelle : %s).', $storage));
    exit(1);
}
if (!is_dir($storage . '/sessions') || !is_dir($storage . '/logs')) {
    Console::error(sprintf('Les dossiers sessions/ et logs/ sont introuvables dans %s.', $storage));
    exit(1);
}

$modele = file_get_contents($racine . '/public/.user.ini.dist');
if ($modele === false) {
    Console::error('Modèle public/.user.ini.dist introuvable.');
    exit(1);
}

$cible = $racine . '/public/.user.ini';
if (file_put_contents($cible, str_replace('{{STORAGE_PATH}}', $storage, $modele)) === false) {
    Console::error('Écriture impossible : ' . $cible);
    exit(1);
}

Console::ok('public/.user.ini généré (stockage : ' . $storage . ')');
Console::line('PHP-FPM relit ce fichier toutes les 5 minutes (user_ini.cache_ttl).');
