<?php

declare(strict_types=1);

// Usage : php bin/restaurer-sauvegarde.php storage/backups/<fichier>.sql.gz.chiffre > restauration.sql
//
// Déchiffre et décompresse une sauvegarde produite par « cron.php sauvegarde:base ».
// Le SQL est écrit sur la sortie standard ; l'importer ensuite dans une base vide :
//   mysql -u <utilisateur> -p <base> < restauration.sql
// Supprimer restauration.sql après usage : il contient les données en clair.
// La clé est dérivée de APP_KEY : sans la même APP_KEY, la sauvegarde est inexploitable.

use App\Core\App;
use App\Core\Console;

$racine = require __DIR__ . '/bootstrap.php';
$factory = App::boot($racine);

$fichier = $argv[1] ?? '';
if ($fichier === '' || !is_file($fichier)) {
    Console::error('Usage : php bin/restaurer-sauvegarde.php <fichier.sql.gz.chiffre> > restauration.sql');
    exit(1);
}
if (stream_isatty(STDOUT)) {
    Console::error('Rediriger la sortie vers un fichier : le SQL ne doit pas s\'afficher dans le terminal.');
    exit(1);
}

try {
    $factory->sauvegardeBase()->dechiffrer($fichier, STDOUT);
} catch (Throwable $exception) {
    Console::error($exception->getMessage());
    exit(1);
}
