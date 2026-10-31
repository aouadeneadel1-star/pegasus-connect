<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ce qu'un utilisateur a le droit de voir pour une permission donnée.
 * Les repositories traduisent ce périmètre en condition SQL.
 */
final class Perimetre
{
    /**
     * @param list<int> $siteIds
     * @param list<int> $agentIds
     */
    private function __construct(
        public readonly string $type,
        public readonly array $siteIds,
        public readonly array $agentIds,
    ) {
    }

    public static function tout(): self
    {
        return new self('tout', [], []);
    }

    public static function soi(int $agentId): self
    {
        return new self('soi', [], [$agentId]);
    }

    /**
     * @param list<int> $siteIds
     * @param list<int> $agentIds
     */
    public static function equipe(array $siteIds, array $agentIds): self
    {
        return new self('equipe', array_values(array_unique($siteIds)), array_values(array_unique($agentIds)));
    }

    /**
     * @param list<int> $siteIds
     */
    public static function client(array $siteIds): self
    {
        return new self('client', array_values(array_unique($siteIds)), []);
    }

    public function estGlobal(): bool
    {
        return $this->type === 'tout';
    }

    public function couvreSite(int $siteId): bool
    {
        return $this->estGlobal() || in_array($siteId, $this->siteIds, true);
    }

    public function couvreAgent(int $agentId): bool
    {
        return $this->estGlobal() || in_array($agentId, $this->agentIds, true);
    }

    /**
     * Condition SQL sur une colonne d'agent. Le tableau de paramètres est à fusionner
     * avec ceux de la requête appelante.
     *
     * @return array{0: string, 1: array<string, int>}
     */
    public function sqlAgents(string $colonne, string $prefixe = 'pa'): array
    {
        return $this->estGlobal() ? ['1 = 1', []] : self::in($colonne, $this->agentIds, $prefixe);
    }

    /**
     * @return array{0: string, 1: array<string, int>}
     */
    public function sqlSites(string $colonne, string $prefixe = 'ps'): array
    {
        return $this->estGlobal() ? ['1 = 1', []] : self::in($colonne, $this->siteIds, $prefixe);
    }

    /**
     * @param list<int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private static function in(string $colonne, array $ids, string $prefixe): array
    {
        if ($ids === []) {
            return ['1 = 0', []];
        }

        $params = [];
        foreach ($ids as $index => $id) {
            $params[$prefixe . $index] = $id;
        }

        return [$colonne . ' IN (:' . implode(', :', array_keys($params)) . ')', $params];
    }
}
