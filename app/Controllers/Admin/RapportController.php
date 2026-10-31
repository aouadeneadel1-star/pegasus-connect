<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ValidationException;

/**
 * Rapports mensuels PDF par agent, générés un par un. La génération en masse et l'archive ZIP
 * sont prévues dans un lot ultérieur.
 */
final class RapportController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        [$annee, $mois] = self::mois($request->query('mois'));
        $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'rapports.voir');
        $agents = array_values(array_filter($this->factory->agents()->actifs(), static fn (array $a): bool => $perimetre->couvreAgent((int) $a['id'])));

        return $this->vue('admin/rapports', [
            'annee' => $annee,
            'mois' => $mois,
            'agents' => $agents,
            'rapports' => $this->factory->rapports()->duMois($annee, $mois),
            'peutGerer' => $utilisateur->peut('rapports.gerer'),
        ]);
    }

    public function generer(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        [$annee, $mois] = self::mois($request->input('mois'));
        $agentId = (int) $request->input('agent_id', '0');
        $retour = sprintf('/admin/rapports?mois=%04d-%02d', $annee, $mois);
        if (!$this->factory->autorisation()->perimetre($utilisateur, 'rapports.voir')->couvreAgent($agentId)) {
            throw HttpException::notFound();
        }

        try {
            $this->factory->rapportMensuel()->generer($agentId, $annee, $mois, $utilisateur->id);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur($retour, $exception->getMessage());
        }

        return $this->rediriger($retour, 'Rapport généré. Vérifiez-le avant de l\'envoyer à l\'agent.');
    }

    public function envoyer(Request $request): Response
    {
        $rapport = $this->rapport($request);
        try {
            $this->factory->rapportMensuel()->envoyer((int) $rapport['id']);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur(sprintf('/admin/rapports?mois=%04d-%02d', $rapport['annee'], $rapport['mois']), $exception->getMessage());
        }

        return $this->rediriger(sprintf('/admin/rapports?mois=%04d-%02d', $rapport['annee'], $rapport['mois']), 'Rapport envoyé : il est disponible dans l\'espace de l\'agent.');
    }

    public function telecharger(Request $request): Response
    {
        $rapport = $this->rapport($request);
        if ($rapport['document_id'] === null) {
            throw HttpException::notFound();
        }

        return Response::redirect($this->factory->urlSignee()->pour((int) $rapport['document_id'], $this->utilisateur($request)->id, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function rapport(Request $request): array
    {
        $rapport = $this->factory->rapports()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        if (!$this->factory->autorisation()->perimetre($this->utilisateur($request), 'rapports.voir')->couvreAgent((int) $rapport['agent_id'])) {
            throw HttpException::notFound();
        }

        return $rapport;
    }
}
