<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class ConsentementRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function dernier(int $utilisateurId, string $type): ?array
    {
        return $this->ligne(
            'SELECT * FROM consentements
             WHERE utilisateur_id = :utilisateur AND type = :type
             ORDER BY created_at DESC, id DESC LIMIT 1',
            ['utilisateur' => $utilisateurId, 'type' => $type],
        );
    }

    public function enregistrer(int $utilisateurId, string $type, string $version, bool $accorde, string $ip): void
    {
        $this->inserer('consentements', [
            'utilisateur_id' => $utilisateurId,
            'type' => $type,
            'version_texte' => $version,
            'accorde' => $accorde ? 1 : 0,
            'ip' => (string) inet_pton($ip),
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historique(int $utilisateurId): array
    {
        return $this->lignes(
            'SELECT type, version_texte, accorde, created_at FROM consentements
             WHERE utilisateur_id = :utilisateur ORDER BY created_at DESC',
            ['utilisateur' => $utilisateurId],
        );
    }
}
