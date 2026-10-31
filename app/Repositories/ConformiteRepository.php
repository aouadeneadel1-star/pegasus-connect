<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;

/**
 * Carte professionnelle CNAPS, SST et qualifications d'un agent.
 */
final class ConformiteRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function carteProCourante(int $agentId): ?array
    {
        return $this->ligne('SELECT * FROM cartes_pro WHERE agent_id = :agent AND courante = 1 ORDER BY id DESC LIMIT 1', ['agent' => $agentId]);
    }

    /**
     * Une nouvelle carte remplace la précédente, qui reste en historique.
     *
     * @param array<string, scalar|null> $donnees
     */
    public function enregistrerCartePro(int $agentId, array $donnees): int
    {
        $this->executer('UPDATE cartes_pro SET courante = 0 WHERE agent_id = :agent', ['agent' => $agentId]);

        return $this->inserer('cartes_pro', [...$donnees, 'agent_id' => $agentId, 'courante' => 1, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifierCartePro(int $id, array $donnees): void
    {
        $this->modifierLigne('cartes_pro', $id, $donnees);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function qualifications(int $agentId): array
    {
        return $this->lignes(
            'SELECT q.*, t.code, t.libelle AS type_libelle
             FROM qualifications q JOIN types_qualification t ON t.id = q.type_qualification_id
             WHERE q.agent_id = :agent
             ORDER BY t.categorie, t.libelle',
            ['agent' => $agentId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function qualification(int $id): ?array
    {
        return $this->ligne('SELECT * FROM qualifications WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function ajouterQualification(int $agentId, array $donnees): int
    {
        return $this->inserer('qualifications', [...$donnees, 'agent_id' => $agentId, 'created_at' => Clock::sql(Clock::now())]);
    }

    public function supprimerQualification(int $id): void
    {
        $this->executer('DELETE FROM qualifications WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function typesQualification(): array
    {
        return $this->lignes('SELECT * FROM types_qualification WHERE actif = 1 ORDER BY categorie, libelle');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function typeQualification(int $id): ?array
    {
        return $this->ligne('SELECT * FROM types_qualification WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return list<string> codes des qualifications valides de l'agent à une date
     */
    public function qualificationsValides(int $agentId, string $date): array
    {
        return array_map('strval', $this->executer(
            'SELECT t.code FROM qualifications q JOIN types_qualification t ON t.id = q.type_qualification_id
             WHERE q.agent_id = :agent AND (q.date_echeance IS NULL OR q.date_echeance >= :date)',
            ['agent' => $agentId, 'date' => $date],
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Toutes les échéances (carte pro, qualifications, documents) jusqu'à une date limite.
     *
     * @return list<array<string, mixed>>
     */
    public function echeances(Perimetre $perimetre, string $limite): array
    {
        [$condCarte, $paramsCarte] = $perimetre->sqlAgents('c.agent_id', 'pc');
        [$condQualif, $paramsQualif] = $perimetre->sqlAgents('q.agent_id', 'pq');

        return $this->lignes(
            "SELECT 'carte_pro' AS objet_type, c.id AS objet_id, c.agent_id, 'Carte professionnelle' AS libelle,
                    c.date_expiration AS echeance, a.nom, a.prenom, a.matricule
             FROM cartes_pro c JOIN agents a ON a.id = c.agent_id JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE c.courante = 1 AND u.statut = 'actif' AND c.date_expiration <= :limite_carte AND $condCarte
             UNION ALL
             SELECT 'qualification', q.id, q.agent_id, t.libelle, q.date_echeance, a.nom, a.prenom, a.matricule
             FROM qualifications q
             JOIN types_qualification t ON t.id = q.type_qualification_id
             JOIN agents a ON a.id = q.agent_id JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE q.date_echeance IS NOT NULL AND u.statut = 'actif' AND q.date_echeance <= :limite_qualif AND $condQualif
             ORDER BY echeance, nom",
            [...$paramsCarte, ...$paramsQualif, 'limite_carte' => $limite, 'limite_qualif' => $limite],
        );
    }

    public function alerteDejaEnvoyee(string $objetType, int $objetId, string $echeance, int $seuil): bool
    {
        return (bool) $this->valeur(
            'SELECT COUNT(*) FROM alertes_expiration_envois
             WHERE objet_type = :type AND objet_id = :objet AND date_echeance = :echeance AND seuil_jours = :seuil',
            ['type' => $objetType, 'objet' => $objetId, 'echeance' => $echeance, 'seuil' => $seuil],
        );
    }

    public function enregistrerAlerte(string $objetType, int $objetId, string $echeance, int $seuil): void
    {
        $this->executer(
            'INSERT IGNORE INTO alertes_expiration_envois (objet_type, objet_id, date_echeance, seuil_jours, envoye_le)
             VALUES (:type, :objet, :echeance, :seuil, :maintenant)',
            ['type' => $objetType, 'objet' => $objetId, 'echeance' => $echeance, 'seuil' => $seuil, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function carteProValideLe(int $agentId, string $date): ?bool
    {
        $expiration = $this->valeur(
            'SELECT date_expiration FROM cartes_pro WHERE agent_id = :agent AND courante = 1 ORDER BY id DESC LIMIT 1',
            ['agent' => $agentId],
        );

        return $expiration === null ? null : (string) $expiration >= $date;
    }
}
