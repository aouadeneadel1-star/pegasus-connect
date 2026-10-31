<?php
/**
 * @var list<array<string, mixed>> $utilisateurs
 * @var array{recherche: string, role: string, statut: string} $filtres
 * @var list<Role> $roles
 */
use App\Models\Role;

$this->layout('admin', ['titre' => 'Utilisateurs', 'menu' => 'utilisateurs']);
?>
<div class="page-head">
    <h1>Utilisateurs</h1>
    <a class="btn btn-primary btn-sm" href="/admin/utilisateurs/nouveau"><?= icone('user-plus', 'icon-sm') ?>Nouveau compte</a>
</div>
<p class="help">Les comptes des agents et chefs d'équipe se créent depuis la fiche agent. Ici : service RH, administrateurs et contacts clients.</p>
<form class="filtres" method="get" action="/admin/utilisateurs">
    <div class="field"><label for="u-q">Recherche</label><input class="input input-sm" id="u-q" name="q" type="search" value="<?= e($filtres['recherche']) ?>" placeholder="Nom, e-mail"></div>
    <div class="field"><label for="u-role">Rôle</label><select class="select select-sm" id="u-role" name="role"><option value="">Tous</option>
        <?php foreach ($roles as $role): ?><option value="<?= e($role->value) ?>"<?= selectionne($filtres['role'] === $role->value) ?>><?= e($role->libelle()) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="u-statut">Statut</label><select class="select select-sm" id="u-statut" name="statut"><option value="">Tous</option>
        <?php foreach (['actif' => 'Actifs', 'invite' => 'Invités', 'suspendu' => 'Suspendus', 'desactive' => 'Désactivés'] as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th scope="col">Nom</th><th scope="col">E-mail</th><th scope="col">Rôle</th><th scope="col">2FA</th><th scope="col">Dernière connexion</th><th scope="col">Statut</th></tr></thead>
        <tbody>
            <?php foreach ($utilisateurs as $compte): ?>
                <tr>
                    <td><a href="/admin/utilisateurs/<?= e($compte['id']) ?>"><?= e($compte['nom_complet']) ?></a></td>
                    <td><?= e($compte['email']) ?></td>
                    <td><?= e(Role::from((string) $compte['role_code'])->libelle()) ?><?= $compte['client'] ? '<br><span class="muted small">' . e($compte['client']) . '</span>' : '' ?></td>
                    <td><?= $compte['totp_actif'] ? badge('Active', 'badge-success') : '<span class="muted">—</span>' ?></td>
                    <td><?= $compte['derniere_connexion'] ? e(date_heure((string) $compte['derniere_connexion'])) : '<span class="muted">Jamais</span>' ?></td>
                    <td><?= badge_compte((string) $compte['statut']) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
