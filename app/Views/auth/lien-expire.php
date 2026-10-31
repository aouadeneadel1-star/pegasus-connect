<?php
/** @var string $type */
$this->layout('auth', ['titre' => 'Lien expiré']);
?>
<div class="stack center">
    <h1 class="auth-title">Lien expiré</h1>
    <?php if ($type === 'invitation'): ?>
        <p class="muted">Ce lien d'invitation a expiré ou a déjà été utilisé. Demandez au service RH de vous renvoyer une invitation.</p>
    <?php else: ?>
        <p class="muted">Ce lien de réinitialisation a expiré ou a déjà été utilisé. Vous pouvez en demander un nouveau.</p>
        <a class="btn btn-primary btn-block" href="/mot-de-passe/oubli">Demander un nouveau lien</a>
    <?php endif ?>
    <a class="auth-footer" href="/connexion">Revenir à la connexion</a>
</div>
