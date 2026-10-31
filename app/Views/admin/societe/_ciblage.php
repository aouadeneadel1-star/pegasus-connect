<?php
/**
 * Champs communs du titre, de la catégorie et du ciblage d'un document société.
 *
 * @var array<string, mixed>|null $document
 * @var list<array{type: string, id: int}> $cibles
 * @var array<string, string> $categories
 * @var list<array{id: int, nom: string}> $sites
 * @var list<array{id: int, nom: string}> $equipes
 * @var list<array{id: int, nom: string}> $roles
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 */
$v = static fn (string $champ): string => valeur($ancien, $document, $champ);
$global = $document === null ? true : (bool) $document['ciblage_global'];
$cible = static fn (string $type, int $id): bool => in_array(['type' => $type, 'id' => $id], $cibles, true);
?>
<div class="field"><label for="titre">Titre *</label><input class="input" id="titre" name="titre" value="<?= e($v('titre')) ?>" maxlength="150" required<?= aria_erreur($erreurs ?? [], 'titre') ?>><?= erreur_champ($erreurs ?? [], 'titre') ?></div>
<div class="field"><label for="categorie">Catégorie *</label><select class="select" id="categorie" name="categorie">
    <?php foreach ($categories as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($v('categorie') === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
<div class="field"><label for="description">Résumé</label><textarea class="textarea" id="description" name="description" rows="3" maxlength="2000"><?= e($v('description')) ?></textarea></div>
<label class="check"><input type="checkbox" name="accuse_requis" value="1"<?= coche($document === null || (bool) $document['accuse_requis']) ?>> Demander un accusé de lecture</label>
<fieldset class="field">
    <legend>Destinataires</legend>
    <label class="check"><input type="radio" name="ciblage" value="global"<?= coche($global) ?>> Tous les agents et chefs d'équipe</label>
    <label class="check"><input type="radio" name="ciblage" value="cible"<?= coche(!$global) ?>> Une sélection :</label>
    <div class="stack stack-sm">
        <p class="small muted">Sites</p>
        <div class="choices choices-larges"><?php foreach ($sites as $site): ?><label class="chip"><input type="checkbox" name="sites[]" value="<?= e($site['id']) ?>"<?= coche($cible('site', $site['id'])) ?>> <?= e($site['nom']) ?></label><?php endforeach ?></div>
        <?php if ($equipes !== []): ?>
            <p class="small muted">Équipes</p>
            <div class="choices"><?php foreach ($equipes as $equipe): ?><label class="chip"><input type="checkbox" name="equipes[]" value="<?= e($equipe['id']) ?>"<?= coche($cible('equipe', $equipe['id'])) ?>> <?= e($equipe['nom']) ?></label><?php endforeach ?></div>
        <?php endif ?>
        <p class="small muted">Rôles</p>
        <div class="choices"><?php foreach ($roles as $role): ?><label class="chip"><input type="checkbox" name="roles[]" value="<?= e($role['id']) ?>"<?= coche($cible('role', $role['id'])) ?>> <?= e($role['nom']) ?></label><?php endforeach ?></div>
    </div>
</fieldset>
