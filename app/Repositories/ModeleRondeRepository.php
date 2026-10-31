<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class ModeleRondeRepository extends Repository
{
    public const CHAMPS = [
        'nom', 'heure_debut', 'heure_fin', 'frequence_minutes', 'jours_semaine', 'duree_max_minutes',
        'tolerance_demarrage_min', 'ordre_impose', 'temps_min_entre_points_s', 'actif',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function pourSite(int $siteId): array
    {
        return $this->lignes(
            'SELECT m.*, (SELECT COUNT(*) FROM rondes_modeles_points mp WHERE mp.modele_id = m.id) AS nb_points
             FROM rondes_modeles m WHERE m.site_id = :site ORDER BY m.actif DESC, m.heure_debut',
            ['site' => $siteId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne('SELECT * FROM rondes_modeles WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actifs(): array
    {
        return $this->lignes(
            'SELECT m.* FROM rondes_modeles m JOIN sites s ON s.id = m.site_id WHERE m.actif = 1 AND s.actif = 1'
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(int $siteId, array $donnees): int
    {
        return $this->inserer('rondes_modeles', [...$donnees, 'site_id' => $siteId, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('rondes_modeles', $id, $donnees);
    }

    /**
     * @return list<array{point_id: int, ordre: int, obligatoire: bool}>
     */
    public function points(int $modeleId): array
    {
        return array_map(
            static fn (array $ligne): array => [
                'point_id' => (int) $ligne['point_id'],
                'ordre' => (int) $ligne['ordre'],
                'obligatoire' => (bool) $ligne['obligatoire'],
            ],
            $this->lignes(
                'SELECT mp.point_id, mp.ordre, mp.obligatoire FROM rondes_modeles_points mp
                 JOIN points_controle p ON p.id = mp.point_id
                 WHERE mp.modele_id = :modele AND p.actif = 1 ORDER BY mp.ordre',
                ['modele' => $modeleId],
            ),
        );
    }

    /**
     * @param list<array{point_id: int, obligatoire: bool}> $points dans l'ordre du parcours
     */
    public function definirPoints(int $modeleId, array $points): void
    {
        $this->executer('DELETE FROM rondes_modeles_points WHERE modele_id = :modele', ['modele' => $modeleId]);
        foreach (array_values($points) as $ordre => $point) {
            $this->executer(
                'INSERT INTO rondes_modeles_points (modele_id, point_id, ordre, obligatoire) VALUES (:modele, :point, :ordre, :obligatoire)',
                ['modele' => $modeleId, 'point' => $point['point_id'], 'ordre' => $ordre + 1, 'obligatoire' => $point['obligatoire'] ? 1 : 0],
            );
        }
    }
}
