<?php

declare(strict_types=1);

// Usage : php bin/seed.php [--env=testing]
// Les fichiers de database/seeds sont rejoués dans l'ordre et doivent rester idempotents
// (INSERT … ON DUPLICATE KEY UPDATE) : le script peut être relancé sans risque.

use App\Core\Console;
use App\Core\Database;
use App\Core\SqlScript;

$racine = require __DIR__ . '/bootstrap.php';

$fichiers = glob($racine . '/database/seeds/*.sql') ?: [];
sort($fichiers, SORT_STRING);

if ($fichiers === []) {
    Console::line('Aucun fichier de données initiales.');
    exit(0);
}

try {
    $pdo = Database::connection();
} catch (PDOException $exception) {
    Console::error('Connexion à la base impossible : ' . $exception->getMessage());
    exit(1);
}

foreach ($fichiers as $chemin) {
    $pdo->beginTransaction();
    try {
        $requetes = SqlScript::runFile($pdo, $chemin);
        $pdo->commit();
        Console::ok(sprintf('%s (%d requête%s)', basename($chemin), $requetes, $requetes > 1 ? 's' : ''));
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Console::error($exception->getMessage());
        exit(1);
    }
}
