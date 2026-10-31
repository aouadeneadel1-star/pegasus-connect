<?php
/**
 * @var array<string, string> $erreurs
 * @var string $retour
 */
$this->layout('auth', ['titre' => 'Confirmer votre identité']);
?>
<div class="stack center">
    <h1 class="auth-title">Confirmer votre identité</h1>
    <p class="muted">Cette action concerne des données sensibles. Saisissez votre mot de passe ou un code de votre application d'authentification.</p>
</div>

<form method="post" action="/compte/reauthentifier" class="stack" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="retour" value="<?= e($retour) ?>">
    <div class="field">
        <label for="secret">Mot de passe ou code à 6 chiffres</label>
        <input class="input" id="secret" name="secret" type="password" autocomplete="current-password" required autofocus<?= aria_erreur($erreurs, 'secret') ?>>
        <?= erreur_champ($erreurs, 'secret') ?>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Confirmer</button>
    <a class="auth-footer" href="<?= e($retour) ?>">Annuler</a>
</form>
