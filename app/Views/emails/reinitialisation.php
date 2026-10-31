<?php
/**
 * @var string $nom
 * @var string $lien
 */
$this->layout('email');
?>
<p>Bonjour <?= e($nom) ?>,</p>
<p>Une réinitialisation du mot de passe de votre compte a été demandée. Ce lien est valable 30 minutes et ne fonctionne qu'une fois.</p>
<?= $this->insert('partials/email-bouton', ['lien' => $lien, 'libelle' => 'Choisir un nouveau mot de passe']) ?>
<p>Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.</p>
