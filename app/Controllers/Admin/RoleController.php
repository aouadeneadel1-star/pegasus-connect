<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Role;

/**
 * Matrice des droits : permission × rôle, avec la portée de chaque droit.
 * Les permissions verrouillées (NIR, rôles, audit, RGPD) ne se modifient pas depuis l'interface.
 */
final class RoleController extends Controller
{
    private const PORTEES = ['soi' => 'Soi', 'equipe' => 'Équipe', 'client' => 'Ses sites', 'tout' => 'Tout'];

    public function index(Request $request): Response
    {
        $permissions = [];
        foreach ($this->factory->permissions()->toutes() as $permission) {
            $permissions[(string) $permission['module']][] = $permission;
        }

        return $this->vue('admin/roles', [
            'permissions' => $permissions,
            'matrice' => $this->factory->permissions()->matrice(),
            'roles' => Role::cases(),
            'portees' => self::PORTEES,
        ]);
    }

    public function enregistrer(Request $request): Response
    {
        $saisie = $request->raw('droits');
        $saisie = is_array($saisie) ? $saisie : [];
        $avant = $this->factory->permissions()->matrice();

        foreach (Role::cases() as $role) {
            $droits = [];
            foreach ((array) ($saisie[$role->value] ?? []) as $permissionId => $portee) {
                if (is_string($portee) && array_key_exists($portee, self::PORTEES) && (int) $permissionId > 0) {
                    $droits[(int) $permissionId] = $portee;
                }
            }
            $this->factory->permissions()->remplacerPourRole($role->id(), $droits);
        }

        $apres = $this->factory->permissions()->matrice();
        $changements = 0;
        foreach (Role::cases() as $role) {
            $changements += count(array_diff_assoc($apres[$role->id()] ?? [], $avant[$role->id()] ?? []))
                + count(array_diff_key($avant[$role->id()] ?? [], $apres[$role->id()] ?? []));
        }
        $this->factory->audit()->journaliser('roles.modifier', null, null, ['changements' => $changements]);

        return $this->rediriger('/admin/roles', sprintf('Droits enregistrés (%d changement(s)). Ils s\'appliquent à la prochaine page chargée par chaque utilisateur.', $changements));
    }
}
