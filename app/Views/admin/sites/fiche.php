<?php
/**
 * @var array<string, mixed> $site
 * @var string $onglet
 * @var bool $peutGerer
 * @var array{borne: string, code: string}|null $codeEnrolement
 */
$this->layout('admin', ['titre' => $site['nom'], 'menu' => 'sites']);
$base = '/admin/sites/' . $site['id'];
$onglets = ['infos' => 'Informations', 'bornes' => 'Bornes', 'points' => 'Points de contrôle', 'modeles' => 'Rondes planifiées'];
?>
<p class="fil-ariane"><a href="/admin/sites">Sites</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($site['nom']) ?> <?= $site['actif'] ? '' : badge('Inactif') ?></h1>
        <p class="muted"><?= e($site['client']) ?> · <?= e($site['adresse']) ?>, <?= e($site['code_postal']) ?> <?= e($site['ville']) ?></p>
    </div>
    <?php if ($peutGerer): ?>
        <a class="btn btn-outline btn-sm" href="<?= e($base) ?>/modifier"><?= icone('edit', 'icon-sm') ?>Modifier</a>
    <?php endif ?>
</div>

<nav class="tabs" aria-label="Sections du site">
    <?php foreach ($onglets as $cle => $libelle): ?>
        <a href="<?= e($base . ($cle === 'infos' ? '' : '?onglet=' . $cle)) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
    <?php endforeach ?>
</nav>

<?= $this->insert('admin/sites/onglets/' . $onglet, $__data) ?>
