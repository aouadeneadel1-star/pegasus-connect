<?php

declare(strict_types=1);

namespace App\Services\Audit;

use PDO;

/**
 * Contrôle de la chaîne d'empreintes du journal d'audit. Les lignes supprimées par la purge
 * de conservation laissent des trous : ils sont acceptés s'ils correspondent exactement
 * à une plage consignée par un événement « audit.purge ».
 */
final class VerificationAudit
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{lignes: int, rupture: ?int} identifiant de la première ligne invalide, ou null
     */
    public function verifier(): array
    {
        $purges = $this->plagesPurgees();
        $precedent = AuditLogger::GENESE;
        $dernierId = 0;
        $lignes = 0;
        $statement = $this->pdo->prepare(
            'SELECT id, utilisateur_id, role_code, action, objet_type, objet_id, details, LOWER(HEX(ip)) AS ip,
                    DATE_FORMAT(created_at, \'%Y-%m-%d %H:%i:%s.%f\') AS created_at, hash_precedent, hash
             FROM audit WHERE id > :dernier ORDER BY id LIMIT 2000'
        );

        do {
            $statement->execute(['dernier' => $dernierId]);
            $lot = $statement->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lot as $ligne) {
                $id = (int) $ligne['id'];
                $ligne['created_at'] = substr((string) $ligne['created_at'], 0, 23);
                $ligne['ip'] = $ligne['ip'] === '' ? null : $ligne['ip'];

                $chaine = $ligne['hash_precedent'] === $precedent
                    || ($id > $dernierId + 1 && self::trouCouvert($dernierId + 1, $id - 1, $purges));
                if (!$chaine || !hash_equals(AuditLogger::empreinte((string) $ligne['hash_precedent'], $ligne), (string) $ligne['hash'])) {
                    return ['lignes' => $lignes, 'rupture' => $id];
                }

                $precedent = (string) $ligne['hash'];
                $dernierId = $id;
                $lignes++;
            }
        } while (count($lot) === 2000);

        return ['lignes' => $lignes, 'rupture' => null];
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function plagesPurgees(): array
    {
        $plages = [];
        foreach ($this->pdo->query("SELECT details FROM audit WHERE action = 'audit.purge'")->fetchAll(PDO::FETCH_COLUMN) as $details) {
            foreach (json_decode((string) $details, true)['plages'] ?? [] as $plage) {
                $plages[] = [(int) $plage[0], (int) $plage[1]];
            }
        }

        return $plages;
    }

    /**
     * @param list<array{0: int, 1: int}> $plages
     */
    private static function trouCouvert(int $debut, int $fin, array $plages): bool
    {
        foreach ($plages as [$de, $a]) {
            if ($de <= $debut && $a >= $fin) {
                return true;
            }
        }

        // Plusieurs purges successives peuvent couvrir un même trou en plusieurs morceaux.
        $courant = $debut;
        usort($plages, static fn (array $x, array $y): int => $x[0] <=> $y[0]);
        foreach ($plages as [$de, $a]) {
            if ($de <= $courant && $a >= $courant) {
                $courant = $a + 1;
            }
        }

        return $courant > $fin;
    }
}
