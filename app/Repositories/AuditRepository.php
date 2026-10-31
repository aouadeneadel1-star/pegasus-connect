<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * Lecture du journal d'audit. L'écriture passe exclusivement par AuditLogger.
 */
final class AuditRepository extends Repository
{
    public const PAR_PAGE = 100;

    /**
     * @param array{action?: string, utilisateur?: int, objet_type?: string, objet_id?: int} $filtres
     * @return array{lignes: list<array<string, mixed>>, total: int}
     */
    public function lister(DateTimeImmutable $du, DateTimeImmutable $au, array $filtres, int $page): array
    {
        [$where, $params] = $this->conditions($du, $au, $filtres);
        $total = (int) $this->valeur('SELECT COUNT(*) FROM audit au ' . $where, $params);
        $offset = max(0, ($page - 1) * self::PAR_PAGE);

        return [
            'total' => $total,
            'lignes' => array_map(self::adresse(...), $this->lignes(
                'SELECT au.id, au.utilisateur_id, au.role_code, au.action, au.objet_type, au.objet_id, au.details,
                        LOWER(HEX(au.ip)) AS ip, au.created_at, u.nom_complet
                 FROM audit au LEFT JOIN utilisateurs u ON u.id = au.utilisateur_id ' . $where . '
                 ORDER BY au.id DESC LIMIT ' . self::PAR_PAGE . ' OFFSET ' . $offset,
                $params,
            )),
        ];
    }

    /**
     * @param array{action?: string, utilisateur?: int, objet_type?: string, objet_id?: int} $filtres
     * @return iterable<array<string, mixed>>
     */
    public function exporter(DateTimeImmutable $du, DateTimeImmutable $au, array $filtres): iterable
    {
        [$where, $params] = $this->conditions($du, $au, $filtres);
        $requete = $this->executer(
            'SELECT au.id, au.created_at, au.action, au.objet_type, au.objet_id, au.role_code, u.nom_complet, au.details, LOWER(HEX(au.ip)) AS ip, au.hash
             FROM audit au LEFT JOIN utilisateurs u ON u.id = au.utilisateur_id ' . $where . ' ORDER BY au.id',
            $params,
        );
        while (($ligne = $requete->fetch()) !== false) {
            yield self::adresse($ligne);
        }
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_map('strval', $this->executer('SELECT DISTINCT action FROM audit ORDER BY action')->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return list<array<string, mixed>> historique d'un objet (fiche agent, site…)
     */
    public function pourObjet(string $type, int $id, int $limite = 50): array
    {
        return $this->lignes(
            'SELECT au.action, au.created_at, au.details, u.nom_complet FROM audit au LEFT JOIN utilisateurs u ON u.id = au.utilisateur_id
             WHERE au.objet_type = :type AND au.objet_id = :id ORDER BY au.id DESC LIMIT ' . max(1, $limite),
            ['type' => $type, 'id' => $id],
        );
    }

    /**
     * @param array<string, mixed> $ligne
     * @return array<string, mixed>
     */
    private static function adresse(array $ligne): array
    {
        $binaire = is_string($ligne['ip'] ?? null) && $ligne['ip'] !== '' ? hex2bin($ligne['ip']) : false;
        $ligne['ip'] = $binaire === false ? null : (inet_ntop($binaire) ?: null);

        return $ligne;
    }

    /**
     * @param array{action?: string, utilisateur?: int, objet_type?: string, objet_id?: int} $filtres
     * @return array{0: string, 1: array<string, scalar>}
     */
    private function conditions(DateTimeImmutable $du, DateTimeImmutable $au, array $filtres): array
    {
        $conditions = ['au.created_at >= :du', 'au.created_at < :au'];
        $params = ['du' => Clock::sql($du), 'au' => Clock::sql($au)];
        if (($filtres['action'] ?? '') !== '') {
            $conditions[] = 'au.action LIKE :action';
            $params['action'] = str_replace(['%', '_'], ['\%', '\_'], (string) $filtres['action']) . '%';
        }
        if (($filtres['utilisateur'] ?? 0) > 0) {
            $conditions[] = 'au.utilisateur_id = :utilisateur';
            $params['utilisateur'] = (int) $filtres['utilisateur'];
        }
        if (($filtres['objet_type'] ?? '') !== '') {
            $conditions[] = 'au.objet_type = :objet_type';
            $params['objet_type'] = (string) $filtres['objet_type'];
        }
        if (($filtres['objet_id'] ?? 0) > 0) {
            $conditions[] = 'au.objet_id = :objet_id';
            $params['objet_id'] = (int) $filtres['objet_id'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
