<?php
/**
 * @var array<string, string> $erreurs
 */
$this->layout('admin', ['titre' => 'Nouveau document', 'menu' => 'societe']);
?>
<p class="fil-ariane"><a href="/admin/societe">Documents société</a> ›</p>
<div class="page-head"><h1>Nouveau document</h1></div>
<form class="card stack" method="post" action="/admin/societe" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?= $this->insert('admin/societe/_ciblage', $__data) ?>
    <div class="field"><label for="date_effet">En vigueur le</label><input class="input" id="date_effet" name="date_effet" type="date"></div>
    <div class="field"><label for="fichier">Document PDF *</label><input class="input" id="fichier" name="fichier" type="file" accept="application/pdf" required<?= aria_erreur($erreurs, 'fichier') ?>><?= erreur_champ($erreurs, 'fichier') ?></div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="/admin/societe">Annuler</a>
        <button class="btn btn-primary" type="submit">Créer le brouillon</button>
    </div>
</form>
