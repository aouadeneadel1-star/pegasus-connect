<?php
/**
 * @var int $minutes
 * @var string $lien
 */
$this->layout('email');
?>
<p>Bonjour,</p>
<p>Plusieurs tentatives de connexion ont échoué sur votre compte. Par sécurité, il est verrouillé pendant <?= e($minutes) ?> minutes.</p>
<p>Si ce n'était pas vous, nous vous conseillons de changer votre mot de passe dès que possible.</p>
<?= $this->insert('partials/email-bouton', ['lien' => $lien, 'libelle' => 'Réinitialiser mon mot de passe']) ?>
