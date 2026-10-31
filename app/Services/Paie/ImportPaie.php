<?php

declare(strict_types=1);

namespace App\Services\Paie;

use App\Repositories\AgentRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\PaieRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications;
use App\Services\Stockage\FichierRefuseException;
use App\Services\Stockage\Stockage;
use App\Services\ValidationException;
use PDO;
use Throwable;

/**
 * Import des bulletins (cahier des charges, § 3.8) : dépôt des PDF, rapprochement avec les agents
 * par matricule puis par nom, contrôle par le gestionnaire, publication.
 * Les bulletins restent invisibles des agents tant que l'import n'est pas publié.
 */
final class ImportPaie
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PaieRepository $paie,
        private readonly AgentRepository $agents,
        private readonly DocumentRepository $documents,
        private readonly Stockage $stockage,
        private readonly AuditLogger $audit,
        private readonly Notifications $notifications,
    ) {
    }

    /**
     * @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $fichiers
     */
    public function analyser(int $annee, int $mois, array $fichiers, int $utilisateurId): int
    {
        if ($fichiers === []) {
            throw ValidationException::champ('fichiers', 'Déposez au moins un bulletin au format PDF.');
        }
        if (count($fichiers) > 300) {
            throw ValidationException::champ('fichiers', 'Un import est limité à 300 fichiers.');
        }

        $importId = $this->paie->creerImport($annee, $mois, $utilisateurId);
        $index = $this->indexAgents();
        $vus = [];

        foreach ($fichiers as $fichier) {
            $nom = mb_substr(basename($fichier['name']), 0, 255);
            try {
                $documentId = $this->stockage->deposer(
                    $fichier, Stockage::PDF, 5 * 1048576, 'paie', null,
                    sprintf('Bulletin de paie %s %d', nom_mois($mois), $annee), $utilisateurId, ['visible_agent' => false],
                );
            } catch (FichierRefuseException $exception) {
                $this->paie->ajouterLigne($importId, ['nom_fichier' => $nom, 'statut' => 'invalide', 'message' => mb_substr($exception->getMessage(), 0, 255)]);
                continue;
            }

            [$agentId, $methode, $statut, $message] = $this->rapprocher($nom, $index);
            if ($agentId !== null && isset($vus[$agentId])) {
                [$statut, $message] = ['doublon', 'Un autre fichier de cet import concerne le même agent.'];
            } elseif ($agentId !== null && $this->paie->dejaPubliee($agentId, $annee, $mois)) {
                [$statut, $message] = ['deja_publie', 'Un bulletin est déjà publié pour ce mois : confirmez pour le remplacer.'];
            }
            if ($agentId !== null) {
                $vus[$agentId] = true;
            }

            $this->paie->ajouterLigne($importId, [
                'nom_fichier' => $nom,
                'document_id' => $documentId,
                'agent_id' => $agentId,
                'methode' => $methode,
                'statut' => $statut,
                'message' => $message,
            ]);
        }

        $this->paie->actualiserCompteurs($importId, 'a_valider');
        $this->audit->journaliser('paie.import.analyser', 'import_paie', $importId, ['fichiers' => count($fichiers), 'periode' => sprintf('%04d-%02d', $annee, $mois)]);

        return $importId;
    }

    public function associer(int $importId, int $ligneId, int $agentId): void
    {
        $import = $this->importModifiable($importId);
        if ($this->agents->trouver($agentId) === null) {
            throw ValidationException::champ('agent_id', 'Agent introuvable.');
        }

        $this->paie->associerLigne($ligneId, $importId, $agentId);
        $this->paie->actualiserCompteurs($importId, (string) $import['statut']);
    }

    /**
     * @return int nombre de bulletins publiés
     */
    public function publier(int $importId, int $utilisateurId): int
    {
        $import = $this->importModifiable($importId);
        $annee = (int) $import['annee'];
        $mois = (int) $import['mois'];
        $publies = [];

        $this->pdo->beginTransaction();
        try {
            foreach ($this->paie->lignesImport($importId) as $ligne) {
                if ($ligne['statut'] !== 'associe' || $ligne['agent_id'] === null || $ligne['document_id'] === null) {
                    continue;
                }
                $agentId = (int) $ligne['agent_id'];
                $this->pdo->prepare('UPDATE documents SET agent_id = :agent, visible_agent = 1 WHERE id = :id')
                    ->execute(['agent' => $agentId, 'id' => (int) $ligne['document_id']]);
                $this->paie->publierFiche($agentId, $annee, $mois, (int) $ligne['document_id'], $importId);
                $publies[] = $agentId;
            }
            $this->paie->marquerPublie($importId, $utilisateurId);
            $this->audit->journaliser('paie.import.publier', 'import_paie', $importId, ['bulletins' => count($publies)]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        // Le message reste générique : aucun montant, aucune donnée de paie dans une notification.
        $this->notifications->envoyer(
            $this->agents->utilisateursDe($publies),
            'paie_publiee',
            sprintf('Votre bulletin de %s %d est disponible', nom_mois($mois), $annee),
            null,
            '/paie',
            'notification',
        );

        return count($publies);
    }

    public function annuler(int $importId): void
    {
        $this->importModifiable($importId);
        foreach ($this->paie->lignesImport($importId) as $ligne) {
            if ($ligne['document_id'] !== null) {
                $this->documents->supprimer((int) $ligne['document_id']);
            }
        }
        $this->paie->annulerImport($importId);
        $this->audit->journaliser('paie.import.annuler', 'import_paie', $importId);
    }

    public function depublier(int $ficheId, string $motif): void
    {
        $fiche = $this->paie->fiche($ficheId) ?? throw ValidationException::champ('fiche', 'Bulletin introuvable.');
        if (trim($motif) === '') {
            throw ValidationException::champ('motif', 'Indiquez le motif de la dépublication.');
        }

        $this->paie->depublier($ficheId);
        $this->pdo->prepare('UPDATE documents SET visible_agent = 0 WHERE id = :id')->execute(['id' => (int) $fiche['document_id']]);
        $this->audit->journaliser('paie.fiche.depublier', 'fiche_paie', $ficheId, ['motif' => mb_substr($motif, 0, 200)]);
    }

    /**
     * Matricule dans le nom du fichier (PGS-0042_2026-10.pdf), sinon nom et prénom (DUPONT_Jean.pdf).
     *
     * @param array{matricules: array<string, int>, noms: array<string, list<int>>} $index
     * @return array{0: ?int, 1: ?string, 2: string, 3: ?string}
     */
    private function rapprocher(string $fichier, array $index): array
    {
        $base = mb_strtoupper(pathinfo($fichier, PATHINFO_FILENAME));
        if (preg_match_all('/[A-Z]{2,5}-?\d{3,6}/', $base, $correspondances) > 0) {
            foreach ($correspondances[0] as $candidat) {
                $cle = self::cleMatricule($candidat);
                if (isset($index['matricules'][$cle])) {
                    return [$index['matricules'][$cle], 'matricule', 'associe', null];
                }
            }
        }

        $mots = array_values(array_filter(preg_split('/[^A-Z]+/', self::sansAccents($base)) ?: [], static fn (string $mot): bool => strlen($mot) >= 2));
        $trouves = [];
        for ($i = 0; $i < count($mots) - 1; ++$i) {
            foreach ([$mots[$i] . ' ' . $mots[$i + 1], $mots[$i + 1] . ' ' . $mots[$i]] as $cle) {
                foreach ($index['noms'][$cle] ?? [] as $agentId) {
                    $trouves[$agentId] = true;
                }
            }
        }

        return match (count($trouves)) {
            0 => [null, null, 'introuvable', 'Aucun agent ne correspond au nom du fichier.'],
            1 => [(int) array_key_first($trouves), 'nom', 'associe', 'Rapproché par le nom : vérifiez.'],
            default => [null, null, 'ambigu', 'Plusieurs agents correspondent : choisissez.'],
        };
    }

    /**
     * @return array{matricules: array<string, int>, noms: array<string, list<int>>}
     */
    private function indexAgents(): array
    {
        $index = ['matricules' => [], 'noms' => []];
        foreach ($this->pdo->query('SELECT id, matricule, nom, prenom FROM agents')->fetchAll() as $agent) {
            $index['matricules'][self::cleMatricule((string) $agent['matricule'])] = (int) $agent['id'];
            $cle = self::sansAccents(mb_strtoupper($agent['nom'] . ' ' . $agent['prenom']));
            $cle = (string) preg_replace('/[^A-Z ]+/', '', $cle);
            $index['noms'][$cle][] = (int) $agent['id'];
        }

        return $index;
    }

    private static function cleMatricule(string $matricule): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($matricule));
    }

    private static function sansAccents(string $texte): string
    {
        $translitere = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);

        return strtoupper($translitere === false ? $texte : $translitere);
    }

    /**
     * @return array<string, mixed>
     */
    private function importModifiable(int $importId): array
    {
        $import = $this->paie->import($importId) ?? throw ValidationException::champ('import', 'Import introuvable.');
        if (!in_array($import['statut'], ['analyse', 'a_valider'], true)) {
            throw ValidationException::champ('import', 'Cet import est déjà publié ou annulé.');
        }

        return $import;
    }
}
