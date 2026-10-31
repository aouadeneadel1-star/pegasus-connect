<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

/**
 * Règles de majoration, jours fériés et périodes clôturées.
 */
final class MajorationRepository extends Repository
{
    /**
     * Règle en vigueur pour chaque code à une date : la plus récente dont la date d'effet est passée.
     *
     * @return array<string, array<string, mixed>>
     */
    public function enVigueur(string $date): array
    {
        $regles = [];
        foreach ($this->lignes(
            'SELECT * FROM regles_majoration WHERE actif = 1 AND date_effet <= :date ORDER BY code, date_effet',
            ['date' => $date],
        ) as $regle) {
            $regles[(string) $regle['code']] = $regle;
        }

        return $regles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function regles(): array
    {
        return $this->lignes('SELECT * FROM regles_majoration ORDER BY code, date_effet DESC');
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function ajouterRegle(array $donnees): int
    {
        return $this->inserer('regles_majoration', $donnees);
    }

    public function desactiverRegle(int $id): void
    {
        $this->modifierLigne('regles_majoration', $id, ['actif' => 0]);
    }

    /**
     * @return array<string, true>
     */
    public function feriesEntre(string $du, string $au): array
    {
        $dates = [];
        foreach ($this->lignes('SELECT date FROM jours_feries WHERE date >= :du AND date <= :au', ['du' => $du, 'au' => $au]) as $ligne) {
            $dates[(string) $ligne['date']] = true;
        }

        return $dates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function feries(int $annee): array
    {
        return $this->lignes(
            'SELECT * FROM jours_feries WHERE date >= :du AND date <= :au ORDER BY date',
            ['du' => $annee . '-01-01', 'au' => $annee . '-12-31'],
        );
    }

    public function ajouterFerie(string $date, string $libelle): void
    {
        $this->executer(
            'INSERT INTO jours_feries (date, libelle) VALUES (:date, :libelle) ON DUPLICATE KEY UPDATE libelle = :libelle_maj',
            ['date' => $date, 'libelle' => $libelle, 'libelle_maj' => $libelle],
        );
    }

    public function supprimerFerie(string $date): void
    {
        $this->executer('DELETE FROM jours_feries WHERE date = :date', ['date' => $date]);
    }

    public function estCloturee(int $annee, int $mois): bool
    {
        return (bool) $this->valeur('SELECT COUNT(*) FROM periodes_cloturees WHERE annee = :annee AND mois = :mois', ['annee' => $annee, 'mois' => $mois]);
    }

    public function cloturer(int $annee, int $mois, int $utilisateurId): void
    {
        $this->executer(
            'INSERT IGNORE INTO periodes_cloturees (annee, mois, cloturee_par, cloturee_le) VALUES (:annee, :mois, :par, :le)',
            ['annee' => $annee, 'mois' => $mois, 'par' => $utilisateurId, 'le' => Clock::sql(Clock::now())],
        );
    }

    public function rouvrir(int $annee, int $mois): void
    {
        $this->executer('DELETE FROM periodes_cloturees WHERE annee = :annee AND mois = :mois', ['annee' => $annee, 'mois' => $mois]);
    }
}
