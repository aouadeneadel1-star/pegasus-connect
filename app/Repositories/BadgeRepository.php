<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * Cartes d'entreprise NFC. L'UID de la puce n'est jamais stocké en clair :
 * seule son empreinte HMAC et ses quatre derniers caractères le sont.
 */
final class BadgeRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT b.*, a.nom, a.prenom, a.matricule, a.utilisateur_id FROM badges b
             LEFT JOIN agents a ON a.id = b.agent_id WHERE b.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parEmpreinte(string $uidHash): ?array
    {
        return $this->ligne(
            'SELECT b.*, a.nom, a.prenom, a.utilisateur_id FROM badges b
             LEFT JOIN agents a ON a.id = b.agent_id WHERE b.uid_hash = :hash',
            ['hash' => $uidHash],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function actifPourAgent(int $agentId): ?array
    {
        return $this->ligne("SELECT * FROM badges WHERE agent_id = :agent AND statut = 'actif'", ['agent' => $agentId]);
    }

    /**
     * @return list<array<string, mixed>> badges terminés de l'agent
     */
    public function historique(int $agentId): array
    {
        return $this->lignes(
            "SELECT * FROM badges WHERE agent_id = :agent AND statut <> 'actif' ORDER BY emis_le DESC",
            ['agent' => $agentId],
        );
    }

    public function numeroUtilise(string $numero): bool
    {
        return (bool) $this->valeur('SELECT COUNT(*) FROM badges WHERE numero = :numero', ['numero' => $numero]);
    }

    public function prochainNumero(): string
    {
        $dernier = (int) $this->valeur("SELECT MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) FROM badges WHERE numero REGEXP '^B-[0-9]+$'");

        return sprintf('B-%06d', $dernier + 1);
    }

    public function emettre(int $agentId, string $numero, ?string $carteProNumero, int $emisPar): int
    {
        return $this->inserer('badges', [
            'numero' => $numero,
            'agent_id' => $agentId,
            'statut' => 'actif',
            'pin_a_changer' => 1,
            'carte_pro_numero' => $carteProNumero,
            'emis_le' => Clock::sql(Clock::now()),
            'emis_par' => $emisPar,
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    public function demarrerAssociation(int $id, int $borneId, DateTimeImmutable $expiration): void
    {
        $this->modifierLigne('badges', $id, [
            'association_borne_id' => $borneId,
            'association_expire_le' => Clock::sql($expiration),
        ]);
    }

    /**
     * @return array<string, mixed>|null badge qui attend sa puce sur cette borne
     */
    public function associationEnAttente(int $borneId): ?array
    {
        return $this->ligne(
            "SELECT b.*, a.prenom, a.nom FROM badges b JOIN agents a ON a.id = b.agent_id
             WHERE b.association_borne_id = :borne AND b.association_expire_le > :maintenant AND b.statut = 'actif'
             ORDER BY b.association_expire_le DESC LIMIT 1",
            ['borne' => $borneId, 'maintenant' => Clock::sql(Clock::now())],
        );
    }

    public function associer(int $id, string $uidHash, string $uidFin): void
    {
        $this->modifierLigne('badges', $id, [
            'uid_hash' => $uidHash,
            'uid_fin' => $uidFin,
            'association_borne_id' => null,
            'association_expire_le' => null,
        ]);
    }

    public function terminer(int $id, string $statut, ?string $motif): void
    {
        $this->modifierLigne('badges', $id, [
            'statut' => $statut,
            'desactive_le' => Clock::sql(Clock::now()),
            'motif_desactivation' => $motif,
            'association_borne_id' => null,
            'association_expire_le' => null,
        ]);
    }

    public function definirPin(int $id, ?string $hash): void
    {
        $this->modifierLigne('badges', $id, [
            'pin_hash' => $hash,
            'pin_a_changer' => $hash === null ? 1 : 0,
            'echecs_pin' => 0,
            'verrouille_jusqu_a' => null,
        ]);
    }

    /**
     * @return int nombre d'échecs consécutifs après celui-ci
     */
    public function enregistrerEchecPin(int $id): int
    {
        $this->executer('UPDATE badges SET echecs_pin = echecs_pin + 1 WHERE id = :id', ['id' => $id]);

        return (int) $this->valeur('SELECT echecs_pin FROM badges WHERE id = :id', ['id' => $id]);
    }

    public function verrouiller(int $id, DateTimeImmutable $jusqua): void
    {
        $this->modifierLigne('badges', $id, ['verrouille_jusqu_a' => Clock::sql($jusqua), 'echecs_pin' => 0]);
    }

    public function enregistrerUtilisation(int $id): void
    {
        $this->modifierLigne('badges', $id, ['derniere_utilisation' => Clock::sql(Clock::now()), 'echecs_pin' => 0]);
    }

    /**
     * @param array{q?: string, statut?: string} $filtres
     * @return list<array<string, mixed>>
     */
    public function lister(array $filtres): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        if (($filtres['q'] ?? '') !== '') {
            $conditions[] = '(b.numero LIKE :q_numero OR a.nom LIKE :q_nom OR a.matricule LIKE :q_matricule)';
            $params['q_numero'] = $params['q_nom'] = $params['q_matricule'] = '%' . $filtres['q'] . '%';
        }
        if (in_array($filtres['statut'] ?? '', ['stock', 'actif', 'perdu', 'vole', 'desactive', 'restitue'], true)) {
            $conditions[] = 'b.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }

        return $this->lignes(
            'SELECT b.id, b.numero, b.uid_fin, b.statut, b.pin_hash IS NOT NULL AND b.pin_a_changer = 0 AS pin_defini,
                    b.emis_le, b.derniere_utilisation, b.desactive_le, b.motif_desactivation,
                    a.id AS agent_id, a.nom, a.prenom, a.matricule
             FROM badges b LEFT JOIN agents a ON a.id = b.agent_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY b.statut = \'actif\' DESC, b.emis_le DESC LIMIT 500',
            $params,
        );
    }
}
