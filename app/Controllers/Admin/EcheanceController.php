<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Csv;
use App\Core\Request;
use App\Core\Response;
use App\Services\Agents\Echeance;

final class EcheanceController extends Controller
{
    private const HORIZONS = [7, 30, 90];

    public function index(Request $request): Response
    {
        $horizon = in_array((int) $request->query('jours', '90'), self::HORIZONS, true) ? (int) $request->query('jours', '90') : 90;

        return $this->vue('admin/echeances', [
            'echeances' => $this->echeances($request, $horizon),
            'horizon' => $horizon,
            'horizons' => self::HORIZONS,
        ]);
    }

    public function exporter(Request $request): Response
    {
        $lignes = [['Matricule', 'Nom', 'Prénom', 'Objet', 'Échéance', 'Jours restants']];
        foreach ($this->echeances($request, 90) as $echeance) {
            $lignes[] = [$echeance['matricule'], $echeance['nom'], $echeance['prenom'], $echeance['libelle'], date_courte((string) $echeance['echeance']), (string) $echeance['statut']['jours']];
        }

        return Csv::reponse('echeances-' . Clock::now()->setTimezone(Clock::local())->format('Y-m-d') . '.csv', $lignes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function echeances(Request $request, int $horizon): array
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'echeances.voir');
        $limite = Clock::now()->setTimezone(Clock::local())->modify('+' . $horizon . ' days')->format('Y-m-d');

        return array_map(static function (array $echeance): array {
            $echeance['statut'] = Echeance::statut((string) $echeance['echeance']);

            return $echeance;
        }, $this->factory->conformite()->echeances($perimetre, $limite));
    }
}
