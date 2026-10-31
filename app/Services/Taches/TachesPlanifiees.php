<?php

declare(strict_types=1);

namespace App\Services\Taches;

use App\Core\Clock;
use App\Core\Log;
use App\Factory;
use App\Models\Perimetre;
use App\Models\Role;
use App\Services\Audit\VerificationAudit;
use App\Services\Heures\JoursFeries;
use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * Tâches planifiées (cahier des charges, § 5.7). Elles tournent de deux façons :
 * - par le cron de l'hébergement (bin/cron.php), à heure fixe ;
 * - sans cron, après la réponse aux visites : chaque tâche échue est lancée au plus une fois.
 * Un verrou par tâche et un état partagé empêchent les doubles exécutions entre les deux modes.
 */
final class TachesPlanifiees
{
    /** Intervalle en secondes, ou heure locale minimale pour une tâche quotidienne (« 02h »). */
    private const FREQUENCES = [
        'rondes:generer' => 300,
        'rondes:surveiller' => 300,
        'pointages:surveiller' => 300,
        'emails:envoyer' => 120,
        'alertes:echeances' => '02h',
        'rgpd:purger' => '02h',
        'audit:verifier' => '02h',
        'sauvegarde:base' => '03h',
        'recap:quotidien' => '07h',
        'calendrier:feries' => 604800,
        'nettoyage:tmp' => 604800,
    ];

    public function __construct(private readonly Factory $factory)
    {
    }

    /**
     * @return list<string>
     */
    public function noms(): array
    {
        return array_keys(self::FREQUENCES);
    }

    /**
     * @return string|null résultat, ou null si la tâche tournait déjà
     */
    public function executer(string $nom): ?string
    {
        $tache = $this->taches()[$nom] ?? throw new InvalidArgumentException('Tâche inconnue : ' . $nom);
        $verrou = fopen($this->dossier() . '/' . str_replace(':', '-', $nom) . '.lock', 'c');
        if ($verrou === false || !flock($verrou, LOCK_EX | LOCK_NB)) {
            Log::info('Tâche déjà en cours, exécution ignorée', ['tache' => $nom]);

            return null;
        }

        $debut = microtime(true);
        try {
            $resultat = $tache($this->factory);
            $this->noter($nom);
            Log::info('Tâche planifiée terminée', ['tache' => $nom, 'resultat' => $resultat, 'duree_s' => round(microtime(true) - $debut, 2)]);

            return $resultat;
        } finally {
            flock($verrou, LOCK_UN);
            fclose($verrou);
        }
    }

    /**
     * Lance les tâches échues dans la limite du temps donné. Un seul déclenchement à la fois :
     * les autres visites du moment repartent sans attendre.
     */
    public function executerEchues(int $budgetSecondes): void
    {
        $global = fopen($this->dossier() . '/taches-automatiques.lock', 'c');
        if ($global === false || !flock($global, LOCK_EX | LOCK_NB)) {
            return;
        }

        try {
            $fin = microtime(true) + $budgetSecondes;
            $etat = $this->etat();
            foreach (self::FREQUENCES as $nom => $frequence) {
                if (microtime(true) >= $fin) {
                    break;
                }
                if (!$this->estEchue($frequence, $etat[$nom] ?? 0)) {
                    continue;
                }
                try {
                    $this->executer($nom);
                } catch (Throwable $exception) {
                    // Une tâche en échec ne doit ni bloquer les suivantes ni être relancée à chaque visite.
                    $this->noter($nom);
                    Log::exception($exception);
                }
            }
        } finally {
            flock($global, LOCK_UN);
            fclose($global);
        }
    }

    private function estEchue(int|string $frequence, int $dernier): bool
    {
        $maintenant = Clock::now();
        if (is_int($frequence)) {
            return $maintenant->getTimestamp() - $dernier >= $frequence;
        }

        $local = $maintenant->setTimezone(Clock::local());
        $dejaFaite = $dernier > 0 && (new \DateTimeImmutable('@' . $dernier))->setTimezone(Clock::local())->format('Y-m-d') === $local->format('Y-m-d');

        return !$dejaFaite && (int) $local->format('G') >= (int) $frequence;
    }

    /**
     * @return array<string, int> horodatage de la dernière exécution de chaque tâche
     */
    private function etat(): array
    {
        $contenu = @file_get_contents($this->fichierEtat());
        $etat = $contenu === false ? [] : json_decode($contenu, true);

        return is_array($etat) ? array_map('intval', $etat) : [];
    }

    private function noter(string $nom): void
    {
        $flux = fopen($this->fichierEtat(), 'c+');
        if ($flux === false) {
            return;
        }
        flock($flux, LOCK_EX);
        $etat = json_decode((string) stream_get_contents($flux), true);
        $etat = is_array($etat) ? $etat : [];
        $etat[$nom] = Clock::now()->getTimestamp();
        ftruncate($flux, 0);
        rewind($flux);
        fwrite($flux, (string) json_encode($etat));
        flock($flux, LOCK_UN);
        fclose($flux);
    }

    private function fichierEtat(): string
    {
        return $this->dossier() . '/taches-etat.json';
    }

    private function dossier(): string
    {
        $dossier = $this->factory->storage() . '/locks';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }

        return $dossier;
    }

    /**
     * @return array<string, Closure(Factory): string>
     */
    private function taches(): array
    {
        return [
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
                        if (is_file($fichier) && !in_array(basename($fichier), ['.htaccess', 'installation'], true) && filemtime($fichier) < $limite && unlink($fichier)) {
                            ++$supprimes;
                        }
                    }
                }

                return sprintf('%d fichier(s) temporaire(s) supprimé(s), %d jeton(s) expiré(s)', $supprimes, $f->jetons()->purgerExpires());
            },
        ];
    }
}
