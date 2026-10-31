<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Points de contrôle des rondes et leurs étiquettes QR (cahier des charges, § 3.5.1).
 */
final class PointControleController extends Controller
{
    private const REGLES = [
        'nom' => 'required|max:120',
        'description' => 'max:255',
        'zone' => 'max:60',
        'ordre' => 'int|between:0,999',
        'latitude' => 'decimal|between:-90,90',
        'longitude' => 'decimal|between:-180,180',
        'rayon_metres' => 'int|between:5,500',
    ];

    private const LIBELLES = [
        'nom' => 'Le nom', 'description' => 'La description', 'zone' => 'La zone', 'ordre' => 'L\'ordre',
        'latitude' => 'La latitude', 'longitude' => 'La longitude', 'rayon_metres' => 'Le rayon',
    ];

    public function creer(Request $request): Response
    {
        $site = $this->siteAccessible($request, $request->intParam('id'));
        $retour = '/admin/sites/' . $site['id'] . '?onglet=points';
        $validation = Validator::make($request->all(), self::REGLES, self::LIBELLES);
        if ($validation->fails()) {
            return $this->redirigerAvecErreur($retour, self::premiereErreur($validation));
        }

        $id = $this->factory->pointsControle()->creer((int) $site['id'], $this->donnees($validation->validated()));
        $this->factory->audit()->journaliser('point.creer', 'point_controle', $id, ['site' => (int) $site['id']]);

        return $this->rediriger($retour, 'Point de contrôle créé. Imprimez son étiquette depuis la liste.');
    }

    public function voir(Request $request): Response
    {
        $point = $this->pointAccessible($request);
        $du = self::date($request->query('du'), Clock::now()->setTimezone(Clock::local())->modify('-30 days')->format('Y-m-d'));
        $au = self::date($request->query('au'), Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));
        $debut = (new \DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc());
        $fin = (new \DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc());

        return $this->vue('admin/sites/point', [
            'point' => $point,
            'passages' => $this->factory->pointsControle()->passages((int) $point['id'], Clock::sql($debut), Clock::sql($fin)),
            'du' => $du,
            'au' => $au,
            'peutGerer' => $this->utilisateur($request)->peut('sites.gerer'),
        ]);
    }

    public function enregistrer(Request $request): Response
    {
        $point = $this->pointAccessible($request);
        $validation = Validator::make($request->all(), self::REGLES, self::LIBELLES);
        if ($validation->fails()) {
            return $this->redirigerAvecErreur('/admin/points/' . $point['id'], self::premiereErreur($validation));
        }

        $this->factory->pointsControle()->modifier((int) $point['id'], $this->donnees($validation->validated()));
        $this->factory->audit()->journaliser('point.modifier', 'point_controle', (int) $point['id']);

        return $this->rediriger('/admin/points/' . $point['id'], 'Point enregistré.');
    }

    public function regenerer(Request $request): Response
    {
        $point = $this->pointAccessible($request);
        $version = $this->factory->pointsControle()->incrementerVersionQr((int) $point['id']);
        $this->factory->audit()->journaliser('point.qr.regenerer', 'point_controle', (int) $point['id'], ['version' => $version]);

        return $this->rediriger('/admin/points/' . $point['id'], 'Nouvelle étiquette générée : l\'ancienne est refusée dès maintenant.');
    }

    public function basculer(Request $request): Response
    {
        $point = $this->pointAccessible($request);
        $actif = !(bool) $point['actif'];
        $this->factory->pointsControle()->definirActif((int) $point['id'], $actif);
        $this->factory->audit()->journaliser($actif ? 'point.activer' : 'point.desactiver', 'point_controle', (int) $point['id']);

        return $this->rediriger('/admin/sites/' . $point['site_id'] . '?onglet=points', $actif ? 'Point réactivé.' : 'Point désactivé : il n\'apparaît plus dans les rondes.');
    }

    /**
     * Planche d'étiquettes A4 (3 × 4), à imprimer sur papier adhésif résistant.
     */
    public function etiquettes(Request $request): Response
    {
        $site = $this->siteAccessible($request, $request->intParam('id'));
        $demandes = array_filter(array_map('intval', explode(',', (string) $request->query('points', ''))));
        $points = array_filter(
            $this->factory->pointsControle()->pourSite((int) $site['id']),
            static fn (array $p): bool => $demandes === [] || in_array((int) $p['id'], $demandes, true),
        );
        if ($points === []) {
            return $this->redirigerAvecErreur('/admin/sites/' . $site['id'] . '?onglet=points', 'Aucun point actif à imprimer.');
        }

        $etiquettes = array_map(fn (array $point): array => [
            'point' => $point,
            'qr' => $this->factory->qrImage()->dataUri($this->factory->jetonsQr()->pointControle((int) $point['id'], (int) $point['qr_version']), 400),
        ], array_values($points));

        $pdf = $this->factory->pdf()->rendre('etiquettes', ['site' => $site, 'etiquettes' => $etiquettes]);
        $this->factory->audit()->journaliser('point.etiquettes', 'site', (int) $site['id'], ['points' => count($etiquettes)]);

        return Response::download($pdf, 'etiquettes-' . $site['code'] . '.pdf', 'application/pdf');
    }

    /**
     * @param array<string, ?string> $donnees
     * @return array<string, scalar|null>
     */
    private function donnees(array $donnees): array
    {
        return [
            'nom' => (string) $donnees['nom'],
            'description' => $donnees['description'],
            'zone' => $donnees['zone'],
            'ordre' => (int) ($donnees['ordre'] ?? 0),
            'latitude' => $donnees['latitude'] === null ? null : str_replace(',', '.', $donnees['latitude']),
            'longitude' => $donnees['longitude'] === null ? null : str_replace(',', '.', $donnees['longitude']),
            'rayon_metres' => $donnees['rayon_metres'] === null ? null : (int) $donnees['rayon_metres'],
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

    /**
     * @return array<string, mixed>
     */
    private function pointAccessible(Request $request): array
    {
        $point = $this->factory->pointsControle()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $this->siteAccessible($request, (int) $point['site_id']);

        return $point;
    }
}
