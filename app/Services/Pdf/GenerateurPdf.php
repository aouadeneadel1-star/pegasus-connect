<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Rendu PDF des gabarits de app/Views/pdf. Aucune ressource distante n'est chargée :
 * les images sont lues dans public/assets uniquement.
 */
final class GenerateurPdf
{
    public function __construct(
        private readonly View $vues,
        private readonly string $dossierPublic,
        private readonly string $dossierCache,
    ) {
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function rendre(string $gabarit, array $donnees, string $orientation = 'portrait'): string
    {
        $html = $this->vues->render('pdf/' . $gabarit, [...$donnees, 'racinePublique' => $this->dossierPublic]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('fontCache', $this->dossierCache);
        $options->set('tempDir', $this->dossierCache);
        $options->setChroot($this->dossierPublic);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 90, $canvas->get_height() - 28, 'Page {PAGE_NUM} / {PAGE_COUNT}', null, 7, [0.35, 0.37, 0.42]);

        return (string) $dompdf->output();
    }
}
