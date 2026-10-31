<?php

declare(strict_types=1);

// Tâches planifiées (cahier des charges, § 5.7). Une entrée crontab par tâche, par exemple :
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
// Un verrou par tâche empêche deux exécutions simultanées si une exécution déborde.

use App\Core\App;
use App\Core\Clock;
use App\Core\Console;
use App\Core\Log;
use App\Factory;
use App\Models\Perimetre;
use App\Models\Role;
use App\Services\Audit\VerificationAudit;
use App\Services\Heures\JoursFeries;

$racine = require __DIR__ . '/bootstrap.php';
$factory = App::boot($racine);

/** @var array<string, Closure(Factory): string> $taches */
$taches = [
    'rondes:generer' => static fn (Factory $f): string => sprintf('%d ronde(s) planifiée(s)', $f->rondeService()->generer()),

    'rondes:surveiller' => static function (Factory $f): string {
        $resultat = $f->rondeService()->surveiller();

        return sprintf('%d manquée(s), %d clôturée(s) automatiquement', $resultat['manquees'], $resultat['cloturees']);
    },

    'pointages:surveiller' => static function (Factory $f): string {
        $resultat = $f->surveillancePointages()->executer();

        return sprintf('%d absence(s), %d départ(s) manquant(s), %d borne(s) muette(s)', $resultat['absences'], $resultat['departs_manquants'], $resultat['bornes']);
    },

    'emails:envoyer' => static function (Factory $f): string {
        $resultat = $f->envoiEmails()->traiter(50);

        return sprintf('%d envoyé(s), %d échec(s)', $resultat['envoyes'], $resultat['echecs']);
    },

    'alertes:echeances' => static fn (Factory $f): string => sprintf('%d alerte(s) émise(s)', $f->alertesEcheances()->executer()),

    'rgpd:purger' => static function (Factory $f): string {
        $rapport = $f->rgpd()->purger($f->parametres()->int('geoloc.retention_jours', 60));

        return implode(', ', array_map(static fn (string $cle, int $n): string => $cle . '=' . $n, array_keys($rapport), $rapport));
    },

    'audit:verifier' => static function (Factory $f): string {
        $resultat = (new VerificationAudit($f->pdo()))->verifier();
        if ($resultat['rupture'] !== null) {
            $f->notifications()->envoyer(
                $f->destinataires()->administrateurs(),
                'audit_rupture',
                'Journal d\'audit : rupture de la chaîne d\'intégrité',
                sprintf('L\'entrée n° %d ne correspond pas à la chaîne. Le journal a pu être modifié hors de l\'application.', $resultat['rupture']),
                '/admin/audit',
                'notification',
            );

            return sprintf('RUPTURE à l\'entrée %d', $resultat['rupture']);
        }

        return sprintf('%d entrée(s) vérifiée(s), chaîne intègre', $resultat['lignes']);
    },

    'sauvegarde:base' => static function (Factory $f): string {
        $resultat = $f->sauvegardeBase()->executer(14);

        return sprintf('%s (%s Ko), %d ancienne(s) supprimée(s)', $resultat['fichier'], number_format($resultat['octets'] / 1024, 0, ',', ' '), $resultat['supprimees']);
    },

    // Récapitulatif du matin pour les chefs d'équipe et les administrateurs.
    'recap:quotidien' => static function (Factory $f): string {
        $hier = Clock::now()->setTimezone(Clock::local())->modify('-1 day')->setTime(0, 0)->setTimezone(Clock::utc());
        $tout = Perimetre::tout();
        $aVerifier = $f->pointages()->nombreAVerifier($tout);
        $anomalies = $f->anomalies()->nombreATraiter($tout);
        $rondes = $f->rondes()->lister($tout, $hier, $hier->modify('+1 day'), [], 1)['lignes'];
        $manquees = count(array_filter($rondes, static fn (array $r): bool => in_array($r['statut'], ['manquee', 'incomplete'], true)));

        $message = sprintf(
            'Hier : %d ronde(s), dont %d manquée(s) ou incomplète(s). À traiter : %d pointage(s) à vérifier, %d anomalie(s).',
            count($rondes), $manquees, $aVerifier, $anomalies,
        );
        $destinataires = $f->utilisateurs()->idsActifsParRoles([Role::Chef, Role::Admin]);
        $f->notifications()->envoyer($destinataires, 'recap_quotidien', 'Récapitulatif de la veille', $message, '/admin', 'notification');

        return sprintf('%d destinataire(s)', count($destinataires));
    },

    // Complète les jours fériés jusqu'à deux ans d'avance. Une année déjà saisie n'est pas
    // modifiée : l'administrateur a pu y ajouter un jour local ou corriger un libellé.
    'calendrier:feries' => static function (Factory $f): string {
        $courante = (int) Clock::now()->setTimezone(Clock::local())->format('Y');
        $ajoutees = [];
        foreach (range($courante, $courante + 2) as $annee) {
            if ($f->majorations()->feries($annee) !== []) {
                continue;
            }
            foreach (JoursFeries::pourAnnee($annee) as $date => $libelle) {
                $f->majorations()->ajouterFerie($date, $libelle);
            }
            $ajoutees[] = $annee;
        }

        return $ajoutees === [] ? 'calendrier à jour' : 'années ajoutées : ' . implode(', ', $ajoutees);
    },

    'nettoyage:tmp' => static function (Factory $f): string {
        $supprimes = 0;
        $limite = Clock::now()->getTimestamp() - 86400;
        foreach (['tmp', 'cache', 'sessions'] as $dossier) {
            foreach (glob($f->storage() . '/' . $dossier . '/*') ?: [] as $fichier) {
                if (is_file($fichier) && basename($fichier) !== '.htaccess' && filemtime($fichier) < $limite && unlink($fichier)) {
                    ++$supprimes;
                }
            }
        }

        return sprintf('%d fichier(s) temporaire(s) supprimé(s), %d jeton(s) expiré(s)', $supprimes, $f->jetons()->purgerExpires());
    },
];

$nom = $argv[1] ?? '';
if (!isset($taches[$nom])) {
    Console::line('Usage : php bin/cron.php <tâche>');
    Console::line('Tâches : ' . implode(', ', array_keys($taches)));
    exit($nom === '' ? 0 : 1);
}

$dossierVerrous = $factory->storage() . '/locks';
if (!is_dir($dossierVerrous) && !mkdir($dossierVerrous, 0700, true) && !is_dir($dossierVerrous)) {
    Console::error('Dossier des verrous inaccessible.');
    exit(1);
}

$verrou = fopen($dossierVerrous . '/' . str_replace(':', '-', $nom) . '.lock', 'c');
if ($verrou === false || !flock($verrou, LOCK_EX | LOCK_NB)) {
    Log::info('Tâche déjà en cours, exécution ignorée', ['tache' => $nom]);
    exit(0);
}

$debut = microtime(true);
try {
    $resultat = $taches[$nom]($factory);
    $duree = round(microtime(true) - $debut, 2);
    Log::info('Tâche planifiée terminée', ['tache' => $nom, 'resultat' => $resultat, 'duree_s' => $duree]);
    Console::ok(sprintf('%s : %s (%s s)', $nom, $resultat, $duree));
} catch (Throwable $exception) {
    Log::exception($exception);
    Console::error($nom . ' : ' . $exception->getMessage());
    exit(1);
} finally {
    flock($verrou, LOCK_UN);
    fclose($verrou);
}
