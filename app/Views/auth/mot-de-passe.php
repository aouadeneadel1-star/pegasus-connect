<?php
/** @var array<string, string> $erreurs */
$this->layout('auth', ['titre' => 'Changer de mot de passe']);
?>
<div class="stack center">
    <h1 class="auth-title">Changer de mot de passe</h1>
    <p class="muted">Vos autres sessions ouvertes seront fermées.</p>
</div>

<form method="post" action="/compte/mot-de-passe" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="actuel">Mot de passe actuel</label>
        <input class="input" id="actuel" name="actuel" type="password" autocomplete="current-password" required<?= aria_erreur($erreurs, 'actuel') ?>>
        <?= erreur_champ($erreurs, 'actuel') ?>
    </div>
    <div class="field">
        <label for="mot_de_passe">Nouveau mot de passe</label>
        <input class="input" id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" minlength="12" required<?= aria_erreur($erreurs, 'mot_de_passe') ?>>
        <p class="help">12 caractères minimum.</p>
        <?= erreur_champ($erreurs, 'mot_de_passe') ?>
    </div>
    <div class="field">
        <label for="mot_de_passe_confirmation">Confirmer le nouveau mot de passe</label>
        <input class="input" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Enregistrer</button>
    <a class="auth-footer" href="/">Annuler</a>
</form>
