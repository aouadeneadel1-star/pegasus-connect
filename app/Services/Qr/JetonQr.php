<?php

declare(strict_types=1);

namespace App\Services\Qr;

use App\Services\Crypto\Cles;

/**
 * Contenu signé des QR codes (cahier des charges, § 5.6.1).
 *
 *   PGS1.D.{site}.{borne}.{fenêtre}.{signature}   QR dynamique d'une borne, renouvelé toutes les 60 s
 *   PGS1.S.{site}.{version}.{signature}            QR statique d'un site sans écran
 *   PGS1.R.{point}.{version}.{signature}           QR d'un point de contrôle de ronde
 *
 * La signature est un HMAC-SHA256 tronqué à 128 bits, encodé en base64url. Le QR identifie
 * un lieu, jamais un agent.
 */
final class JetonQr
{
    public const FENETRE_SECONDES = 60;
    private const PREFIXE = 'PGS1';

    public function __construct(private readonly Cles $cles)
    {
    }

    /**
     * Clé propre à une borne, remise à l'enrôlement : la borne calcule ses QR sans réseau.
     */
    public function cleBorne(int $borneId, int $version): string
    {
        return $this->cles->pour('borne:' . $borneId . ':v' . $version);
    }

    public static function fenetre(int $timestamp): int
    {
        return intdiv($timestamp, self::FENETRE_SECONDES);
    }

    public function dynamique(int $siteId, int $borneId, int $cleVersion, int $fenetre): string
    {
        $charge = sprintf('D.%d.%d.%d', $siteId, $borneId, $fenetre);

        return self::PREFIXE . '.' . $charge . '.' . self::signer($charge, $this->cleBorne($borneId, $cleVersion));
    }

    public function statique(int $siteId, int $version): string
    {
        $charge = sprintf('S.%d.%d', $siteId, $version);

        return self::PREFIXE . '.' . $charge . '.' . self::signer($charge, $this->cles->pour('statique:v1'));
    }

    public function pointControle(int $pointId, int $version): string
    {
        $charge = sprintf('R.%d.%d', $pointId, $version);

        return self::PREFIXE . '.' . $charge . '.' . self::signer($charge, $this->cles->pour('rondes:v1'));
    }

    /**
     * Décode un contenu de QR sans vérifier sa signature (elle dépend de la borne ou du point).
     */
    public static function lire(string $contenu): ContenuQr
    {
        $parties = explode('.', trim($contenu));
        if (($parties[0] ?? '') !== self::PREFIXE) {
            throw new JetonInvalideException('Ce QR code n\'est pas un code Pegasus.');
        }

        $type = $parties[1] ?? '';
        $attendu = match ($type) {
            'D' => 6,
            'S', 'R' => 5,
            default => 0,
        };
        if (count($parties) !== $attendu) {
            throw new JetonInvalideException('QR code illisible.');
        }

        $nombres = array_slice($parties, 2, $attendu - 3);
        foreach ($nombres as $nombre) {
            if (preg_match('/^\d{1,12}$/', $nombre) !== 1) {
                throw new JetonInvalideException('QR code illisible.');
            }
        }

        return new ContenuQr($type, array_map('intval', $nombres), implode('.', array_slice($parties, 1, $attendu - 2)), $parties[$attendu - 1]);
    }

    public function signatureDynamiqueValide(ContenuQr $qr, int $cleVersion): bool
    {
        return $qr->type === 'D'
            && hash_equals(self::signer($qr->charge, $this->cleBorne($qr->valeurs[1], $cleVersion)), $qr->signature);
    }

    public function signatureStatiqueValide(ContenuQr $qr): bool
    {
        return $qr->type === 'S' && hash_equals(self::signer($qr->charge, $this->cles->pour('statique:v1')), $qr->signature);
    }

    public function signaturePointValide(ContenuQr $qr): bool
    {
        return $qr->type === 'R' && hash_equals(self::signer($qr->charge, $this->cles->pour('rondes:v1')), $qr->signature);
    }

    public static function signer(string $charge, string $cle): string
    {
        return rtrim(strtr(base64_encode(substr(hash_hmac('sha256', $charge, $cle, true), 0, 16)), '+/', '-_'), '=');
    }
}
