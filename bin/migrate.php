<?php

declare(strict_types=1);

// Usage : php bin/migrate.php [--env=testing] [--fresh] [--statut]
//   --fresh   supprime toutes les tables avant de migrer (local et testing uniquement)
//   --statut  liste les migrations sans rien exécuter

use App\Core\Console;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

$racine = require __DIR__ . '/bootstrap.php';

try {
    $migrator = new Migrator(Database::connection(), $racine . '/database/migrations');
    $migrator->ensureHistoryTable();

    if (Console::flag($argv, 'statut')) {
        $executees = $migrator->executed();
        foreach ($migrator->available() as $fichier) {
            Console::line((in_array($fichier, $executees, true) ? '  passée    ' : '  en attente ') . $fichier);
        }
        exit(0);
    }

    if (Console::flag($argv, 'fresh')) {
        $env = Env::get('APP_ENV', 'production');
        $base = Env::required('DB_NAME');

        if (!in_array($env, ['local', 'testing'], true)) {
            Console::error(sprintf('--fresh est refusé en environnement « %s ».', $env));
            exit(1);
        }
        if ($env === 'testing' && !str_ends_with($base, '_test')) {
            Console::error(sprintf('La base de test doit se terminer par « _test » (base actuelle : %s).', $base));
            exit(1);
        }

        $supprimees = $migrator->dropAllTables();
        Console::line(sprintf('%d table(s) supprimée(s) dans %s.', $supprimees, $base));
        $migrator->ensureHistoryTable();
    }

    $nombre = $migrator->migrate(static function (string $fichier, int $requetes): void {
        Console::ok(sprintf('%s (%d requête%s)', $fichier, $requetes, $requetes > 1 ? 's' : ''));
    });

    Console::line($nombre === 0 ? 'Base à jour, aucune migration en attente.' : sprintf('%d migration(s) exécutée(s).', $nombre));
} catch (Throwable $exception) {
    Console::error($exception->getMessage());
    exit(1);
}
