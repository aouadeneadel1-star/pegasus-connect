<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class NotificationRepository extends Repository
{
    public function creer(int $utilisateurId, string $type, string $titre, ?string $message, ?string $lien): void
    {
        $this->inserer('notifications', [
            'utilisateur_id' => $utilisateurId,
            'type' => $type,
            'titre' => mb_substr($titre, 0, 150),
            'message' => $message === null ? null : mb_substr($message, 0, 500),
            'lien' => $lien,
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    public function nonLues(int $utilisateurId): int
    {
        return (int) $this->valeur(
            'SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :utilisateur AND lue_le IS NULL',
            ['utilisateur' => $utilisateurId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentes(int $utilisateurId, int $limite = 50): array
    {
        return $this->lignes(
            'SELECT id, type, titre, message, lien, lue_le, created_at FROM notifications
             WHERE utilisateur_id = :utilisateur ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min($limite, 200)),
            ['utilisateur' => $utilisateurId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id, int $utilisateurId): ?array
    {
        return $this->ligne(
            'SELECT * FROM notifications WHERE id = :id AND utilisateur_id = :utilisateur',
            ['id' => $id, 'utilisateur' => $utilisateurId],
        );
    }

    public function marquerLue(int $id, int $utilisateurId): void
    {
        $this->executer(
            'UPDATE notifications SET lue_le = :maintenant WHERE id = :id AND utilisateur_id = :utilisateur AND lue_le IS NULL',
            ['id' => $id, 'utilisateur' => $utilisateurId, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function toutMarquerLu(int $utilisateurId): void
    {
        $this->executer(
            'UPDATE notifications SET lue_le = :maintenant WHERE utilisateur_id = :utilisateur AND lue_le IS NULL',
            ['utilisateur' => $utilisateurId, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function purgerAvant(string $limite): int
    {
        return $this->executer('DELETE FROM notifications WHERE created_at < :limite', ['limite' => $limite])->rowCount();
    }
}
