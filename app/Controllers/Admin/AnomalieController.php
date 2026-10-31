<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AnomalieRepository;

final class AnomalieController extends Controller
{
    public function index(Request $request): Response
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'rondes.suivre');
        $filtres = [
            'site' => (int) $request->query('site', '0'),
            'statut' => (string) $request->query('statut', 'a_traiter'),
            'gravite' => (string) $request->query('gravite', ''),
        ];

        return $this->vue('admin/anomalies/index', [
            'anomalies' => $this->factory->anomalies()->lister($perimetre, $filtres),
            'filtres' => $filtres,
            'sites' => $this->factory->sites()->choix($perimetre),
        ]);
    }

    public function voir(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $anomalie = $this->anomalie($request);

        return $this->vue('admin/anomalies/fiche', [
            'anomalie' => $anomalie,
            'photos' => array_map(fn (int $id): string => $this->factory->urlSignee()->pour($id, $utilisateur->id, true), $this->factory->anomalies()->photos((int) $anomalie['id'])),
            'peutTraiter' => $utilisateur->peut('anomalies.traiter'),
        ]);
    }

    public function traiter(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $anomalie = $this->anomalie($request);
        $statut = (string) $request->input('statut');
        if (!array_key_exists($statut, AnomalieRepository::STATUTS)) {
            return $this->redirigerAvecErreur('/admin/anomalies/' . $anomalie['id'], 'Statut invalide.');
        }
        $commentaire = trim((string) $request->input('commentaire', ''));
        if (in_array($statut, ['resolue', 'close'], true) && $commentaire === '') {
            return $this->redirigerAvecErreur('/admin/anomalies/' . $anomalie['id'], 'Décrivez les suites données avant de résoudre l\'anomalie.');
        }

        $this->factory->anomalies()->traiter((int) $anomalie['id'], $statut, $commentaire === '' ? null : mb_substr($commentaire, 0, 5000), $request->boolean('visible_client'), $utilisateur->id);
        $this->factory->audit()->journaliser('anomalie.traiter', 'anomalie', (int) $anomalie['id'], ['statut' => $statut]);

        return $this->rediriger('/admin/anomalies/' . $anomalie['id'], 'Anomalie mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function anomalie(Request $request): array
    {
        $anomalie = $this->factory->anomalies()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        if (!$this->factory->autorisation()->perimetre($this->utilisateur($request), 'rondes.suivre')->couvreSite((int) $anomalie['site_id'])) {
            throw HttpException::notFound();
        }

        return $anomalie;
    }
}
