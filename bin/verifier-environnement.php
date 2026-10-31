<?php

declare(strict_types=1);

// Usage : php bin/verifier-environnement.php [--env=testing]
// À lancer en local et sur o2switch avant chaque mise en production.
// Code de sortie 1 si au moins un contrôle échoue.

use App\Core\Console;
use App\Core\Database;
use App\Core\Env;

$racine = require __DIR__ . '/bootstrap.php';

$resultats = [];

$controle = static function (string $libelle, bool $ok, string $detail = '') use (&$resultats): void {
    $resultats[] = $ok;
    $message = $detail === '' ? $libelle : $libelle . ' — ' . $detail;
    $ok ? Console::ok($message) : Console::ko($message);
};

Console::line('Vérification de l\'environnement Pegasus Connect');
Console::line('Fichier d\'environnement : ' . basename($fichierEnv));
Console::line();

// PHP -------------------------------------------------------------------------------------------

$controle(
    sprintf('PHP %s (%s)', PHP_VERSION, PHP_SAPI),
    PHP_VERSION_ID >= 80200,
    PHP_VERSION_ID >= 80200 ? '' : 'version 8.2 minimum requise',
);

foreach (['pdo_mysql', 'sodium', 'gd', 'mbstring', 'fileinfo', 'zip', 'intl'] as $extension) {
    $controle('Extension ' . $extension, extension_loaded($extension), extension_loaded($extension) ? '' : 'non chargée');
}

if (extension_loaded('gd')) {
    $gd = gd_info();
    $jpeg = (bool) ($gd['JPEG Support'] ?? false);
    $png = (bool) ($gd['PNG Support'] ?? false);
    $webp = (bool) ($gd['WebP Support'] ?? false);
    $controle(
        'GD : formats d\'image',
        $jpeg && $png,
        sprintf('JPEG %s, PNG %s, WebP %s', $jpeg ? 'oui' : 'non', $png ? 'oui' : 'non', $webp ? 'oui' : 'non'),
    );
}

// Chiffrement des archives ZIP (export RGPD) --------------------------------------------------

$controle('ZipArchive : chiffrement AES-256', ...verifierZipAes($racine . '/storage/tmp'));

// Base de données -----------------------------------------------------------------------------

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    $pdo = null;
    $controle(
        sprintf('Connexion à %s@%s', Env::get('DB_NAME', '?'), Env::get('DB_HOST', '?')),
        false,
        $exception->getMessage(),
    );
}

if ($pdo !== null) {
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $estMariaDb = stripos($version, 'mariadb') !== false;
    $numero = preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $version, $m) === 1 ? $m[1] : '0';
    $versionOk = $estMariaDb ? version_compare($numero, '10.4', '>=') : version_compare($numero, '8.0', '>=');
    $controle(
        ($estMariaDb ? 'MariaDB ' : 'MySQL ') . $version,
        $versionOk,
        $versionOk ? '' : ($estMariaDb ? 'MariaDB 10.4 minimum requise' : 'MySQL 8.0 minimum requis'),
    );

    $controle('Colonnes générées indexées (un seul service ouvert par agent)', ...verifierColonneGeneree($pdo));

    $session = $pdo->query('SELECT @@session.time_zone AS fuseau, UNIX_TIMESTAMP(NOW()) AS maintenant')->fetch();
    $ecart = abs((int) $session['maintenant'] - time());
    $fuseauOk = in_array($session['fuseau'], ['+00:00', 'UTC'], true);
    $controle(
        'Fuseau de session SQL',
        $fuseauOk && $ecart <= 120,
        sprintf('time_zone = %s, écart NOW() / horloge PHP = %d s', $session['fuseau'], $ecart),
    );
}

// Dossiers d'écriture --------------------------------------------------------------------------

$storage = Env::get('STORAGE_PATH') ?? $racine . '/storage';
foreach (['fichiers', 'rapports', 'exports', 'imports', 'tmp', 'backups', 'logs', 'sessions', 'cache'] as $dossier) {
    $chemin = $storage . '/' . $dossier;
    $controle('Écriture dans storage/' . $dossier, ...verifierEcriture($chemin));
}

// Sauvegarde ------------------------------------------------------------------------------------

$mysqldump = Env::get('MYSQLDUMP_PATH', 'mysqldump') ?? 'mysqldump';
$cheminMysqldump = str_contains($mysqldump, '/') ? $mysqldump : trim((string) shell_exec('command -v ' . escapeshellarg($mysqldump) . ' 2>/dev/null'));
$controle(
    'mysqldump (sauvegarde quotidienne)',
    $cheminMysqldump !== '' && is_executable($cheminMysqldump),
    $cheminMysqldump !== '' && is_executable($cheminMysqldump) ? $cheminMysqldump : 'introuvable : renseigner MYSQLDUMP_PATH dans .env',
);

// Synthèse -------------------------------------------------------------------------------------

$echecs = count(array_filter($resultats, static fn (bool $ok): bool => !$ok));
Console::line();
Console::line(sprintf('%d contrôle(s), %d OK, %d KO.', count($resultats), count($resultats) - $echecs, $echecs));

exit($echecs === 0 ? 0 : 1);

/**
 * @return array{0: bool, 1: string}
 */
function verifierZipAes(string $dossierTemporaire): array
{
    if (!class_exists(ZipArchive::class)) {
        return [false, 'extension zip absente'];
    }
    if (!ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) {
        return [false, 'libzip compilée sans chiffrement : prévoir l\'alternative pour l\'export RGPD'];
    }

    $archive = (is_dir($dossierTemporaire) && is_writable($dossierTemporaire) ? $dossierTemporaire : sys_get_temp_dir())
        . '/verif-' . bin2hex(random_bytes(6)) . '.zip';

    try {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            return [false, 'création d\'archive impossible'];
        }
        $zip->addFromString('controle.txt', 'pegasus');
        $zip->setEncryptionName('controle.txt', ZipArchive::EM_AES_256, 'mot-de-passe-de-controle');
        $zip->close();

        $lecture = new ZipArchive();
        $lecture->open($archive);
        $lecture->setPassword('mot-de-passe-de-controle');
        $contenu = $lecture->getFromName('controle.txt');
        $lecture->close();

        return $contenu === 'pegasus'
            ? [true, 'archive chiffrée puis relue avec succès']
            : [false, 'archive créée mais illisible avec le mot de passe'];
    } finally {
        if (is_file($archive)) {
            unlink($archive);
        }
    }
}

/**
 * @return array{0: bool, 1: string}
 */
function verifierColonneGeneree(PDO $pdo): array
{
    try {
        $pdo->exec(
            "CREATE TEMPORARY TABLE verif_colonne_generee (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agent_id INT UNSIGNED NOT NULL,
                statut ENUM('ouvert','clos') NOT NULL,
                agent_ouvert INT UNSIGNED AS (IF(statut = 'ouvert', agent_id, NULL)) STORED,
                UNIQUE KEY uq_un_ouvert (agent_ouvert)
            ) ENGINE=InnoDB"
        );
    } catch (PDOException $exception) {
        return [false, 'création refusée : ' . $exception->getMessage()];
    }

    try {
        $pdo->exec("INSERT INTO verif_colonne_generee (agent_id, statut) VALUES (1, 'ouvert'), (1, 'clos'), (1, 'clos')");

        try {
            $pdo->exec("INSERT INTO verif_colonne_generee (agent_id, statut) VALUES (1, 'ouvert')");
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return [true, 'un second service ouvert est bien refusé'];
            }
            throw $exception;
        }

        return [false, 'l\'index unique n\'empêche pas un second service ouvert'];
    } catch (PDOException $exception) {
        return [false, $exception->getMessage()];
    } finally {
        $pdo->exec('DROP TEMPORARY TABLE IF EXISTS verif_colonne_generee');
    }
}

/**
 * @return array{0: bool, 1: string}
 */
function verifierEcriture(string $dossier): array
{
    if (!is_dir($dossier)) {
        return [false, 'dossier absent : ' . $dossier];
    }

    $fichier = $dossier . '/.verif-' . bin2hex(random_bytes(6));
    if (!is_writable($dossier) || file_put_contents($fichier, 'ok') === false) {
        $utilisateur = function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid())
            : 'courant';

        return [false, 'écriture refusée pour l\'utilisateur ' . $utilisateur];
    }
    unlink($fichier);

    return [true, ''];
}
