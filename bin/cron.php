<?php

declare(strict_types=1);

// Tâches planifiées (cahier des charges, § 5.7).
//
// Le cron est facultatif : sans lui, l'application lance elle-même les tâches échues après
// la réponse aux visites. Il les rend ponctuelles même la nuit, quand personne ne se connecte.
// Une entrée crontab par tâche, par exemple :
//
//   */5 * * * *  php /home/compte/pegasus/bin/cron.php rondes:generer
//   */5 * * * *  php /home/compte/pegasus/bin/cron.php rondes:surveiller
//   */5 * * * *  php /home/compte/pegasus/bin/cron.php pointages:surveiller
//   */5 * * * *  php /home/compte/pegasus/bin/cron.php emails:envoyer
//   0   2 * * *  php /home/compte/pegasus/bin/cron.php alertes:echeances
//   15  2 * * *  php /home/compte/pegasus/bin/cron.php rgpd:purger
//   30  2 * * *  php /home/compte/pegasus/bin/cron.php audit:verifier
//   0   3 * * *  php /home/compte/pegasus/bin/cron.php sauvegarde:base
//   0   7 * * *  php /home/compte/pegasus/bin/cron.php recap:quotidien
//   0   4 * * 0  php /home/compte/pegasus/bin/cron.php nettoyage:tmp
//   30  4 * * 0  php /home/compte/pegasus/bin/cron.php calendrier:feries
//
// Ou une seule entrée qui lance toutes les tâches échues :
//
//   */5 * * * *  php /home/compte/pegasus/bin/cron.php echues

use App\Core\App;
use App\Core\Console;
use App\Core\Log;

$racine = require __DIR__ . '/bootstrap.php';
$factory = App::boot($racine);
$taches = $factory->tachesPlanifiees();

$nom = $argv[1] ?? '';
if ($nom === 'echues') {
    $factory->installation()->verifier();
    $taches->executerEchues(240);
    Console::ok('Tâches échues traitées.');
    exit(0);
}

if (!in_array($nom, $taches->noms(), true)) {
    Console::line('Usage : php bin/cron.php <tâche>|echues');
    Console::line('Tâches : ' . implode(', ', $taches->noms()));
    exit($nom === '' ? 0 : 1);
}

$debut = microtime(true);
try {
    $resultat = $taches->executer($nom);
    Console::ok(sprintf('%s : %s (%s s)', $nom, $resultat ?? 'déjà en cours, ignorée', round(microtime(true) - $debut, 2)));
} catch (Throwable $exception) {
    Log::exception($exception);
    Console::error($nom . ' : ' . $exception->getMessage());
    exit(1);
}
