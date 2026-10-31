<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Perimetre;
use App\Services\ValidationException;

/**
 * Import et publication des bulletins de paie en trois étapes : dépôt, contrôle, publication.
 */
final class PaieController extends Controller
{
    public function index(Request $request): Response
    {
        [$annee, $mois] = self::mois(null);
        $precedent = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $mois)))->modify('-1 month');

        return $this->vue('admin/paie/index', [
            'imports' => $this->factory->paie()->imports(),
            'periodeDefaut' => $precedent->format('Y-m'),
        ]);
    }

    public function importer(Request $request): Response
    {
        [$annee, $mois] = self::mois($request->input('periode'));
        try {
            $id = $this->factory->importPaie()->analyser($annee, $mois, $request->files('fichiers'), $this->utilisateur($request)->id);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/paie', $exception->getMessage());
        }

        return $this->rediriger('/admin/paie/import/' . $id, 'Fichiers analysés. Vérifiez les rapprochements avant de publier.');
    }

    public function voir(Request $request): Response
    {
        $import = $this->factory->paie()->import($request->intParam('id')) ?? throw HttpException::notFound();
        $lignes = $this->factory->paie()->lignesImport((int) $import['id']);
        foreach ($lignes as &$ligne) {
            $ligne['url'] = $ligne['document_id'] === null ? null : $this->factory->urlSignee()->pour((int) $ligne['document_id'], $this->utilisateur($request)->id, true);
        }
        unset($ligne);

        return $this->vue('admin/paie/import', [
            'import' => $import,
            'lignes' => $lignes,
            'fiches' => $import['statut'] === 'publie' ? $this->factory->paie()->fichesImport((int) $import['id']) : [],
            'agents' => $this->factory->agents()->choix(Perimetre::tout()),
        ]);
    }

    public function associer(Request $request): Response
    {
        $importId = $request->intParam('id');
        try {
            $this->factory->importPaie()->associer($importId, (int) $request->input('ligne_id', '0'), (int) $request->input('agent_id', '0'));
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/paie/import/' . $importId, $exception->getMessage());
        }

        return $this->rediriger('/admin/paie/import/' . $importId, 'Bulletin rapproché.');
    }

    public function publier(Request $request): Response
    {
        $importId = $request->intParam('id');
        try {
            $nombre = $this->factory->importPaie()->publier($importId, $this->utilisateur($request)->id);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/paie/import/' . $importId, $exception->getMessage());
        }

        return $this->rediriger('/admin/paie/import/' . $importId, sprintf('%d bulletin(s) publié(s). Les agents sont prévenus.', $nombre));
    }

    public function annuler(Request $request): Response
    {
        try {
            $this->factory->importPaie()->annuler($request->intParam('id'));
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/paie', $exception->getMessage());
        }

        return $this->rediriger('/admin/paie', 'Import annulé.');
    }

    public function depublier(Request $request): Response
    {
        $fiche = $this->factory->paie()->fiche($request->intParam('id')) ?? throw HttpException::notFound();
        try {
            $this->factory->importPaie()->depublier((int) $fiche['id'], (string) $request->input('motif', ''));
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/paie/import/' . $fiche['import_id'], $exception->getMessage());
        }

        return $this->rediriger('/admin/paie/import/' . $fiche['import_id'], 'Bulletin retiré de l\'espace de l\'agent.');
    }
}
