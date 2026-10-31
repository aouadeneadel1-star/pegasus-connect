<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * Téléphones utilisés pour pointer. L'identifiant est généré par l'application
 * (stockage local du navigateur) : il sert à repérer un appareil prêté, pas à authentifier.
 */
final class AppareilRepository extends Repository
{
    public function enregistrer(string $uuid, int $utilisateurId, string $userAgent): int
    {
        $maintenant = Clock::sql(Clock::now());
        $this->executer(
            'INSERT INTO appareils (appareil_uuid, user_agent, plateforme, premier_vu, dernier_vu)
             VALUES (:uuid, :ua, :plateforme, :premier, :dernier)
             ON DUPLICATE KEY UPDATE dernier_vu = :maj, user_agent = :ua_maj',
            [
                'uuid' => $uuid,
                'ua' => mb_substr($userAgent, 0, 255),
                'plateforme' => self::plateforme($userAgent),
                'premier' => $maintenant,
                'dernier' => $maintenant,
                'maj' => $maintenant,
                'ua_maj' => mb_substr($userAgent, 0, 255),
            ],
        );
        $id = (int) $this->valeur('SELECT id FROM appareils WHERE appareil_uuid = :uuid', ['uuid' => $uuid]);

        $this->executer(
            'INSERT INTO appareils_utilisateurs (appareil_id, utilisateur_id, premier_vu, dernier_vu)
             VALUES (:appareil, :utilisateur, :premier, :dernier)
             ON DUPLICATE KEY UPDATE dernier_vu = :maj',
            ['appareil' => $id, 'utilisateur' => $utilisateurId, 'premier' => $maintenant, 'dernier' => $maintenant, 'maj' => $maintenant],
        );

        return $id;
    }

    /**
     * Un autre compte a-t-il utilisé cet appareil depuis la date donnée ?
     */
    public function partageDepuis(int $appareilId, int $utilisateurId, DateTimeImmutable $depuis): bool
    {
        return (bool) $this->valeur(
            'SELECT COUNT(*) FROM appareils_utilisateurs
             WHERE appareil_id = :appareil AND utilisateur_id <> :utilisateur AND dernier_vu >= :depuis',
            ['appareil' => $appareilId, 'utilisateur' => $utilisateurId, 'depuis' => Clock::sql($depuis)],
        );
    }

    private static function plateforme(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
