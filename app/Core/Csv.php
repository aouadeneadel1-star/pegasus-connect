<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Export CSV lisible par Excel en français : séparateur point-virgule, UTF-8 avec BOM.
 * Les cellules commençant par =, +, - ou @ sont neutralisées (injection de formule).
 */
final class Csv
{
    /**
     * @param iterable<list<scalar|null>> $lignes
     */
    public static function reponse(string $nomFichier, iterable $lignes): Response
    {
        $flux = fopen('php://temp', 'r+');
        fwrite($flux, "\xEF\xBB\xBF");
        foreach ($lignes as $ligne) {
            fputcsv($flux, array_map(self::cellule(...), $ligne), ';', '"', '');
        }
        rewind($flux);
        $contenu = (string) stream_get_contents($flux);
        fclose($flux);

        return Response::download($contenu, $nomFichier, 'text/csv; charset=utf-8');
    }

    private static function cellule(string|int|float|bool|null $valeur): string
    {
        $texte = (string) $valeur;

        return $texte !== '' && in_array($texte[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($texte)
            ? "'" . $texte
            : $texte;
    }
}
