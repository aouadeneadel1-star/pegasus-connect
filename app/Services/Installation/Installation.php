<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Core\Clock;
use App\Core\Env;
use App\Core\Log;
use App\Core\Migrator;
use App\Core\SqlScript;
use PDO;
use RuntimeException;

/**
 * Installation et mise à jour du schéma sans ligne de commande : sur un hébergement mutualisé,
 * déposer les fichiers suffit. La première requête crée les tables, charge les données
 * initiales et prépare le compte administrateur ; une nouvelle version applique ses migrations
 * à la première requête qui suit le dépôt.
 *
 * Un fichier témoin mémorise la liste des migrations déjà appliquées : tant qu'elle ne change
 * pas, aucune requête SQL n'est faite.
 */
final class Installation
{
    private const VERROU = 'pegasus_installation';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $racine,
        private readonly string $temoin,
    ) {
    }

    public function verifier(): void
    {
        $signature = $this->signature();
        if (is_file($this->temoin) && trim((string) file_get_contents($this->temoin)) === $signature) {
            return;
        }

        // Plusieurs visiteurs peuvent arriver pendant l'installation : un seul l'exécute,
        // les autres attendent qu'elle soit terminée.
        if ((int) $this->pdo->query("SELECT GET_LOCK('" . self::VERROU . "', 60)")->fetchColumn() !== 1) {
            throw new RuntimeException('Installation en cours, réessayez dans un instant.');
        }

        try {
            $migrator = new Migrator($this->pdo, $this->racine . '/database/migrations');
            $neuve = !$this->tableExiste('schema_migrations') || $migrator->executed() === [];
            $migrator->ensureHistoryTable();
            $appliquees = $migrator->migrate(static fn (string $fichier) => Log::info('Migration appliquée', ['fichier' => $fichier]));

            // Les données initiales ne sont chargées qu'à la création : les rejouer à chaque
            // mise à jour écraserait les paramètres modifiés depuis le back-office.
            if ($neuve) {
                $this->donneesInitiales();
            }
            $this->premierAdministrateur();

            if (!is_dir(dirname($this->temoin))) {
                mkdir(dirname($this->temoin), 0700, true);
            }
            file_put_contents($this->temoin, $signature, LOCK_EX);
            if ($appliquees > 0) {
                Log::info('Schéma à jour', ['migrations' => $appliquees, 'installation' => $neuve]);
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('" . self::VERROU . "')");
        }
    }

    private function signature(): string
    {
        $fichiers = array_map('basename', glob($this->racine . '/database/migrations/*.sql') ?: []);
        sort($fichiers, SORT_STRING);

        return hash('sha256', implode("\n", $fichiers));
    }

    private function tableExiste(string $table): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
        $statement->execute(['t' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function donneesInitiales(): void
    {
        $fichiers = glob($this->racine . '/database/seeds/*.sql') ?: [];
        sort($fichiers, SORT_STRING);
        foreach ($fichiers as $fichier) {
            SqlScript::runFile($this->pdo, $fichier);
        }
    }

    /**
     * Compte administrateur décrit dans .env, créé en attente d'activation. Son lien
     * d'invitation est connu du seul propriétaire : .env n'en contient que l'empreinte.
     */
    private function premierAdministrateur(): void
    {
        $email = mb_strtolower(trim((string) Env::get('INSTALLATION_ADMIN_EMAIL', '')));
        $empreinte = strtolower(trim((string) Env::get('INSTALLATION_INVITATION', '')));
        if ($email === '' || preg_match('/^[0-9a-f]{64}$/', $empreinte) !== 1) {
            return;
        }

        $administrateurs = (int) $this->pdo->query("SELECT COUNT(*) FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin'")->fetchColumn();
        if ($administrateurs > 0) {
            return;
        }

        $this->pdo->prepare(
            "INSERT INTO utilisateurs (role_id, email, nom_complet, statut)
             SELECT id, :email, :nom, 'invite' FROM roles WHERE code = 'admin'"
        )->execute(['email' => $email, 'nom' => (string) Env::get('INSTALLATION_ADMIN_NOM', 'Administrateur')]);

        $this->pdo->prepare(
            "INSERT INTO jetons_compte (utilisateur_id, type, jeton_hash, expire_le)
             VALUES (:utilisateur, 'invitation', :empreinte, :expire)"
        )->execute([
            'utilisateur' => (int) $this->pdo->lastInsertId(),
            'empreinte' => $empreinte,
            'expire' => Clock::sql(Clock::now()->modify('+14 days')),
        ]);
        Log::info('Compte administrateur initial créé', ['email' => $email]);
    }
}
