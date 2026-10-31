<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Core\Clock;
use App\Models\UtilisateurConnecte;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Journal d'audit chaîné : chaque ligne contient l'empreinte de la précédente.
 * Modifier ou supprimer une ligne casse la chaîne, ce que détecte VerificationAudit.
 */
final class AuditLogger
{
    public const GENESE = '0000000000000000000000000000000000000000000000000000000000000000';

    private ?UtilisateurConnecte $auteur = null;

    private string $ip = '';

    private string $userAgent = '';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function definirContexte(?UtilisateurConnecte $auteur, string $ip, string $userAgent): void
    {
        $this->auteur = $auteur;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }

    /**
     * @param array<string, scalar|null|array<mixed>> $details ne jamais y placer de donnée sensible en clair
     */
    public function journaliser(
        string $action,
        ?string $objetType = null,
        ?int $objetId = null,
        array $details = [],
        ?int $utilisateurId = null,
    ): void {
        $ligne = [
            'utilisateur_id' => $utilisateurId ?? $this->auteur?->id,
            'role_code' => $utilisateurId === null ? $this->auteur?->role->value : null,
            'action' => $action,
            'objet_type' => $objetType,
            'objet_id' => $objetId,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => $this->ip === '' ? null : bin2hex((string) inet_pton($this->ip)),
            'user_agent' => $this->userAgent === '' ? null : $this->userAgent,
            'created_at' => Clock::sql(Clock::now(), true),
        ];

        // Un verrou nommé sérialise les écritures : deux lignes ne peuvent pas partager le même maillon.
        $verrou = (int) $this->pdo->query("SELECT GET_LOCK('pegasus_audit', 10)")->fetchColumn();
        if ($verrou !== 1) {
            throw new RuntimeException('Journal d\'audit indisponible (verrou).');
        }

        try {
            $precedent = $this->pdo->query('SELECT hash FROM audit ORDER BY id DESC LIMIT 1')->fetchColumn();
            $precedent = is_string($precedent) ? $precedent : self::GENESE;
            $ligne['hash_precedent'] = $precedent;
            $ligne['hash'] = self::empreinte($precedent, $ligne);

            $statement = $this->pdo->prepare(
                'INSERT INTO audit (utilisateur_id, role_code, action, objet_type, objet_id, details, ip, user_agent,
                                    created_at, hash_precedent, hash)
                 VALUES (:utilisateur_id, :role_code, :action, :objet_type, :objet_id, :details, UNHEX(:ip),
                         :user_agent, :created_at, :hash_precedent, :hash)'
            );
            $statement->execute($ligne);
        } finally {
            try {
                $this->pdo->query("SELECT RELEASE_LOCK('pegasus_audit')");
            } catch (Throwable) {
                // Le verrou est de toute façon libéré à la fermeture de la connexion.
            }
        }
    }

    /**
     * @param array<string, scalar|null> $ligne
     */
    public static function empreinte(string $precedent, array $ligne): string
    {
        $contenu = implode('|', [
            $precedent,
            (string) ($ligne['utilisateur_id'] ?? ''),
            (string) ($ligne['role_code'] ?? ''),
            (string) $ligne['action'],
            (string) ($ligne['objet_type'] ?? ''),
            (string) ($ligne['objet_id'] ?? ''),
            (string) ($ligne['details'] ?? ''),
            (string) ($ligne['ip'] ?? ''),
            (string) $ligne['created_at'],
        ]);

        return hash('sha256', $contenu);
    }
}
