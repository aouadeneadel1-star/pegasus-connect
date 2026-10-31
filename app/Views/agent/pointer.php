<?php
/**
 * @var array<string, mixed>|null $service
 * @var bool $geolocalisation
 */
$this->layout('agent', ['titre' => 'Pointer', 'onglet' => 'pointer', 'scripts' => ['pointage.js']]);
?>
<div class="stack" data-pointage data-geolocalisation="<?= $geolocalisation ? '1' : '0' ?>">
    <p class="center muted small" data-consigne>
        <?= $service === null
            ? 'Scannez le QR code de la borne ou du poste pour pointer votre arrivée.'
            : 'En service sur ' . e($service['site']) . ' depuis ' . e(heure((string) $service['debut'])) . '. Scannez pour pointer votre départ.' ?>
    </p>

    <div class="scanner" data-scanner>
        <video playsinline muted data-video></video>
        <div class="scanner-lib" id="lecteur-qr" hidden></div>
        <div class="scanner-cadre" aria-hidden="true"></div>
        <p class="scanner-message" data-scanner-message role="status">Ouverture de la caméra…</p>
    </div>

    <div class="row row-between">
        <button class="btn btn-ghost btn-sm" type="button" data-torche hidden><?= icone('torch', 'icon-sm') ?>Lampe</button>
        <button class="btn btn-ghost btn-sm" type="button" data-relancer hidden><?= icone('refresh', 'icon-sm') ?>Relancer la caméra</button>
    </div>

    <p class="help center">
        <?= $geolocalisation
            ? 'Votre position est relevée au moment du scan uniquement.'
            : 'Vous avez refusé la géolocalisation : vos pointages sur les sites à QR fixe seront vérifiés.' ?>
    </p>
</div>

<div class="confirmation" data-confirmation hidden role="alertdialog" aria-modal="true" aria-labelledby="confirmation-titre" tabindex="-1">
    <span class="rond" data-confirmation-icone><?= icone('check', 'icon-xl') ?></span>
    <h2 id="confirmation-titre" data-confirmation-titre>Arrivée enregistrée</h2>
    <p class="heure" data-confirmation-heure></p>
    <p data-confirmation-site></p>
    <ul class="stack stack-sm" data-confirmation-raisons></ul>
    <div class="actions">
        <button class="btn btn-outline" type="button" data-annuler-pointage hidden>Annuler ce pointage <span data-decompte></span></button>
        <a class="btn btn-secondary" href="/" data-terminer>Terminer</a>
    </div>
</div>

<dialog class="dialog" data-changement-site aria-labelledby="changement-titre">
    <form method="dialog" class="stack">
        <h2 id="changement-titre">Changer de site ?</h2>
        <p data-changement-message></p>
        <div class="row row-end">
            <button class="btn btn-ghost" value="non">Annuler</button>
            <button class="btn btn-primary" value="oui">Clôturer et pointer ici</button>
        </div>
    </form>
</dialog>

<template data-modele-icone-alerte><?= icone('alert', 'icon-xl') ?></template>
<template data-modele-icone-sync><?= icone('sync', 'icon-xl') ?></template>
<template data-modele-icone-erreur><?= icone('x', 'icon-xl') ?></template>
