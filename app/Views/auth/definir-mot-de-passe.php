<?php
/**
 * @var array<string, string> $erreurs
 * @var string $type invitation ou reinitialisation
 * @var string $jeton
 * @var string $nom
 */
$invitation = $type === 'invitation';
$this->layout('auth', ['titre' => $invitation ? 'Activer mon compte' : 'Nouveau mot de passe']);
$action = ($invitation ? '/invitation/' : '/mot-de-passe/reinitialiser/') . rawurlencode($jeton);
?>
<div class="stack center">
    <h1 class="auth-title"><?= $invitation ? 'Activer votre compte' : 'Choisir un nouveau mot de passe' ?></h1>
    <?php if ($nom !== ''): ?>
        <p class="muted">Bonjour <?= e($nom) ?>, choisissez votre mot de passe.</p>
    <?php endif ?>
</div>

<form method="post" action="<?= e($action) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="mot_de_passe">Mot de passe</label>
        <input class="input" id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" minlength="12" required
               aria-describedby="aide-mdp<?= isset($erreurs['mot_de_passe']) ? ' erreur-mot_de_passe' : '' ?>"<?= isset($erreurs['mot_de_passe']) ? ' aria-invalid="true"' : '' ?>>
        <p class="help" id="aide-mdp">12 caractères minimum. Une phrase de plusieurs mots est plus simple à retenir et plus solide.</p>
        <?= erreur_champ($erreurs, 'mot_de_passe') ?>
    </div>
    <div class="field">
        <label for="mot_de_passe_confirmation">Confirmer le mot de passe</label>
        <input class="input" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit"><?= $invitation ? 'Activer mon compte' : 'Enregistrer' ?></button>
</form>
