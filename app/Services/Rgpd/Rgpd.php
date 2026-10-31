<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

use App\Core\Clock;
use App\Repositories\AgentRepository;
use App\Repositories\DocumentRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Stockage\Stockage;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Droit d'accès (export) et application des durées de conservation (cahier des charges, § 6.4 et § 6.5).
 */
final class Rgpd
{
    /** Actions d'audit conservées trois ans au lieu d'un. */
    private const AUDIT_SENSIBLE = ['agent.nir.reveler', 'correction.%', 'pointage.%', 'rgpd.%'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly AgentRepository $agents,
        private readonly DocumentRepository $documents,
        private readonly Stockage $stockage,
        private readonly AuditLogger $audit,
        private readonly string $dossierTemporaire,
    ) {
    }

    /**
     * Archive ZIP chiffrée (AES-256) des données de l'agent : JSON et documents.
     * Le NIR n'y figure pas : il est communiqué sur demande écrite au service RH.
     *
     * @return array{chemin: string, mot_de_passe: string, nom: string}
     */
    public function exporter(int $agentId): array
    {
        $agent = $this->agents->trouver($agentId) ?? throw new RuntimeException('Agent introuvable.');
        unset($agent['nir_chiffre'], $agent['nir_index'], $agent['nir_fin']);

        $requete = fn (string $sql): array => $this->lignes($sql, $agentId);
        $donnees = [
            'genere_le' => Clock::now()->format(DATE_ATOM),
            'agent' => $agent,
            'numero_securite_sociale' => 'Enregistré de façon chiffrée ; communiqué sur demande écrite au service RH.',
            'affectations' => $requete('SELECT site_id, date_debut, date_fin, principal FROM agent_sites WHERE agent_id = :agent'),
            'cartes_professionnelles' => $requete('SELECT numero, type_activite, date_delivrance, date_expiration, courante FROM cartes_pro WHERE agent_id = :agent'),
            'qualifications' => $requete('SELECT t.libelle, q.intitule, q.numero, q.organisme, q.date_obtention, q.date_echeance FROM qualifications q JOIN types_qualification t ON t.id = q.type_qualification_id WHERE q.agent_id = :agent'),
            'vacations' => $requete("SELECT site_id, debut, fin, poste, pause_minutes FROM vacations WHERE agent_id = :agent AND statut = 'publiee'"),
            'pointages' => $requete('SELECT type, mode, horodatage, site_id, latitude, longitude, precision_m, distance_site_m, geoloc_statut, statut, indicateurs FROM pointages WHERE agent_id = :agent'),
            'services' => $requete('SELECT site_id, debut, fin, duree_minutes, statut FROM services WHERE agent_id = :agent'),
            'rondes' => $requete('SELECT site_id, demarree_le, cloturee_le, statut, nb_points_prevus, nb_points_controles FROM rondes WHERE agent_id = :agent'),
            'passages' => $requete('SELECT point_id, horodatage, latitude, longitude, statut FROM passages_ronde WHERE agent_id = :agent'),
            'anomalies' => $requete('SELECT site_id, categorie, gravite, description, created_at FROM anomalies WHERE agent_id = :agent'),
            'badges' => $requete('SELECT numero, statut, emis_le, desactive_le, derniere_utilisation FROM badges WHERE agent_id = :agent'),
            'bulletins' => $requete("SELECT annee, mois, version, publiee_le, premiere_consultation FROM fiches_paie WHERE agent_id = :agent AND statut <> 'depubliee'"),
            'consentements' => $this->lignes(
                'SELECT type, version_texte, accorde, created_at FROM consentements WHERE utilisateur_id = :utilisateur',
                (int) $agent['utilisateur_id'],
                'utilisateur',
            ),
            'connexions' => ['derniere_connexion' => $agent['derniere_connexion']],
        ];

        $motDePasse = self::motDePasse();
        if (!is_dir($this->dossierTemporaire) && !mkdir($this->dossierTemporaire, 0700, true) && !is_dir($this->dossierTemporaire)) {
            throw new RuntimeException('Dossier temporaire indisponible.');
        }
        $chemin = $this->dossierTemporaire . '/export-' . bin2hex(random_bytes(8)) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($chemin, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Création de l\'archive impossible.');
        }
        $zip->setPassword($motDePasse);
        $zip->addFromString('donnees.json', json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $zip->setEncryptionName('donnees.json', ZipArchive::EM_AES_256);

        $documents = $this->lignes('SELECT * FROM documents WHERE agent_id = :agent AND supprime_le IS NULL', $agentId);
        foreach ($documents as $document) {
            try {
                $nom = sprintf('documents/%d-%s', $document['id'], preg_replace('/[^\w.\-]+/u', '_', (string) $document['nom_original']));
                $zip->addFile($this->stockage->chemin($document), $nom);
                $zip->setEncryptionName($nom, ZipArchive::EM_AES_256);
            } catch (Throwable) {
                // Fichier absent du disque : l'export reste utilisable sans lui.
            }
        }
        $zip->close();

        $this->audit->journaliser('rgpd.exporter', 'agent', $agentId, ['documents' => count($documents)]);

        return [
            'chemin' => $chemin,
            'mot_de_passe' => $motDePasse,
            'nom' => sprintf('donnees-%s-%s.zip', strtolower((string) $agent['matricule']), Clock::now()->setTimezone(Clock::local())->format('Ymd')),
        ];
    }

    /**
     * @return array<string, int> nombre de lignes traitées par catégorie
     */
    public function purger(int $retentionGeolocJours): array
    {
        $maintenant = Clock::now();
        $rapport = [];

        $limiteGps = Clock::sql($maintenant->modify('-' . $retentionGeolocJours . ' days'));
        $rapport['gps_pointages'] = $this->executer('UPDATE pointages SET latitude = NULL, longitude = NULL WHERE horodatage < :limite AND latitude IS NOT NULL', $limiteGps);
        $rapport['gps_passages'] = $this->executer('UPDATE passages_ronde SET latitude = NULL, longitude = NULL WHERE horodatage < :limite AND latitude IS NOT NULL', $limiteGps);

        $rapport['tentatives_connexion'] = $this->executer('DELETE FROM tentatives_connexion WHERE created_at < :limite', Clock::sql($maintenant->modify('-6 months')));
        $rapport['jetons'] = $this->executer('DELETE FROM jetons_compte WHERE expire_le < :limite', Clock::sql($maintenant->modify('-7 days')));
        $rapport['notifications'] = $this->executer('DELETE FROM notifications WHERE created_at < :limite', Clock::sql($maintenant->modify('-1 year')));
        $rapport['limites_debit'] = $this->executer('DELETE FROM limites_debit WHERE expire_le < :limite', Clock::sql($maintenant));
        $rapport['alertes'] = $this->executer('DELETE FROM alertes_envoyees WHERE envoye_le < :limite', Clock::sql($maintenant->modify('-90 days')));
        $rapport['emails'] = $this->executer("DELETE FROM file_emails WHERE statut <> 'attente' AND created_at < :limite", Clock::sql($maintenant->modify('-90 days')));
        $rapport['demandes'] = $this->executer("DELETE FROM demandes WHERE statut = 'close' AND COALESCE(resolue_le, updated_at, created_at) < :limite", Clock::sql($maintenant->modify('-2 years')));

        $rapport['documents'] = 0;
        foreach ($this->documents->aPurger() as $document) {
            try {
                $this->stockage->effacer((string) $document['chemin']);
                $this->documents->effacerDefinitivement((int) $document['id']);
                ++$rapport['documents'];
            } catch (Throwable) {
                // Document encore référencé (bulletin, carte) : il reste en suppression logique.
            }
        }

        $rapport['audit'] = $this->purgerAudit($maintenant);

        $this->audit->journaliser('rgpd.purger', null, null, $rapport);

        return $rapport;
    }

    /**
     * Supprime les lignes d'audit expirées en consignant les plages supprimées,
     * pour que la vérification de la chaîne reconnaisse les trous légitimes.
     */
    private function purgerAudit(DateTimeImmutable $maintenant): int
    {
        $sensibles = implode(' OR ', array_map(static fn (int $i): string => 'action LIKE :s' . $i, array_keys(self::AUDIT_SENSIBLE)));
        $params = ['un_an' => Clock::sql($maintenant->modify('-1 year')), 'trois_ans' => Clock::sql($maintenant->modify('-3 years'))];
        foreach (self::AUDIT_SENSIBLE as $i => $motif) {
            $params['s' . $i] = $motif;
        }
        // Les événements de purge restent : ils justifient les trous de la chaîne.
        $condition = "action <> 'audit.purge' AND (created_at < :trois_ans OR (created_at < :un_an AND NOT ($sensibles)))";

        $statement = $this->pdo->prepare("SELECT id FROM audit WHERE $condition ORDER BY id");
        $statement->execute($params);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if ($ids === []) {
            return 0;
        }

        $plages = [];
        $debut = $precedent = $ids[0];
        foreach (array_slice($ids, 1) as $id) {
            if ($id !== $precedent + 1) {
                $plages[] = [$debut, $precedent];
                $debut = $id;
            }
            $precedent = $id;
        }
        $plages[] = [$debut, $precedent];

        $this->audit->journaliser('audit.purge', null, null, ['plages' => $plages]);
        $suppression = $this->pdo->prepare("DELETE FROM audit WHERE $condition");
        $suppression->execute($params);

        return $suppression->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lignes(string $sql, int $id, string $parametre = 'agent'): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$parametre => $id]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function executer(string $sql, string $limite): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['limite' => $limite]);

        return $statement->rowCount();
    }

    private static function motDePasse(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $mot = '';
        for ($i = 0; $i < 20; ++$i) {
            $mot .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return implode('-', str_split($mot, 5));
    }
}
