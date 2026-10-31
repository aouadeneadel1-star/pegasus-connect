<?php

declare(strict_types=1);

namespace App\Services\Stockage;

use App\Core\Clock;
use App\Services\Crypto\Cles;

/**
 * Lien de téléchargement temporaire, lié à un document et à l'utilisateur qui l'a demandé.
 * Recopié dans une autre session, il est refusé.
 */
final class UrlSignee
{
    private readonly string $cle;

    public function __construct(Cles $cles, private readonly int $dureeSecondes = 300)
    {
        $this->cle = $cles->pour('url-signee');
    }

    public function pour(int $documentId, int $utilisateurId, bool $affichage = false): string
    {
        $charge = implode('.', [$documentId, $utilisateurId, Clock::now()->getTimestamp() + $this->dureeSecondes, $affichage ? 'i' : 'a']);

        return '/fichier/' . self::b64($charge) . '.' . self::b64(substr(hash_hmac('sha256', $charge, $this->cle, true), 0, 20));
    }

    /**
     * @return array{document: int, utilisateur: int, affichage: bool}|null
     */
    public function verifier(string $jeton): ?array
    {
        $parties = explode('.', $jeton);
        if (count($parties) !== 2) {
            return null;
        }

        $charge = self::decoder($parties[0]);
        $signature = self::decoder($parties[1]);
        if ($charge === null || $signature === null) {
            return null;
        }

        $attendue = substr(hash_hmac('sha256', $charge, $this->cle, true), 0, 20);
        if (!hash_equals($attendue, $signature)) {
            return null;
        }

        $champs = explode('.', $charge);
        if (count($champs) !== 4 || (int) $champs[2] < Clock::now()->getTimestamp()) {
            return null;
        }

        return ['document' => (int) $champs[0], 'utilisateur' => (int) $champs[1], 'affichage' => $champs[3] === 'i'];
    }

    private static function b64(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }

    private static function decoder(string $valeur): ?string
    {
        $decode = base64_decode(strtr($valeur, '-_', '+/'), true);

        return $decode === false ? null : $decode;
    }
}
