<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class DemandeModificationRepository extends Repository
{
    /**
     * @param array<string, array{avant: string, apres: string}> $changements
     */
    public function creer(int $agentId, array $changements, ?int $documentId): int
    {
        return $this->inserer('demandes_modification_profil', [
            'agent_id' => $agentId,
            'changements' => json_encode($changements, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'document_id' => $documentId,
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne(
            'SELECT d.*, a.nom, a.prenom, a.matricule FROM demandes_modification_profil d
             JOIN agents a ON a.id = d.agent_id WHERE d.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enAttente(): array
    {
        return $this->lignes(
            "SELECT d.*, a.nom, a.prenom, a.matricule FROM demandes_modification_profil d
             JOIN agents a ON a.id = d.agent_id
             WHERE d.statut = 'en_attente' ORDER BY d.created_at"
        );
    }

    public function nombreEnAttente(): int
    {
        return (int) $this->valeur("SELECT COUNT(*) FROM demandes_modification_profil WHERE statut = 'en_attente'");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pourAgent(int $agentId): array
    {
        return $this->lignes(
            'SELECT * FROM demandes_modification_profil WHERE agent_id = :agent ORDER BY created_at DESC LIMIT 10',
            ['agent' => $agentId],
        );
    }

    public function traiter(int $id, string $statut, int $traitePar, ?string $motif): void
    {
        $this->modifierLigne('demandes_modification_profil', $id, [
            'statut' => $statut,
            'traite_par' => $traitePar,
            'traite_le' => Clock::sql(Clock::now()),
            'motif_refus' => $motif,
        ]);
    }
}
