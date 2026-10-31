<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class PointControleRepository extends Repository
{
    public const CHAMPS = ['nom', 'description', 'zone', 'ordre', 'latitude', 'longitude', 'rayon_metres'];

    /**
     * @return list<array<string, mixed>>
     */
    public function pourSite(int $siteId, bool $actifsSeulement = true): array
    {
        return $this->lignes(
            'SELECT p.*,
                    (SELECT MAX(pr.horodatage) FROM passages_ronde pr WHERE pr.point_id = p.id AND pr.statut <> \'rejete\') AS dernier_passage
             FROM points_controle p
             WHERE p.site_id = :site' . ($actifsSeulement ? ' AND p.actif = 1' : '') . '
             ORDER BY p.actif DESC, p.ordre, p.nom',
            ['site' => $siteId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT p.*, s.nom AS site_nom, s.code AS site_code FROM points_controle p JOIN sites s ON s.id = p.site_id WHERE p.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function plusieurs(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $params['p' . $index] = $id;
        }

        return $this->lignes(
            'SELECT p.*, s.nom AS site_nom FROM points_controle p JOIN sites s ON s.id = p.site_id
             WHERE p.id IN (:' . implode(', :', array_keys($params)) . ') ORDER BY p.ordre, p.nom',
            $params,
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(int $siteId, array $donnees): int
    {
        $id = $this->inserer('points_controle', [
            ...$donnees,
            'site_id' => $siteId,
            'identifiant' => 'PC-TMP-' . bin2hex(random_bytes(2)),
            'created_at' => Clock::sql(Clock::now()),
        ]);
        // L'identifiant lisible découle de la clé : unique et stable, il est imprimé sous le QR.
        $this->modifierLigne('points_controle', $id, ['identifiant' => sprintf('PC-%04d', $id)]);

        return $id;
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('points_controle', $id, $donnees);
    }

    public function incrementerVersionQr(int $id): int
    {
        $this->executer('UPDATE points_controle SET qr_version = qr_version + 1 WHERE id = :id', ['id' => $id]);

        return (int) $this->valeur('SELECT qr_version FROM points_controle WHERE id = :id', ['id' => $id]);
    }

    public function definirActif(int $id, bool $actif): void
    {
        $this->modifierLigne('points_controle', $id, [
            'actif' => $actif ? 1 : 0,
            'desactive_le' => $actif ? null : Clock::sql(Clock::now()),
        ]);
    }

    public function definirPosition(int $id, int $siteId, ?float $x, ?float $y): void
    {
        $this->executer(
            'UPDATE points_controle SET plan_x = :x, plan_y = :y WHERE id = :id AND site_id = :site',
            ['id' => $id, 'site' => $siteId, 'x' => $x === null ? null : (string) round($x, 2), 'y' => $y === null ? null : (string) round($y, 2)],
        );
    }

    /**
     * Historique des passages à un emplacement.
     *
     * @return list<array<string, mixed>>
     */
    public function passages(int $pointId, string $du, string $au, int $limite = 500): array
    {
        return $this->lignes(
            'SELECT pr.horodatage, pr.statut, pr.indicateurs, pr.ronde_id, a.nom, a.prenom, a.matricule
             FROM passages_ronde pr JOIN agents a ON a.id = pr.agent_id
             WHERE pr.point_id = :point AND pr.horodatage >= :du AND pr.horodatage < :au
             ORDER BY pr.horodatage DESC
             LIMIT ' . max(1, min($limite, 2000)),
            ['point' => $pointId, 'du' => $du, 'au' => $au],
        );
    }
}
