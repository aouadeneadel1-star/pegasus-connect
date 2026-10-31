<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Clock;
use DateTimeImmutable;
use PDO;

/**
 * Les e-mails ne partent jamais pendant la requête : ils sont mis en file et envoyés
 * par le cron, par lots, pour respecter les quotas d'envoi du mutualisé.
 */
final class FileEmails
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, scalar|null> $donnees variables du gabarit, sans donnée sensible
     */
    public function ajouter(
        string $destinataire,
        string $sujet,
        string $gabarit,
        array $donnees = [],
        ?DateTimeImmutable $apres = null,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO file_emails (destinataire, sujet, gabarit, donnees, envoyer_apres, created_at)
             VALUES (:destinataire, :sujet, :gabarit, :donnees, :apres, :maintenant)'
        )->execute([
            'destinataire' => $destinataire,
            'sujet' => $sujet,
            'gabarit' => $gabarit,
            'donnees' => json_encode($donnees, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'apres' => Clock::sql($apres ?? Clock::now()),
            'maintenant' => Clock::sql(Clock::now()),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function prochains(int $limite): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM file_emails
             WHERE statut = 'attente' AND envoyer_apres <= :maintenant
             ORDER BY envoyer_apres, id
             LIMIT :limite"
        );
        $statement->bindValue(':maintenant', Clock::sql(Clock::now()));
        $statement->bindValue(':limite', $limite, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marquerEnvoye(int $id): void
    {
        $this->pdo->prepare("UPDATE file_emails SET statut = 'envoye', envoye_le = :le, derniere_erreur = NULL WHERE id = :id")
            ->execute(['id' => $id, 'le' => Clock::sql(Clock::now())]);
    }

    /**
     * Nouvel essai avec un délai croissant (5, 10, 20, 40 min) ; abandon après 5 tentatives.
     */
    public function marquerEchec(int $id, int $tentatives, string $erreur): void
    {
        $tentatives++;
        $this->pdo->prepare(
            'UPDATE file_emails
             SET tentatives = :tentatives, derniere_erreur = :erreur, statut = :statut, envoyer_apres = :apres
             WHERE id = :id'
        )->execute([
            'id' => $id,
            'tentatives' => $tentatives,
            'erreur' => mb_substr($erreur, 0, 255),
            'statut' => $tentatives >= 5 ? 'echec' : 'attente',
            'apres' => Clock::sql(Clock::now()->modify('+' . (5 * 2 ** ($tentatives - 1)) . ' minutes')),
        ]);
    }
}
