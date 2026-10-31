<?php
/**
 * @var array<string, mixed>|null $compte
 * @var \App\Models\Role|null $role
 * @var ?int $agentId
 * @var list<\App\Models\Role> $roles
 * @var list<array{id: int, nom: string}> $clients
 * @var int $clientDefaut
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 */
$creation = $compte === null;
$v = static fn (string $champ): string => valeur($ancien, $compte, $champ);
$roleChoisi = (string) ($ancien['role'] ?? ($role?->value ?? 'rh'));
$clientChoisi = (int) ($ancien['client_id'] ?? ($compte['client_id'] ?? $clientDefaut));
$titre = $creation ? 'Nouveau compte' : $compte['nom_complet'];
$this->layout('admin', ['titre' => $titre, 'menu' => 'utilisateurs']);
?>
<p class="fil-ariane"><a href="/admin/utilisateurs">Utilisateurs</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($titre) ?></h1>
        <?php if (!$creation): ?><p class="row"><?= badge_compte((string) $compte['statut']) ?> <?= $compte['totp_actif'] ? badge('2FA active', 'badge-success') : badge('2FA inactive') ?></p><?php endif ?>
    </div>
    <?php if ($agentId !== null): ?><a class="btn btn-outline btn-sm" href="/admin/agents/<?= e($agentId) ?>">Voir la fiche agent</a><?php endif ?>
</div>

<div class="grid-2-1">
    <form class="card stack" method="post" action="<?= $creation ? '/admin/utilisateurs' : '/admin/utilisateurs/' . e($compte['id']) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="nom_complet">Nom et prénom *</label><input class="input" id="nom_complet" name="nom_complet" value="<?= e($v('nom_complet')) ?>" maxlength="120" required<?= aria_erreur($erreurs, 'nom_complet') ?>><?= erreur_champ($erreurs, 'nom_complet') ?></div>
        <div class="field"><label for="email">E-mail *</label><input class="input" id="email" name="email" type="email" value="<?= e($v('email')) ?>" required<?= aria_erreur($erreurs, 'email') ?>><?= erreur_champ($erreurs, 'email') ?></div>
        <div class="field"><label for="role">Rôle *</label><select class="select" id="role" name="role"<?= aria_erreur($erreurs, 'role') ?>>
            <?php foreach ($roles as $r): ?><option value="<?= e($r->value) ?>"<?= selectionne($roleChoisi === $r->value) ?>><?= e($r->libelle()) ?></option><?php endforeach ?></select><?= erreur_champ($erreurs, 'role') ?></div>
        <div class="field"><label for="client_id">Client (comptes clients)</label><select class="select" id="client_id" name="client_id"<?= aria_erreur($erreurs, 'client_id') ?>><option value="">—</option>
            <?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"<?= selectionne($clientChoisi === $client['id']) ?>><?= e($client['nom']) ?></option><?php endforeach ?></select><?= erreur_champ($erreurs, 'client_id') ?></div>
        <?php if ($creation): ?>
            <label class="check"><input type="checkbox" name="inviter" value="1" checked> Envoyer l'invitation maintenant</label>
        <?php endif ?>
        <p class="help">La double authentification est obligatoire pour les rôles RH, administrateur, chef d'équipe et client : elle se configure à la première connexion.</p>
        <div class="form-actions"><a class="btn btn-ghost" href="/admin/utilisateurs">Annuler</a><button class="btn btn-primary" type="submit"><?= $creation ? 'Créer' : 'Enregistrer' ?></button></div>
    </form>

    <?php if (!$creation): ?>
        <section class="card stack">
            <h2 class="card-header">Accès</h2>
            <?php if ($compte['statut'] === 'invite'): ?>
                <form method="post" action="/admin/utilisateurs/<?= e($compte['id']) ?>/inviter"><?= csrf_field() ?><button class="btn btn-outline btn-sm btn-block" type="submit"><?= icone('send', 'icon-sm') ?>Renvoyer l'invitation</button></form>
            <?php endif ?>
            <?php if ($compte['totp_actif']): ?>
                <form method="post" action="/admin/utilisateurs/<?= e($compte['id']) ?>/2fa" data-confirmer="Réinitialiser la double authentification ? L'utilisateur devra la reconfigurer à sa prochaine connexion."><?= csrf_field() ?><button class="btn btn-outline btn-sm btn-block" type="submit"><?= icone('lock', 'icon-sm') ?>Réinitialiser la 2FA (téléphone perdu)</button></form>
            <?php endif ?>
            <?php if ($agentId === null): ?>
                <form class="stack stack-sm" method="post" action="/admin/utilisateurs/<?= e($compte['id']) ?>">
                    <?= csrf_field() ?>
                    <label for="u-statut">Statut du compte</label>
                    <select class="select select-sm" id="u-statut" name="statut">
                        <?php foreach (['actif' => 'Actif', 'suspendu' => 'Suspendu', 'desactive' => 'Désactivé'] as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($compte['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?>
                    </select>
                    <button class="btn btn-outline btn-sm" type="submit">Appliquer</button>
                </form>
            <?php endif ?>
            <p class="muted small">Dernière connexion : <?= $compte['derniere_connexion'] ? e(date_heure((string) $compte['derniere_connexion'])) : 'jamais' ?></p>
        </section>
    <?php endif ?>
</div>
