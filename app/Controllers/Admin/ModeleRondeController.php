<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\Rondes\Planification;

/**
 * Modèles de ronde : plage horaire, fréquence, jours, points à contrôler (cahier des charges, § 3.5.2).
 */
final class ModeleRondeController extends Controller
{
    private const LIBELLES = [
        'nom' => 'Le nom',
        'heure_debut' => 'L\'heure de début',
        'heure_fin' => 'L\'heure de fin',
        'frequence_minutes' => 'La fréquence',
        'duree_max_minutes' => 'La durée maximale',
        'tolerance_demarrage_min' => 'La tolérance de démarrage',
        'temps_min_entre_points_s' => 'Le temps minimal entre deux points',
    ];

    public function nouveau(Request $request): Response
    {
        $site = $this->siteAccessible($request, $request->intParam('id'));

        return $this->vue('admin/sites/modele', $this->donneesFormulaire($site, null));
    }

    public function creer(Request $request): Response
    {
        $site = $this->siteAccessible($request, $request->intParam('id'));
        [$validation, $donnees, $points] = $this->valider($request, (int) $site['id']);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/sites/modele', $validation->errors(), $this->donneesFormulaire($site, null));
        }

        $id = $this->factory->modelesRonde()->creer((int) $site['id'], $donnees);
        $this->factory->modelesRonde()->definirPoints($id, $points);
        $this->factory->audit()->journaliser('modele_ronde.creer', 'modele_ronde', $id, ['site' => (int) $site['id']]);

        return $this->rediriger('/admin/sites/' . $site['id'] . '?onglet=modeles', 'Modèle de ronde créé. Les rondes des prochaines 24 heures sont générées automatiquement.');
    }

    public function editer(Request $request): Response
    {
        $modele = $this->factory->modelesRonde()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $site = $this->siteAccessible($request, (int) $modele['site_id']);

        return $this->vue('admin/sites/modele', $this->donneesFormulaire($site, $modele));
    }

    public function enregistrer(Request $request): Response
    {
        $modele = $this->factory->modelesRonde()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $site = $this->siteAccessible($request, (int) $modele['site_id']);
        [$validation, $donnees, $points] = $this->valider($request, (int) $site['id']);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/sites/modele', $validation->errors(), $this->donneesFormulaire($site, $modele));
        }

        $this->factory->modelesRonde()->modifier((int) $modele['id'], $donnees);
        $this->factory->modelesRonde()->definirPoints((int) $modele['id'], $points);
        $this->factory->audit()->journaliser('modele_ronde.modifier', 'modele_ronde', (int) $modele['id']);

        return $this->rediriger('/admin/sites/' . $site['id'] . '?onglet=modeles', 'Modèle enregistré. Les rondes déjà planifiées gardent leurs points.');
    }

    /**
     * @return array{0: Validator, 1: array<string, scalar|null>, 2: list<array{point_id: int, obligatoire: bool}>}
     */
    private function valider(Request $request, int $siteId): array
    {
        $validation = Validator::make($request->all(), [
            'nom' => 'required|max:80',
            'heure_debut' => 'required|time',
            'heure_fin' => 'required|time',
            'frequence_minutes' => 'required|int|between:15,1440',
            'duree_max_minutes' => 'required|int|between:5,480',
            'tolerance_demarrage_min' => 'required|int|between:0,240',
            'temps_min_entre_points_s' => 'required|int|between:0,600',
        ], self::LIBELLES);

        $jours = 0;
        $choisis = $request->raw('jours');
        foreach (is_array($choisis) ? $choisis : [] as $bit) {
            if (array_key_exists((int) $bit, Planification::JOURS)) {
                $jours |= (int) $bit;
            }
        }
        if ($jours === 0) {
            $validation->addError('jours', 'Choisissez au moins un jour.');
        }

        $actifs = array_map(static fn (array $p): int => (int) $p['id'], $this->factory->pointsControle()->pourSite($siteId));
        $ordre = $request->raw('points');
        $obligatoires = $request->raw('obligatoires');
        $points = [];
        foreach (is_array($ordre) ? $ordre : [] as $pointId) {
            $pointId = (int) $pointId;
            if (in_array($pointId, $actifs, true) && !in_array($pointId, array_column($points, 'point_id'), true)) {
                $points[] = ['point_id' => $pointId, 'obligatoire' => is_array($obligatoires) && in_array((string) $pointId, array_map('strval', $obligatoires), true)];
            }
        }
        if ($points === []) {
            $validation->addError('points', 'Sélectionnez les points de contrôle de la ronde.');
        }

        $donnees = $validation->validated();

        return [$validation, [
            'nom' => (string) ($donnees['nom'] ?? ''),
            'heure_debut' => (string) ($donnees['heure_debut'] ?? '00:00'),
            'heure_fin' => (string) ($donnees['heure_fin'] ?? '00:00'),
            'frequence_minutes' => (int) ($donnees['frequence_minutes'] ?? 60),
            'jours_semaine' => $jours,
            'duree_max_minutes' => (int) ($donnees['duree_max_minutes'] ?? 45),
            'tolerance_demarrage_min' => (int) ($donnees['tolerance_demarrage_min'] ?? 15),
            'ordre_impose' => $request->boolean('ordre_impose') ? 1 : 0,
            'temps_min_entre_points_s' => (int) ($donnees['temps_min_entre_points_s'] ?? 30),
            'actif' => $request->boolean('actif') ? 1 : 0,
        ], $points];
    }

    /**
     * @param array<string, mixed> $site
     * @param array<string, mixed>|null $modele
     * @return array<string, mixed>
     */
    private function donneesFormulaire(array $site, ?array $modele): array
    {
        $selection = $modele === null ? [] : $this->factory->modelesRonde()->points((int) $modele['id']);

        return [
            'site' => $site,
            'modele' => $modele,
            'defauts' => [
                'heure_debut' => '21:00', 'heure_fin' => '06:00', 'frequence_minutes' => '120', 'jours_semaine' => '127',
                'duree_max_minutes' => '45', 'tolerance_demarrage_min' => '15',
                'temps_min_entre_points_s' => (string) $this->factory->parametres()->int('rondes.temps_min_entre_points_s', 30), 'actif' => '1',
            ],
            'points' => $this->factory->pointsControle()->pourSite((int) $site['id']),
            'selection' => $selection,
            'jours' => Planification::JOURS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function siteAccessible(Request $request, int $siteId): array
    {
        $site = $this->factory->sites()->trouver($siteId) ?? throw HttpException::notFound();
        if (!$this->factory->autorisation()->perimetre($this->utilisateur($request), 'sites.voir')->couvreSite($siteId)) {
            throw HttpException::notFound();
        }

        return $site;
    }
}
