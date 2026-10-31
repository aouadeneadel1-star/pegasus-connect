<?php

declare(strict_types=1);

namespace App\Services\Sauvegarde;

use App\Core\Clock;
use RuntimeException;
use Throwable;

/**
 * Sauvegarde quotidienne de la base (cahier des charges, § 5.7 et § 7) : sortie de mysqldump
 * compressée en gzip puis chiffrée par blocs (XChaCha20-Poly1305 secretstream), sans jamais
 * écrire le SQL en clair sur le disque.
 *
 * Format : « PGSB1 » + en-tête secretstream, puis des blocs [longueur sur 4 octets][bloc chiffré].
 * Le dernier bloc porte l'étiquette FINAL : un fichier tronqué est détecté à la restauration.
 */
final class SauvegardeBase
{
    private const MAGIQUE = 'PGSB1';
    private const TAILLE_BLOC = 1048576;

    /**
     * @param array{host: string, port: int, name: string, user: string, password: string} $base
     */
    public function __construct(
        private readonly array $base,
        private readonly string $cle,
        private readonly string $dossier,
        private readonly string $mysqldump = 'mysqldump',
    ) {
        if (strlen($cle) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Clé de sauvegarde invalide.');
        }
    }

    /**
     * @return array{fichier: string, octets: int, supprimees: int}
     */
    public function executer(int $retentionJours): array
    {
        if (!is_dir($this->dossier) && !mkdir($this->dossier, 0700, true) && !is_dir($this->dossier)) {
            throw new RuntimeException('Dossier de sauvegarde inaccessible.');
        }

        // Le mot de passe passe par un fichier d'options : en argument, il serait visible dans la liste des processus.
        $options = (string) tempnam(sys_get_temp_dir(), 'pgsdump');
        chmod($options, 0600);
        file_put_contents($options, sprintf(
            "[client]\nhost=%s\nport=%d\nuser=%s\npassword=\"%s\"\n",
            $this->base['host'],
            $this->base['port'],
            $this->base['user'],
            addcslashes($this->base['password'], "\\\""),
        ));

        $destination = sprintf(
            '%s/%s-%s-%s.sql.gz.chiffre',
            $this->dossier,
            $this->base['name'],
            Clock::now()->setTimezone(Clock::local())->format('Ymd-His'),
            bin2hex(random_bytes(3)),
        );
        $commande = [
            $this->mysqldump,
            '--defaults-extra-file=' . $options,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--no-tablespaces',
            $this->base['name'],
        ];

        $erreurs = '';
        try {
            $processus = proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
            if (!is_resource($processus)) {
                throw new RuntimeException('mysqldump introuvable.');
            }
            try {
                $this->chiffrer($tubes[1], $destination);
            } finally {
                $erreurs = (string) stream_get_contents($tubes[2]);
                fclose($tubes[1]);
                fclose($tubes[2]);
                $code = proc_close($processus);
            }
            if ($code !== 0) {
                throw new RuntimeException(sprintf('mysqldump a échoué (code %d) : %s', $code, trim($erreurs)));
            }
        } catch (Throwable $exception) {
            // Une sauvegarde incomplète serait prise pour valide lors d'une restauration.
            if (is_file($destination)) {
                unlink($destination);
            }

            throw $exception;
        } finally {
            unlink($options);
        }

        return ['fichier' => basename($destination), 'octets' => (int) filesize($destination), 'supprimees' => $this->rotation($retentionJours)];
    }

    /**
     * @param resource $source
     */
    public function chiffrer($source, string $destination): void
    {
        $sortie = fopen($destination, 'xb');
        if ($sortie === false) {
            throw new RuntimeException('Impossible de créer ' . $destination);
        }
        chmod($destination, 0600);

        try {
            [$etat, $entete] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->cle);
            fwrite($sortie, self::MAGIQUE . $entete);
            $compression = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
            $tampon = '';

            while (!feof($source)) {
                $lu = fread($source, 65536);
                if ($lu === false) {
                    break;
                }
                $tampon .= deflate_add($compression, $lu, ZLIB_NO_FLUSH);
                while (strlen($tampon) >= self::TAILLE_BLOC) {
                    self::ecrireBloc($sortie, $etat, substr($tampon, 0, self::TAILLE_BLOC), SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                    $tampon = substr($tampon, self::TAILLE_BLOC);
                }
            }
            $tampon .= deflate_add($compression, '', ZLIB_FINISH);
            self::ecrireBloc($sortie, $etat, $tampon, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($sortie);
        }
    }

    /**
     * Restitue le SQL en clair dans le flux de destination.
     *
     * @param resource $destination
     */
    public function dechiffrer(string $source, $destination): void
    {
        $entree = fopen($source, 'rb');
        if ($entree === false) {
            throw new RuntimeException('Sauvegarde illisible : ' . $source);
        }

        try {
            if (fread($entree, strlen(self::MAGIQUE)) !== self::MAGIQUE) {
                throw new RuntimeException('Ce fichier n\'est pas une sauvegarde Pegasus.');
            }
            $entete = (string) fread($entree, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $etat = sodium_crypto_secretstream_xchacha20poly1305_init_pull($entete, $this->cle);
            $decompression = inflate_init(ZLIB_ENCODING_GZIP);
            $termine = false;

            while (!$termine) {
                $longueur = fread($entree, 4);
                if ($longueur === false || strlen($longueur) !== 4) {
                    throw new RuntimeException('Sauvegarde tronquée.');
                }
                $bloc = (string) fread($entree, (int) unpack('N', $longueur)[1]);
                $resultat = sodium_crypto_secretstream_xchacha20poly1305_pull($etat, $bloc);
                if ($resultat === false) {
                    throw new RuntimeException('Sauvegarde altérée ou clé incorrecte.');
                }
                [$clair, $etiquette] = $resultat;
                fwrite($destination, (string) inflate_add($decompression, $clair));
                $termine = $etiquette === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
        } finally {
            fclose($entree);
        }
    }

    private function rotation(int $retentionJours): int
    {
        $limite = Clock::now()->getTimestamp() - $retentionJours * 86400;
        $supprimees = 0;
        foreach (glob($this->dossier . '/*.sql.gz.chiffre') ?: [] as $fichier) {
            if (filemtime($fichier) < $limite && unlink($fichier)) {
                ++$supprimees;
            }
        }

        return $supprimees;
    }

    /**
     * @param resource $sortie
     */
    private static function ecrireBloc($sortie, string &$etat, string $donnees, int $etiquette): void
    {
        $chiffre = sodium_crypto_secretstream_xchacha20poly1305_push($etat, $donnees, '', $etiquette);
        fwrite($sortie, pack('N', strlen($chiffre)) . $chiffre);
    }
}
