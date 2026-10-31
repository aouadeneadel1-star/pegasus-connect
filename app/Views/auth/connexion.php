<?php
/**
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $ancien
 * @var array{succes: ?string, erreur: ?string, info: ?string} $flash
 * @var ?string $retour
 */
$this->layout('auth', ['titre' => 'Connexion']);
?>
<?= $this->insert('partials/flash', ['flash' => $flash]) ?>

<form method="post" action="/connexion" class="stack" novalidate>
    <?= csrf_field() ?>
    <?php if ($retour !== null): ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
    <?php endif ?>

    <div class="field">
        <label for="email">Adresse e-mail</label>
        <input class="input" id="email" name="email" type="email" autocomplete="username" inputmode="email" required
               value="<?= e((string) ($ancien['email'] ?? '')) ?>"<?= aria_erreur($erreurs, 'email') ?>>
        <?= erreur_champ($erreurs, 'email') ?>
    </div>

    <div class="field">
        <label for="mot_de_passe">Mot de passe</label>
        <input class="input" id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required>
    </div>

    <label class="check">
        <input type="checkbox" name="rester_connecte" value="1"<?= coche(!empty($ancien['rester_connecte'])) ?>>
        Rester connecté sur cet appareil
    </label>

    <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
    <a class="auth-footer" href="/mot-de-passe/oubli">Mot de passe oublié ?</a>
</form>
