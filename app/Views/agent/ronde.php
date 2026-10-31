<?php
/**
 * @var array<string, mixed> $etat état renvoyé par RondeService::etat()
 * @var bool $geolocalisation
 */
$this->layout('agent', ['titre' => $etat['modele'], 'onglet' => 'accueil', 'retour' => '/rondes', 'scripts' => ['ronde.js']]);
$enCours = $etat['statut'] === 'en_cours';
?>
<div class="stack" data-ronde="<?= e($etat['id']) ?>" data-geolocalisation="<?= $geolocalisation ? '1' : '0' ?>" data-fin-prevue="<?= e($etat['fin_prevue']) ?>">
    <section class="card stack stack-sm">
        <div class="row row-between">
            <b><?= e($etat['site']) ?></b>
            <span class="tnum" data-progression><?= e($etat['nb_controles']) ?> / <?= e($etat['nb_prevus']) ?></span>
        </div>
        <div class="segments" aria-hidden="true" data-segments>
            <?php foreach ($etat['points'] as $point): ?><i class="<?= $point['controle'] ? 'fait' : '' ?>"></i><?php endforeach ?>
        </div>
        <?php if ($enCours): ?>
            <p class="muted small" data-temps-restant></p>
        <?php else: ?>
            <p><?= badge($etat['statut'] === 'terminee' ? 'Terminée' : 'Clôturée', $etat['statut'] === 'terminee' ? 'badge-success' : 'badge-warning') ?></p>
        <?php endif ?>
    </section>

    <?php if ($enCours): ?>
        <div class="scanner scanner-compact" data-scanner hidden>
            <video playsinline muted data-video></video>
            <div class="scanner-lib" id="lecteur-qr" hidden></div>
            <div class="scanner-cadre" aria-hidden="true"></div>
            <p class="scanner-message" data-scanner-message role="status"></p>
        </div>
    <?php endif ?>

    <ol class="list card" data-points>
        <?php foreach ($etat['points'] as $point): ?>
            <li class="list-item" data-point="<?= e($point['id']) ?>">
                <span class="list-icon<?= $point['controle'] ? ' success' : '' ?>"><?= icone($point['controle'] ? 'check' : 'pin') ?></span>
                <span class="grow">
                    <span class="list-title"><?= e($point['ordre']) ?>. <?= e($point['nom']) ?></span>
                    <span class="list-sub"><?= e($point['zone'] ?? '') ?><?= $point['obligatoire'] ? '' : ' · facultatif' ?></span>
                </span>
                <span class="tnum small" data-heure><?= e($point['heure'] ?? '') ?></span>
            </li>
        <?php endforeach ?>
    </ol>

    <?php if ($enCours): ?>
        <div class="barre-actions">
            <button class="btn btn-primary btn-xl" type="button" data-scanner-point><?= icone('qr') ?>Scanner un point</button>
            <a class="btn btn-outline btn-xl btn-icon" href="/anomalie" aria-label="Signaler une anomalie"><?= icone('alert') ?></a>
        </div>
        <form class="card stack" data-cloture>
            <label for="commentaire-cloture">Commentaire de fin de ronde</label>
            <textarea class="textarea" id="commentaire-cloture" name="commentaire" rows="2" maxlength="500" placeholder="Obligatoire si des points n'ont pas pu être contrôlés"></textarea>
            <button class="btn btn-secondary" type="submit"><?= icone('flag') ?>Clôturer la ronde</button>
        </form>
    <?php endif ?>
</div>
