<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;

final class PerimetreRepository extends Repository
{
    /**
     * @return list<int>
     */
    public function sitesDuChef(int $agentId): array
    {
        return $this->ids('SELECT site_id FROM sites_responsables WHERE agent_id = :agent', ['agent' => $agentId]);
    }

    /**
     * Agents des équipes dirigées par le chef et agents affectés à ses sites.
     *
     * @param list<int> $siteIds
     * @return list<int>
     */
    public function agentsDuChef(int $agentId, array $siteIds): array
    {
        $agents = $this->ids(
            'SELECT a.id FROM agents a JOIN equipes e ON e.id = a.equipe_id WHERE e.chef_agent_id = :chef',
            ['chef' => $agentId],
        );

        if ($siteIds !== []) {
            $params = ['aujourdhui' => Clock::now()->setTimezone(Clock::local())->format('Y-m-d')];
            foreach ($siteIds as $index => $siteId) {
                $params['s' . $index] = $siteId;
            }
            $placeholders = ':s' . implode(', :s', array_keys($siteIds));
            $agents = [...$agents, ...$this->ids(
                "SELECT DISTINCT agent_id FROM agent_sites
                 WHERE site_id IN ($placeholders) AND (date_fin IS NULL OR date_fin >= :aujourdhui)",
                $params,
            )];
        }

        $agents[] = $agentId;

        return array_values(array_unique($agents));
    }

    /**
     * @return list<int>
     */
    public function sitesDuClient(int $clientId): array
    {
        return $this->ids('SELECT id FROM sites WHERE client_id = :client', ['client' => $clientId]);
    }
}
