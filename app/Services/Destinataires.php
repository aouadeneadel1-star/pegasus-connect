<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Repositories\SiteRepository;
use App\Repositories\UtilisateurRepository;

/**
 * Qui prévenir d'un événement sur un site : chefs responsables du site, puis RH et administrateurs.
 */
final class Destinataires
{
    public function __construct(
        private readonly SiteRepository $sites,
        private readonly UtilisateurRepository $utilisateurs,
    ) {
    }

    /**
     * @return list<int>
     */
    public function pourSite(int $siteId, bool $avecRh = true): array
    {
        $ids = $this->sites->utilisateursResponsables($siteId);
        if ($avecRh) {
            $ids = [...$ids, ...$this->utilisateurs->idsActifsParRoles([Role::Rh, Role::Admin])];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public function rh(): array
    {
        return $this->utilisateurs->idsActifsParRoles([Role::Rh, Role::Admin]);
    }

    /**
     * @return list<int>
     */
    public function administrateurs(): array
    {
        return $this->utilisateurs->idsActifsParRoles([Role::Admin]);
    }
}
