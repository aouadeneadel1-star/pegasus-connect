<?php

declare(strict_types=1);

namespace App\Services\Securite;

use App\Core\Clock;
use PDO;

/**
 * Compteur à fenêtre fixe stocké en base : le mutualisé n'offre ni Redis ni APCu partagé.
 */
final class LimiteDebit
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Enregistre un essai et indique s'il reste sous la limite.
     */
    public function essayer(string $cle, int $maximum, int $fenetreSecondes): bool
    {
        $empreinte = hash('sha256', $cle);
        $maintenant = Clock::now();

        $this->pdo->prepare('DELETE FROM limites_debit WHERE cle = :cle AND expire_le <= :maintenant')
            ->execute(['cle' => $empreinte, 'maintenant' => Clock::sql($maintenant)]);

        $this->pdo->prepare(
            'INSERT INTO limites_debit (cle, compteur, expire_le) VALUES (:cle, 1, :expire)
             ON DUPLICATE KEY UPDATE compteur = compteur + 1'
        )->execute([
            'cle' => $empreinte,
            'expire' => Clock::sql($maintenant->modify('+' . $fenetreSecondes . ' seconds')),
        ]);

        $statement = $this->pdo->prepare('SELECT compteur FROM limites_debit WHERE cle = :cle');
        $statement->execute(['cle' => $empreinte]);

        return (int) $statement->fetchColumn() <= $maximum;
    }

    public function estBloque(string $cle, int $maximum): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT compteur FROM limites_debit WHERE cle = :cle AND expire_le > :maintenant'
        );
        $statement->execute(['cle' => hash('sha256', $cle), 'maintenant' => Clock::sql(Clock::now())]);

        return (int) $statement->fetchColumn() >= $maximum;
    }

    public function reinitialiser(string $cle): void
    {
        $this->pdo->prepare('DELETE FROM limites_debit WHERE cle = :cle')->execute(['cle' => hash('sha256', $cle)]);
    }

    public function purger(): int
    {
        $statement = $this->pdo->prepare('DELETE FROM limites_debit WHERE expire_le <= :maintenant');
        $statement->execute(['maintenant' => Clock::sql(Clock::now())]);

        return $statement->rowCount();
    }
}
