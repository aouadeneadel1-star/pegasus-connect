<?php
/**
 * @var int $agentId
 * @var string $motDePasse
 * @var string $lien
 */
$this->layout('admin', ['titre' => 'Export des données', 'menu' => 'agents']);
?>
<p class="fil-ariane"><a href="/admin/agents/<?= e($agentId) ?>">Fiche agent</a> ›</p>
<div class="page-head"><h1>Export des données personnelles</h1></div>

<section class="card stack">
    <div class="alert alert-warning"><?= icone('lock') ?><span class="alert-body">Ce mot de passe n'est affiché qu'une fois. Transmettez-le à l'agent par un autre canal que l'archive (téléphone, remise en main propre).</span></div>
    <p>Mot de passe de l'archive :</p>
    <p class="secret-box"><span class="mono strong" data-copiable><?= e($motDePasse) ?></span>
        <button class="btn btn-ghost btn-sm" type="button" data-copier><?= icone('copy', 'icon-sm') ?>Copier</button></p>
    <p>L'archive ZIP est chiffrée (AES-256). Le lien ci-dessous fonctionne une seule fois, pendant 10 minutes.</p>
    <p><a class="btn btn-primary" href="<?= e($lien) ?>"><?= icone('dl') ?>Télécharger l'archive</a></p>
    <p class="help">Le numéro de sécurité sociale n'est pas inclus : il se communique sur demande écrite, après vérification de l'identité du demandeur.</p>
</section>
