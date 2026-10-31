<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

/**
 * Imports de bulletins de paie (PDF produits par le logiciel de paie) et fiches publiées.
 */
final class PaieRepository extends Repository
{
    public function creerImport(int $annee, int $mois, int $utilisateurId): int
    {
        return $this->inserer('imports_paie', [
            'annee' => $annee,
            'mois' => $mois,
            'statut' => 'analyse',
            'cree_par' => $utilisateurId,
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function import(int $id): ?array
    {
        return $this->ligne(
            'SELECT i.*, u.nom_complet AS cree_par_nom FROM imports_paie i JOIN utilisateurs u ON u.id = i.cree_par WHERE i.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function imports(): array
    {
        return $this->lignes(
            'SELECT i.*, u.nom_complet AS cree_par_nom FROM imports_paie i JOIN utilisateurs u ON u.id = i.cree_par
             ORDER BY i.annee DESC, i.mois DESC, i.id DESC LIMIT 60'
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function ajouterLigne(int $importId, array $donnees): int
    {
        return $this->inserer('imports_paie_lignes', [...$donnees, 'import_id' => $importId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lignesImport(int $importId): array
    {
        return $this->lignes(
            "SELECT l.*, a.nom, a.prenom, a.matricule FROM imports_paie_lignes l LEFT JOIN agents a ON a.id = l.agent_id
             WHERE l.import_id = :import
             ORDER BY FIELD(l.statut, 'ambigu', 'introuvable', 'doublon', 'invalide', 'deja_publie', 'associe'), l.nom_fichier",
            ['import' => $importId],
        );
    }

    public function associerLigne(int $ligneId, int $importId, int $agentId): void
    {
        $this->executer(
            "UPDATE imports_paie_lignes SET agent_id = :agent, methode = 'manuel', statut = 'associe', message = NULL
             WHERE id = :id AND import_id = :import",
            ['agent' => $agentId, 'id' => $ligneId, 'import' => $importId],
        );
    }

    public function actualiserCompteurs(int $importId, string $statut): void
    {
        $this->executer(
            "UPDATE imports_paie SET statut = :statut,
                nb_fichiers = (SELECT COUNT(*) FROM imports_paie_lignes WHERE import_id = :i1),
                nb_associes = (SELECT COUNT(*) FROM imports_paie_lignes WHERE import_id = :i2 AND statut = 'associe'),
                nb_erreurs = (SELECT COUNT(*) FROM imports_paie_lignes WHERE import_id = :i3 AND statut <> 'associe')
             WHERE id = :id",
            ['statut' => $statut, 'i1' => $importId, 'i2' => $importId, 'i3' => $importId, 'id' => $importId],
        );
    }

    public function marquerPublie(int $importId, int $utilisateurId): void
    {
        $this->modifierLigne('imports_paie', $importId, [
            'statut' => 'publie',
            'publie_par' => $utilisateurId,
            'publie_le' => Clock::sql(Clock::now()),
        ]);
    }

    public function annulerImport(int $importId): void
    {
        $this->modifierLigne('imports_paie', $importId, ['statut' => 'annule']);
    }

    public function dejaPubliee(int $agentId, int $annee, int $mois): bool
    {
        return (bool) $this->valeur(
            "SELECT COUNT(*) FROM fiches_paie WHERE agent_id = :agent AND annee = :annee AND mois = :mois AND statut = 'publiee'",
            ['agent' => $agentId, 'annee' => $annee, 'mois' => $mois],
        );
    }

    /**
     * Publication d'une fiche ; une fiche déjà publiée pour la même période est remplacée.
     */
    public function publierFiche(int $agentId, int $annee, int $mois, int $documentId, ?int $importId): int
    {
        $version = (int) $this->valeur(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM fiches_paie WHERE agent_id = :agent AND annee = :annee AND mois = :mois',
            ['agent' => $agentId, 'annee' => $annee, 'mois' => $mois],
        );
        $this->executer(
            "UPDATE fiches_paie SET statut = 'remplacee' WHERE agent_id = :agent AND annee = :annee AND mois = :mois AND statut = 'publiee'",
            ['agent' => $agentId, 'annee' => $annee, 'mois' => $mois],
        );

        return $this->inserer('fiches_paie', [
            'agent_id' => $agentId,
            'annee' => $annee,
            'mois' => $mois,
            'version' => $version,
            'document_id' => $documentId,
            'import_id' => $importId,
            'statut' => 'publiee',
            'publiee_le' => Clock::sql(Clock::now()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fiche(int $id): ?array
    {
        return $this->ligne(
            'SELECT f.*, a.nom, a.prenom, a.matricule, a.utilisateur_id FROM fiches_paie f JOIN agents a ON a.id = f.agent_id WHERE f.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fichesAgent(int $agentId): array
    {
        return $this->lignes(
            "SELECT f.*, d.taille_octets FROM fiches_paie f JOIN documents d ON d.id = f.document_id
             WHERE f.agent_id = :agent AND f.statut = 'publiee' ORDER BY f.annee DESC, f.mois DESC",
            ['agent' => $agentId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fichesImport(int $importId): array
    {
        return $this->lignes(
            'SELECT f.*, a.nom, a.prenom, a.matricule FROM fiches_paie f JOIN agents a ON a.id = f.agent_id
             WHERE f.import_id = :import ORDER BY a.nom, a.prenom',
            ['import' => $importId],
        );
    }

    public function marquerConsultee(int $id): void
    {
        $this->executer(
            'UPDATE fiches_paie SET premiere_consultation = :le WHERE id = :id AND premiere_consultation IS NULL',
            ['le' => Clock::sql(Clock::now()), 'id' => $id],
        );
    }

    public function depublier(int $id): void
    {
        $this->modifierLigne('fiches_paie', $id, ['statut' => 'depubliee']);
    }
}
