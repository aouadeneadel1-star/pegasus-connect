<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class ClientRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function lister(): array
    {
        return $this->lignes(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM sites s WHERE s.client_id = c.id AND s.actif = 1) AS nb_sites,
                    (SELECT COUNT(*) FROM utilisateurs u WHERE u.client_id = c.id AND u.statut <> \'desactive\') AS nb_comptes
             FROM clients c ORDER BY c.raison_sociale'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->ligne('SELECT * FROM clients WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return list<array{id: int, nom: string}>
     */
    public function choix(): array
    {
        return array_map(
            static fn (array $ligne): array => ['id' => (int) $ligne['id'], 'nom' => (string) $ligne['raison_sociale']],
            $this->lignes('SELECT id, raison_sociale FROM clients WHERE actif = 1 ORDER BY raison_sociale'),
        );
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->inserer('clients', [...$donnees, 'created_at' => Clock::sql(Clock::now())]);
    }

    /**
     * @param array<string, scalar|null> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->modifierLigne('clients', $id, $donnees);
    }

    /**
     * @return array{noms_agents: bool, validite_carte_pro: bool}
     */
    public function options(int $id): array
    {
        $json = $this->valeur('SELECT options_portail FROM clients WHERE id = :id', ['id' => $id]);
        $options = is_string($json) ? json_decode($json, true) : [];

        return [
            'noms_agents' => (bool) ($options['noms_agents'] ?? false),
            'validite_carte_pro' => (bool) ($options['validite_carte_pro'] ?? false),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comptes(int $clientId): array
    {
        return $this->lignes(
            'SELECT id, email, nom_complet, statut, derniere_connexion FROM utilisateurs WHERE client_id = :client ORDER BY nom_complet',
            ['client' => $clientId],
        );
    }
}
