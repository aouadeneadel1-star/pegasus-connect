<?php

declare(strict_types=1);

namespace App\Services\Heures;

use App\Core\Clock;
use App\Repositories\MajorationRepository;
use App\Repositories\ServiceTravailRepository;
use App\Repositories\VacationRepository;
use App\Services\Parametres;
use DateTimeImmutable;

/**
 * Totaux mensuels par agent : heures planifiées, travaillées et majorations.
 *
 * Un service est rattaché au mois de son début. Les heures supplémentaires sont calculées
 * par semaine civile ; une semaine à cheval sur deux mois est rattachée au mois de son dimanche.
 */
final class HeuresService
{
    public function __construct(
        private readonly ServiceTravailRepository $services,
        private readonly VacationRepository $vacations,
        private readonly MajorationRepository $majorations,
        private readonly Parametres $parametres,
    ) {
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} bornes UTC du mois local
     */
    public static function bornesMois(int $annee, int $mois): array
    {
        $debut = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $annee, $mois), Clock::local());

        return [$debut->setTimezone(Clock::utc()), $debut->modify('+1 month')->setTimezone(Clock::utc())];
    }

    /**
     * @param list<int> $agentIds
     * @return array<int, array{planifiees: int, travaillees: int, nuit: int, dimanche: int, ferie: int, hs25: int, hs50: int, a_verifier: int, services: int}>
     */
    public function totauxMois(array $agentIds, int $annee, int $mois): array
    {
        [$debutMois, $finMois] = self::bornesMois($annee, $mois);
        $premierLundi = self::lundi($debutMois);
        $dernierDimancheExclu = self::lundi($finMois->modify('-1 second'))->modify('+7 days');

        $regles = $this->majorations->enVigueur($debutMois->setTimezone(Clock::local())->format('Y-m-d'));
        [$nuitDebut, $nuitFin] = self::plageNuit($regles['nuit'] ?? null);
        $calcul = new CalculHeures(
            $this->majorations->feriesEntre($premierLundi->setTimezone(Clock::local())->format('Y-m-d'), $dernierDimancheExclu->setTimezone(Clock::local())->format('Y-m-d')),
            $nuitDebut,
            $nuitFin,
        );
        $reference = (int) round((float) $this->parametres->get('heures.reference_hebdo_h', 35) * 60);
        $seuil = (int) round((float) ($regles['hs25']['seuil_heures'] ?? 8) * 60);

        $totaux = [];
        foreach ($agentIds as $agentId) {
            $totaux[$agentId] = [
                'planifiees' => $this->vacations->minutesPlanifiees($agentId, $debutMois, $finMois),
                'travaillees' => 0, 'nuit' => 0, 'dimanche' => 0, 'ferie' => 0, 'hs25' => 0, 'hs50' => 0, 'a_verifier' => 0, 'services' => 0,
            ];
        }

        $semaines = [];
        foreach ($this->services->comptabilisables($premierLundi, $dernierDimancheExclu, $agentIds) as $service) {
            $agentId = (int) $service['agent_id'];
            $debut = Clock::fromSql((string) $service['debut']);
            $fin = Clock::fromSql((string) $service['fin']);
            $ventilation = $calcul->ventiler($debut, $fin, (int) ($service['pause_minutes'] ?? 0));

            foreach ($ventilation['semaines'] as $lundi => $minutes) {
                $semaines[$agentId][$lundi] = ($semaines[$agentId][$lundi] ?? 0) + $minutes;
            }

            if ($debut < $debutMois || $debut >= $finMois) {
                continue;
            }
            $totaux[$agentId]['travaillees'] += $ventilation['total'];
            $totaux[$agentId]['nuit'] += $ventilation['nuit'];
            $totaux[$agentId]['dimanche'] += $ventilation['dimanche'];
            $totaux[$agentId]['ferie'] += $ventilation['ferie'];
            $totaux[$agentId]['services']++;
            if ((bool) $service['a_verifier']) {
                $totaux[$agentId]['a_verifier']++;
            }
        }

        $moisCle = sprintf('%04d-%02d', $annee, $mois);
        foreach ($semaines as $agentId => $parSemaine) {
            foreach ($parSemaine as $lundi => $minutes) {
                $dimanche = (new DateTimeImmutable($lundi, Clock::local()))->modify('+6 days')->format('Y-m');
                if ($dimanche !== $moisCle) {
                    continue;
                }
                $hs = CalculHeures::heuresSupplementaires($minutes, $reference, $seuil);
                $totaux[$agentId]['hs25'] += $hs['hs25'];
                $totaux[$agentId]['hs50'] += $hs['hs50'];
            }
        }

        return $totaux;
    }

    /**
     * @param array<string, mixed>|null $regle
     * @return array{0: int, 1: int}
     */
    private static function plageNuit(?array $regle): array
    {
        $versMinutes = static function (?string $heure, int $defaut): int {
            if ($heure === null || $heure === '') {
                return $defaut;
            }
            [$h, $m] = array_map('intval', explode(':', $heure));

            return $h * 60 + $m;
        };

        return [
            $versMinutes($regle['plage_debut'] ?? null, 21 * 60),
            $versMinutes($regle['plage_fin'] ?? null, 6 * 60),
        ];
    }

    private static function lundi(DateTimeImmutable $instant): DateTimeImmutable
    {
        $local = $instant->setTimezone(Clock::local());

        return $local->modify('-' . ((int) $local->format('N') - 1) . ' days')->setTime(0, 0)->setTimezone(Clock::utc());
    }
}
