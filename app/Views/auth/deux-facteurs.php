<?php
/**
 * @var array<string, string> $erreurs
 * @var ?string $retour
 */
$this->layout('auth', ['titre' => 'Vérification']);
?>
<div class="stack center">
    <h1 class="auth-title">Code de sécurité</h1>
    <p class="muted">Saisissez le code à 6 chiffres affiché dans votre application d'authentification, ou l'un de vos codes de secours.</p>
</div>

<form method="post" action="/connexion/2fa" class="stack" novalidate>
    <?= csrf_field() ?>
    <?php if ($retour !== null): ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
    <?php endif ?>

    <div class="field">
        <label for="code">Code</label>
        <input class="input otp-input" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               maxlength="11" required autofocus<?= aria_erreur($erreurs, 'code') ?>>
        <?= erreur_champ($erreurs, 'code') ?>
    </div>

    <label class="check">
        <input type="checkbox" name="appareil_confiance" value="1">
        Faire confiance à cet appareil pendant 30 jours
    </label>

    <button class="btn btn-primary btn-block" type="submit">Valider</button>
    <p class="help center">5 essais maximum. Au-delà, le compte est verrouillé 15 minutes.</p>
    <a class="auth-footer" href="/connexion">Revenir à la connexion</a>
</form>
