<?php
$this->layout('auth', ['titre' => 'Mot de passe oublié']);
?>
<div class="stack center">
    <h1 class="auth-title">Mot de passe oublié</h1>
    <p class="muted">Indiquez l'adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
</div>

<form method="post" action="/mot-de-passe/oubli" class="stack">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Adresse e-mail</label>
        <input class="input" id="email" name="email" type="email" autocomplete="username" inputmode="email" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Envoyer le lien</button>
    <a class="auth-footer" href="/connexion">Revenir à la connexion</a>
</form>
