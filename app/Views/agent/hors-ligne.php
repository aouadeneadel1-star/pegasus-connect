<?php
$this->layout('agent', ['titre' => 'Hors ligne', 'onglet' => '']);
?>
<div class="card empty stack">
    <?= icone('wifi-off', 'icon-xl') ?>
    <h2>Pas de connexion</h2>
    <p>Cette page n'est pas disponible hors ligne. Vous pouvez toujours pointer et scanner les points de ronde : les enregistrements seront transmis au retour du réseau.</p>
    <a class="btn btn-primary" href="/pointer"><?= icone('qr') ?>Pointer</a>
</div>
