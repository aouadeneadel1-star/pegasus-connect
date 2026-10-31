<?php
/**
 * @var int $statut
 * @var string $message
 */
$this->layout('auth', ['titre' => 'Erreur ' . $statut]);
?>
<div class="stack center">
    <h1><?= $statut === 404 ? 'Page introuvable' : ($statut === 403 ? 'Accès refusé' : 'Erreur ' . e($statut)) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-secondary btn-block" href="/">Retour à l'accueil</a>
</div>
