<?php

declare(strict_types=1);

namespace App\Services\Rapports;

use App\Core\Clock;
use App\Models\Perimetre;
use App\Repositories\AnomalieRepository;
use App\Repositories\RondeRepository;
use App\Services\Parametres;
use App\Services\Pdf\GenerateurPdf;
use DateTimeImmutable;

/**
 * Rapport de rondes d'un site sur une période (cahier des charges, § 3.5.7),
 * pour le back-office et le portail client.
 */
final class RapportRondes
{
    public function __construct(
        private readonly RondeRepository $rondes,
        private readonly AnomalieRepository $anomalies,
        private readonly GenerateurPdf $pdf,
        private readonly Parametres $parametres,
    ) {
    }

    /**
     * @param array<string, mixed> $site
     * @return array<string, mixed>
     */
    public function donnees(array $site, DateTimeImmutable $du, DateTimeImmutable $au, bool $pourClient, bool $nomsAgents): array
    {
        $rondes = [];
        $compteurs = ['prevues' => 0, 'terminee' => 0, 'incomplete' => 0, 'manquee' => 0, 'en_cours' => 0, 'points_prevus' => 0, 'points_controles' => 0];

        foreach ($this->rondes->pourSiteEntre((int) $site['id'], $du, $au) as $ronde) {
            if (in_array($ronde['statut'], ['annulee', 'a_venir'], true)) {
                continue;
            }
            ++$compteurs['prevues'];
            if (isset($compteurs[$ronde['statut']])) {
                ++$compteurs[$ronde['statut']];
            }
            $compteurs['points_prevus'] += (int) $ronde['nb_points_prevus'];
            $compteurs['points_controles'] += (int) $ronde['nb_points_controles'];
            if (!$nomsAgents) {
                $ronde['nom'] = null;
                $ronde['prenom'] = null;
            }
            $rondes[] = $ronde;
        }

        $anomalies = array_filter(
            $this->anomalies->lister(Perimetre::tout(), ['site' => (int) $site['id']], $pourClient, 1000),
            static function (array $anomalie) use ($du, $au): bool {
                $date = Clock::fromSql((string) $anomalie['created_at']);

                return $date >= $du && $date < $au;
            },
        );

        return [
            'site' => $site,
            'du' => $du,
            'au' => $au,
            'rondes' => $rondes,
            'compteurs' => $compteurs,
            'taux' => $compteurs['points_prevus'] === 0 ? null : round(100 * $compteurs['points_controles'] / $compteurs['points_prevus']),
            'anomalies' => array_values($anomalies),
            'nomsAgents' => $nomsAgents,
            'societe' => (string) $this->parametres->get('societe.raison_sociale', 'Pegasus Insure'),
        ];
    }

    /**
     * @param array<string, mixed> $donnees résultat de donnees()
     */
    public function pdf(array $donnees): string
    {
        return $this->pdf->rendre('rapport-rondes', [...$donnees, 'genereLe' => Clock::now()]);
    }

    /**
     * @param array<string, mixed> $donnees
     * @return list<list<string>>
     */
    public function lignesCsv(array $donnees): array
    {
        $lignes = [['Date', 'Ronde', 'Statut', 'Agent', 'Début', 'Fin', 'Points prévus', 'Points contrôlés', 'Commentaire']];
        foreach ($donnees['rondes'] as $ronde) {
            $debut = Clock::fromSql($ronde['demarree_le'] === null ? (string) $ronde['prevue_le'] : (string) $ronde['demarree_le'])?->setTimezone(Clock::local());
            $fin = Clock::fromSql($ronde['cloturee_le'] === null ? null : (string) $ronde['cloturee_le'])?->setTimezone(Clock::local());
            $lignes[] = [
                $debut?->format('d/m/Y') ?? '',
                (string) ($ronde['modele'] ?? 'Ronde libre'),
                (string) $ronde['statut'],
                $donnees['nomsAgents'] ? trim(($ronde['prenom'] ?? '') . ' ' . ($ronde['nom'] ?? '')) : '',
                $ronde['demarree_le'] === null ? '' : ($debut?->format('H:i') ?? ''),
                $fin?->format('H:i') ?? '',
                (string) $ronde['nb_points_prevus'],
                (string) $ronde['nb_points_controles'],
                (string) ($ronde['commentaire_cloture'] ?? ''),
            ];
        }

        return $lignes;
    }
}
