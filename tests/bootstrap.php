<?php

declare(strict_types=1);

use App\Core\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env.testing');

// Garde-fou : les tests d'intégration vident leur base. Ils ne doivent jamais toucher une autre base.
if (!str_ends_with(Env::required('DB_NAME'), '_test')) {
    fwrite(STDERR, "DB_NAME doit se terminer par « _test » dans .env.testing.\n");
    exit(1);
}
