<?php

declare(strict_types=1);

namespace App\Services\Qr;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Rendu des QR codes côté serveur : enrôlement 2FA, étiquettes de points, QR statiques.
 * Le QR dynamique de la borne est calculé dans le navigateur (assets/js/qr-encoder.js).
 */
final class QrImage
{
    public function svg(string $contenu, int $taille = 300): string
    {
        // Contenus ASCII (jetons, URI otpauth) : l'ISO-8859-1 évite l'en-tête ECI qu'ajoute l'UTF-8
        // et que certains lecteurs anciens interprètent mal. La marge d'environ quatre modules
        // exigée par la norme garde l'étiquette lisible même collée près d'un texte ou d'un cadre.
        $qrCode = new QrCode(
            data: $contenu,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: $taille,
            margin: max(16, intdiv($taille, 10)),
        );

        return (new SvgWriter())->write($qrCode, null, null, [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])->getString();
    }

    public function dataUri(string $contenu, int $taille = 300): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($contenu, $taille));
    }
}
