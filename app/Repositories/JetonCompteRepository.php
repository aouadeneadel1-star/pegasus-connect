<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use JsonException;

/**
 * Jetons à usage unique (invitation, réinitialisation) ou durables (appareil de confiance).
 * Seule l'empreinte SHA-256 est stockée : une fuite de la base ne permet pas de les rejouer.
 */
final class JetonCompteRepository extends Repository
{
    /**
     * @param array<string, scalar|null> $meta
     * @return string jeton en clair, à transmettre une seule fois
     * @throws JsonException
     */
    public function creer(int $utilisateurId, string $type, int $dureeSecondes, array $meta = []): string
    {
        $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->inserer('jetons_compte', [
            'utilisateur_id' => $utilisateurId,
            'type' => $type,
            'jeton_hash' => hash('sha256', $jeton),
            'expire_le' => Clock::sql(Clock::now()->modify('+' . $dureeSecondes . ' seconds')),
            'meta' => $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR),
        ]);

        return $jeton;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouverValide(string $jeton, string $type): ?array
    {
        if ($jeton === '' || strlen($jeton) > 64) {
            return null;
        }

        return $this->ligne(
            'SELECT * FROM jetons_compte
             WHERE jeton_hash = :hash AND type = :type AND utilise_le IS NULL AND expire_le > :maintenant',
            ['hash' => hash('sha256', $jeton), 'type' => $type, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function consommer(int $id): void
    {
        $this->executer(
            'UPDATE jetons_compte SET utilise_le = :maintenant WHERE id = :id AND utilise_le IS NULL',
            ['id' => $id, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function invaliderPour(int $utilisateurId, string $type): void
    {
        $this->executer(
            'UPDATE jetons_compte SET utilise_le = :maintenant
             WHERE utilisateur_id = :utilisateur AND type = :type AND utilise_le IS NULL',
            ['utilisateur' => $utilisateurId, 'type' => $type, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function purgerExpires(): int
    {
        return $this->executer(
            'DELETE FROM jetons_compte WHERE expire_le < :limite',
            ['limite' => Clock::sql(Clock::now()->modify('-30 days'))],
        )->rowCount();
    }
}
