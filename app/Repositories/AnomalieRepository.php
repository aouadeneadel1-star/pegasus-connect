<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Models\Perimetre;

final class AnomalieRepository extends Repository
{
    public const CATEGORIES = [
        'intrusion' => 'Intrusion',
        'degradation' => 'Dégradation',
        'incendie' => 'Incendie, fumée',
        'technique' => 'Problème technique',
        'acces_ouvert' => 'Accès ouvert',
        'eclairage' => 'Éclairage',
        'personne' => 'Personne suspecte',
        'colis_suspect' => 'Colis suspect',
        'autre' => 'Autre',
    ];

    public const GRAVITES = [
        'info' => 'Information',
        'mineure' => 'Mineure',
        'majeure' => 'Majeure',
        'critique' => 'Critique',
    ];

    public const STATUTS = [
        'ouverte' => 'Ouverte',
        'en_cours' => 'En cours',
        'resolue' => 'Résolue',
        'close' => 'Close',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT an.*, s.nom AS site, s.client_id, a.nom, a.prenom, p.nom AS point, u.nom_complet AS traitee_par_nom
             FROM anomalies an
             JOIN sites s ON s.id = an.site_id
             JOIN agents a ON a.id = an.agent_id
             LEFT JOIN points_controle p ON p.id = an.point_id
             LEFT JOIN utilisateurs u ON u.id = an.traitee_par
             WHERE an.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parUuid(string $uuid): ?array
    {
        return $this->ligne('SELECT * FROM anomalies WHERE uuid_client = :uuid', ['uuid' => $uuid]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('anomalies', $donnees);
    }

    public function ajouterPhoto(int $anomalieId, int $documentId): void
    {
        $this->executer(
            'INSERT IGNORE INTO anomalies_photos (anomalie_id, document_id) VALUES (:anomalie, :document)',
            ['anomalie' => $anomalieId, 'document' => $documentId],
        );
    }

    /**
     * @return list<int>
     */
    public function photos(int $anomalieId): array
    {
        return $this->ids(
            'SELECT ap.document_id FROM anomalies_photos ap JOIN documents d ON d.id = ap.document_id
             WHERE ap.anomalie_id = :anomalie AND d.supprime_le IS NULL ORDER BY ap.document_id',
            ['anomalie' => $anomalieId],
        );
    }

    /**
     * @param array{site?: int, statut?: string, gravite?: string, ronde?: int} $filtres
     * @return list<array<string, mixed>>
     */
    public function lister(Perimetre $perimetre, array $filtres, bool $visiblesClientSeulement = false, int $limite = 200): array
    {
        [$condSite, $params] = $perimetre->sqlSites('an.site_id');
        $conditions = [$condSite];

        if ($visiblesClientSeulement) {
            $conditions[] = 'an.visible_client = 1';
        }
        if (($filtres['site'] ?? 0) > 0) {
            $conditions[] = 'an.site_id = :site';
            $params['site'] = (int) $filtres['site'];
        }
        if (($filtres['ronde'] ?? 0) > 0) {
            $conditions[] = 'an.ronde_id = :ronde';
            $params['ronde'] = (int) $filtres['ronde'];
        }
        if (array_key_exists($filtres['statut'] ?? '', self::STATUTS)) {
            $conditions[] = 'an.statut = :statut';
            $params['statut'] = $filtres['statut'];
        } elseif (($filtres['statut'] ?? '') === 'a_traiter') {
            $conditions[] = "an.statut IN ('ouverte', 'en_cours')";
        }
        if (array_key_exists($filtres['gravite'] ?? '', self::GRAVITES)) {
            $conditions[] = 'an.gravite = :gravite';
            $params['gravite'] = $filtres['gravite'];
        }

        return $this->lignes(
            "SELECT an.id, an.site_id, an.ronde_id, an.categorie, an.gravite, an.statut, an.description, an.created_at,
                    an.visible_client, s.nom AS site, a.nom, a.prenom, p.nom AS point,
                    (SELECT COUNT(*) FROM anomalies_photos ap WHERE ap.anomalie_id = an.id) AS nb_photos
             FROM anomalies an
             JOIN sites s ON s.id = an.site_id
             JOIN agents a ON a.id = an.agent_id
             LEFT JOIN points_controle p ON p.id = an.point_id
             WHERE " . implode(' AND ', $conditions) . '
             ORDER BY an.statut IN (\'ouverte\', \'en_cours\') DESC, FIELD(an.gravite, \'critique\', \'majeure\', \'mineure\', \'info\'), an.created_at DESC
             LIMIT ' . max(1, $limite),
            $params,
        );
    }

    public function nombreATraiter(Perimetre $perimetre): int
    {
        [$condSite, $params] = $perimetre->sqlSites('site_id');

        return (int) $this->valeur("SELECT COUNT(*) FROM anomalies WHERE statut IN ('ouverte', 'en_cours') AND $condSite", $params);
    }

    public function traiter(int $id, string $statut, ?string $commentaire, bool $visibleClient, int $utilisateurId): void
    {
        $this->modifierLigne('anomalies', $id, [
            'statut' => $statut,
            'commentaire_traitement' => $commentaire,
            'visible_client' => $visibleClient ? 1 : 0,
            'traitee_par' => $utilisateurId,
            'traitee_le' => Clock::sql(Clock::now()),
        ]);
    }
}
