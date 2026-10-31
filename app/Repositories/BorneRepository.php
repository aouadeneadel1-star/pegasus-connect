<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

final class BorneRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function pourSite(int $siteId): array
    {
        return $this->lignes('SELECT * FROM bornes WHERE site_id = :site ORDER BY statut, libelle', ['site' => $siteId]);
    }

    /**
     * Bornes actives équipées d'un lecteur NFC sur les sites donnés (association de badge).
     *
     * @param list<int> $siteIds
     * @return list<array<string, mixed>>
     */
    public function avecLecteurPourSites(array $siteIds): array
    {
        [$condition, $params] = self::dans('b.site_id', $siteIds, 's');

        return $this->lignes(
            "SELECT b.id, b.libelle, s.nom AS site FROM bornes b JOIN sites s ON s.id = b.site_id
             WHERE b.statut = 'active' AND b.lecteur_nfc <> 'aucun' AND s.badge_nfc_actif = 1
               AND $condition
             ORDER BY s.nom, b.libelle",
            $params,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne('SELECT * FROM bornes WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouverParJeton(string $jeton): ?array
    {
        return $this->ligne(
            "SELECT b.*, s.code AS site_code, s.nom AS site_nom, s.actif AS site_actif, s.badge_nfc_actif, s.badge_pin_requis
             FROM bornes b JOIN sites s ON s.id = b.site_id
             WHERE b.appareil_jeton_hash = :hash AND b.statut = 'active'",
            ['hash' => hash('sha256', $jeton)],
        );
    }

    public function creer(int $siteId, string $libelle, string $lecteurNfc, string $codeEnrolement, DateTimeImmutable $expiration): int
    {
        return $this->inserer('bornes', [
            'site_id' => $siteId,
            'libelle' => $libelle,
            'lecteur_nfc' => $lecteurNfc,
            'statut' => 'en_attente',
            'enrolement_code_hash' => hash('sha256', $codeEnrolement),
            'enrolement_expire_le' => Clock::sql($expiration),
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    public function nouveauCodeEnrolement(int $id, string $code, DateTimeImmutable $expiration): void
    {
        $this->modifierLigne('bornes', $id, [
            'enrolement_code_hash' => hash('sha256', $code),
            'enrolement_expire_le' => Clock::sql($expiration),
        ]);
    }

    /**
     * @return array<string, mixed>|null borne en attente correspondant au code, sur ce site
     */
    public function trouverParCodeEnrolement(int $siteId, string $code): ?array
    {
        return $this->ligne(
            "SELECT * FROM bornes
             WHERE site_id = :site AND enrolement_code_hash = :hash AND enrolement_expire_le > :maintenant AND statut <> 'revoquee'",
            ['site' => $siteId, 'hash' => hash('sha256', $code), 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    /**
     * Enrôlement : la borne reçoit un nouveau jeton d'appareil et une nouvelle version de clé.
     */
    public function enroler(int $id, string $jetonAppareil, ?int $enroleePar, string $ip, string $userAgent): int
    {
        $this->executer(
            "UPDATE bornes SET appareil_jeton_hash = :hash, statut = 'active', cle_version = cle_version + 1,
                    enrolement_code_hash = NULL, enrolement_expire_le = NULL,
                    enrolee_le = :maintenant, enrolee_par = :par, derniere_activite = :activite,
                    derniere_ip = :ip, user_agent = :ua, revoquee_le = NULL
             WHERE id = :id",
            [
                'id' => $id,
                'hash' => hash('sha256', $jetonAppareil),
                'maintenant' => Clock::sql(Clock::now()),
                'activite' => Clock::sql(Clock::now()),
                'par' => $enroleePar,
                'ip' => (string) inet_pton($ip),
                'ua' => mb_substr($userAgent, 0, 255),
            ],
        );

        return (int) $this->valeur('SELECT cle_version FROM bornes WHERE id = :id', ['id' => $id]);
    }

    public function battement(int $id, string $ip, ?bool $lecteurNfcOk): void
    {
        $this->modifierLigne('bornes', $id, [
            'derniere_activite' => Clock::sql(Clock::now()),
            'derniere_ip' => (string) inet_pton($ip),
            'lecteur_nfc_ok' => $lecteurNfcOk === null ? null : ($lecteurNfcOk ? 1 : 0),
        ]);
    }

    public function revoquer(int $id): void
    {
        $this->modifierLigne('bornes', $id, [
            'statut' => 'revoquee',
            'appareil_jeton_hash' => null,
            'revoquee_le' => Clock::sql(Clock::now()),
        ]);
    }

    public function modifier(int $id, string $libelle, string $lecteurNfc): void
    {
        $this->modifierLigne('bornes', $id, ['libelle' => $libelle, 'lecteur_nfc' => $lecteurNfc]);
    }

    /**
     * @return list<array<string, mixed>> bornes actives sans signal depuis la limite
     */
    public function silencieuses(DateTimeImmutable $limite): array
    {
        return $this->lignes(
            "SELECT b.*, s.nom AS site_nom FROM bornes b JOIN sites s ON s.id = b.site_id
             WHERE b.statut = 'active' AND s.actif = 1 AND (b.derniere_activite IS NULL OR b.derniere_activite < :limite)",
            ['limite' => Clock::sql($limite)],
        );
    }
}
