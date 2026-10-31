<?php
/**
 * @var string $nom
 * @var string $titre
 * @var ?string $message
 * @var string $lien
 */
$this->layout('email');
?>
<p>Bonjour <?= e($nom ?? '') ?>,</p>
<p><b><?= e($titre) ?></b></p>
<?php if (!empty($message)): ?>
    <p><?= e($message) ?></p>
<?php endif ?>
<?= $this->insert('partials/email-bouton', ['lien' => $lien, 'libelle' => 'Ouvrir Pegasus Connect']) ?>
