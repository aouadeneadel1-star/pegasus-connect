<?php
/**
 * @var string $nom
 * @var string $lien
 * @var int $heures
 */
$this->layout('email');
?>
<p>Bonjour <?= e($nom) ?>,</p>
<p>Votre espace Pegasus Connect est prêt. Il vous permet de pointer vos prises de service, de consulter votre planning, vos documents et vos bulletins de paie.</p>
<p>Choisissez votre mot de passe pour activer votre compte. Ce lien est valable <?= e($heures) ?> heures.</p>
<?= $this->insert('partials/email-bouton', ['lien' => $lien, 'libelle' => 'Activer mon compte']) ?>
