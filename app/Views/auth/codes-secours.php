<?php
/**
 * @var list<string> $codes
 * @var string $suite
 */
$this->layout('auth', ['titre' => 'Codes de secours']);
?>
<div class="stack">
    <div class="alert alert-success" role="status">
        <?= icone('shield') ?>
        <span class="alert-body">La double authentification est active.</span>
    </div>
    <h1 class="auth-title">Vos codes de secours</h1>
    <p class="muted">Chaque code permet de vous connecter une fois si vous n'avez plus accès à votre téléphone. Notez-les et rangez-les en lieu sûr : ils ne seront plus affichés.</p>
    <ul class="codes-secours">
        <?php foreach ($codes as $code): ?>
            <li class="secret-box center"><?= e($code) ?></li>
        <?php endforeach ?>
    </ul>
    <a class="btn btn-primary btn-block" href="<?= e($suite) ?>">J'ai noté mes codes, continuer</a>
</div>
