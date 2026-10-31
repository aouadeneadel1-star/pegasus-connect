<?php
/**
 * @var array<string, list<array<string, mixed>>> $permissions par module
 * @var array<int, array<int, string>> $matrice role_id => [permission_id => portée]
 * @var list<Role> $roles
 * @var array<string, string> $portees
 */
use App\Models\Role;

$this->layout('admin', ['titre' => 'Rôles et droits', 'menu' => 'roles']);
$modules = ['terrain' => 'Terrain', 'contact' => 'Contact', 'agents' => 'Agents', 'badges' => 'Badges', 'planning' => 'Planning', 'pointages' => 'Pointages', 'rondes' => 'Rondes', 'sites' => 'Sites', 'heures' => 'Heures', 'paie' => 'Paie', 'documents' => 'Documents', 'rapports' => 'Rapports', 'administration' => 'Administration', 'client' => 'Portail client'];
?>
<div class="page-head"><h1>Rôles et droits</h1></div>
<p class="help">Pour chaque rôle, choisissez la portée d'un droit : <b>Soi</b> (ses propres données), <b>Équipe</b> (sites dont le chef est responsable et leurs agents), <b>Ses sites</b> (client) ou <b>Tout</b>. Les droits marqués d'un cadenas ne se modifient pas ici.</p>

<form method="post" action="/admin/roles">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="table matrice">
            <thead>
                <tr><th scope="col">Droit</th><?php foreach ($roles as $role): ?><th scope="col"><?= e($role->libelle()) ?></th><?php endforeach ?></tr>
            </thead>
            <?php foreach ($permissions as $module => $liste): ?>
                <tbody>
                    <tr class="ligne-groupe"><th scope="rowgroup" colspan="<?= e(count($roles) + 1) ?>"><?= e($modules[$module] ?? ucfirst($module)) ?></th></tr>
                    <?php foreach ($liste as $permission): ?>
                        <?php $verrou = (bool) $permission['verrouillee']; ?>
                        <tr>
                            <th scope="row"><?= e($permission['libelle']) ?> <span class="mono small muted"><?= e($permission['code']) ?></span><?= $verrou ? ' ' . icone('lock', 'icon-sm') : '' ?></th>
                            <?php foreach ($roles as $role): ?>
                                <?php $actuelle = $matrice[$role->id()][(int) $permission['id']] ?? ''; ?>
                                <td>
                                    <?php if ($verrou): ?>
                                        <span class="small"><?= $actuelle === '' ? '—' : e($portees[$actuelle]) ?></span>
                                    <?php else: ?>
                                        <label class="sr-only" for="d-<?= e($role->value) ?>-<?= e($permission['id']) ?>"><?= e($role->libelle()) ?> : <?= e($permission['libelle']) ?></label>
                                        <select class="select select-sm" id="d-<?= e($role->value) ?>-<?= e($permission['id']) ?>" name="droits[<?= e($role->value) ?>][<?= e($permission['id']) ?>]">
                                            <option value="">—</option>
                                            <?php foreach ($portees as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($actuelle === $code) ?>><?= e($libelle) ?></option><?php endforeach ?>
                                        </select>
                                    <?php endif ?>
                                </td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            <?php endforeach ?>
        </table>
    </div>
    <div class="form-actions mt-4"><button class="btn btn-primary" type="submit">Enregistrer les droits</button></div>
</form>
