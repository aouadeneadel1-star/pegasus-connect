<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Csv;
use App\Core\Request;
use App\Core\Response;

/**
 * Heures du mois par agent et variables de paie (cahier des charges, § 3.7).
 */
final class HeuresController extends Controller
{
    public function index(Request $request): Response
    {
        [$annee, $mois] = self::mois($request->query('mois'));

        return $this->vue('admin/heures', [
            'annee' => $annee,
            'mois' => $mois,
            'lignes' => $this->lignes($request, $annee, $mois),
            'cloture' => $this->factory->majorations()->estCloturee($annee, $mois),
            'peutGerer' => $this->utilisateur($request)->peut('heures.gerer'),
            'regles' => $this->factory->majorations()->enVigueur(sprintf('%04d-%02d-01', $annee, $mois)),
        ]);
    }

    /**
     * Variables de paie au format CSV : une ligne par agent, minutes converties en heures décimales.
     */
    public function exporter(Request $request): Response
    {
        [$annee, $mois] = self::mois($request->query('mois'));
        $lignes = [['Matricule', 'Nom', 'Prénom', 'Heures planifiées', 'Heures travaillées', 'Heures de nuit', 'Heures dimanche', 'Heures fériées', 'HS 25 %', 'HS 50 %', 'Services à vérifier']];
        foreach ($this->lignes($request, $annee, $mois) as $ligne) {
            $t = $ligne['totaux'];
            $lignes[] = [
                $ligne['matricule'], $ligne['nom'], $ligne['prenom'],
                heures_decimales($t['planifiees']), heures_decimales($t['travaillees']), heures_decimales($t['nuit']),
                heures_decimales($t['dimanche']), heures_decimales($t['ferie']), heures_decimales($t['hs25']), heures_decimales($t['hs50']),
                (string) $t['a_verifier'],
            ];
        }
        $this->factory->audit()->journaliser('heures.exporter', null, null, ['periode' => sprintf('%04d-%02d', $annee, $mois)]);

        return Csv::reponse(sprintf('variables-paie-%04d-%02d.csv', $annee, $mois), $lignes);
    }

    public function cloturer(Request $request): Response
    {
        [$annee, $mois] = self::mois($request->input('mois'));
        $this->factory->majorations()->cloturer($annee, $mois, $this->utilisateur($request)->id);
        $this->factory->audit()->journaliser('heures.cloturer', null, null, ['periode' => sprintf('%04d-%02d', $annee, $mois)]);

        return $this->rediriger(sprintf('/admin/heures?mois=%04d-%02d', $annee, $mois), 'Mois clôturé : les pointages de la période ne peuvent plus être corrigés.');
    }

    public function rouvrir(Request $request): Response
    {
        [$annee, $mois] = self::mois($request->input('mois'));
        $this->factory->majorations()->rouvrir($annee, $mois);
        $this->factory->audit()->journaliser('heures.rouvrir', null, null, ['periode' => sprintf('%04d-%02d', $annee, $mois)]);

        return $this->rediriger(sprintf('/admin/heures?mois=%04d-%02d', $annee, $mois), 'Mois rouvert.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lignes(Request $request, int $annee, int $mois): array
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'heures.voir');
        $agents = array_filter($this->factory->agents()->actifs(), static fn (array $a): bool => $perimetre->couvreAgent((int) $a['id']));
        $totaux = $this->factory->heures()->totauxMois(array_map(static fn (array $a): int => (int) $a['id'], array_values($agents)), $annee, $mois);

        $lignes = [];
        foreach ($agents as $agent) {
            $lignes[] = [...$agent, 'totaux' => $totaux[(int) $agent['id']]];
        }

        return $lignes;
    }
}
