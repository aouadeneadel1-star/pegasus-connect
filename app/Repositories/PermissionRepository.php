<?php

declare(strict_types=1);

namespace App\Repositories;

final class PermissionRepository extends Repository
{
    /**
     * @return array<string, string> code => portée
     */
    public function pourRole(int $roleId): array
    {
        $permissions = [];
        foreach ($this->lignes(
            'SELECT p.code, rp.portee
             FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role',
            ['role' => $roleId],
        ) as $row) {
            $permissions[(string) $row['code']] = (string) $row['portee'];
        }

        return $permissions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toutes(): array
    {
        return $this->lignes('SELECT * FROM permissions ORDER BY module, code');
    }

    /**
     * @return array<int, array<int, string>> role_id => [permission_id => portée]
     */
    public function matrice(): array
    {
        $matrice = [];
        foreach ($this->lignes('SELECT role_id, permission_id, portee FROM role_permissions') as $row) {
            $matrice[(int) $row['role_id']][(int) $row['permission_id']] = (string) $row['portee'];
        }

        return $matrice;
    }

    /**
     * Remplace les droits modifiables d'un rôle. Les permissions verrouillées ne sont jamais touchées.
     *
     * @param array<int, string> $permissions permission_id => portée
     */
    public function remplacerPourRole(int $roleId, array $permissions): void
    {
        $this->executer(
            'DELETE rp FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role AND p.verrouillee = 0',
            ['role' => $roleId],
        );

        $insert = $this->pdo->prepare(
            'INSERT INTO role_permissions (role_id, permission_id, portee)
             SELECT :role, id, :portee FROM permissions WHERE id = :permission AND verrouillee = 0'
        );
        foreach ($permissions as $permissionId => $portee) {
            $insert->execute(['role' => $roleId, 'portee' => $portee, 'permission' => $permissionId]);
        }
    }
}
