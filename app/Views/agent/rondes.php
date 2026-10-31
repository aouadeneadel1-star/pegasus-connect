<?php
/**
 * @var array<string, mixed>|null $service
 * @var array<string, mixed>|null $enCours
 * @var list<array<string, mixed>> $aDemarrer
 * @var list<array<string, mixed>> $recentes
 */
$this->layout('agent', ['titre' => 'Rondes', 'onglet' => 'accueil', 'retour' => '/', 'scripts' => ['accueil.js']]);
$statuts = ['terminee' => ['Terminée', 'badge-success'], 'incomplete' => ['Incomplète', 'badge-warning'], 'manquee' => ['Manquée', 'badge-danger'], 'en_cours' => ['En cours', 'badge-info'], 'annulee' => ['Annulée', '']];
?>
<?php if ($enCours !== null): ?>
    <a class="card card-accent row" href="/rondes/<?= e($enCours['id']) ?>">
        <span class="list-icon brand"><?= icone('route') ?></span>
        <span class="grow"><b>Reprendre la ronde en cours</b><br><span class="muted small"><?= e($enCours['modele'] ?? 'Ronde libre') ?> · <?= e($enCours['nb_points_controles']) ?>/<?= e($enCours['nb_points_prevus']) ?> points</span></span>
        <?= icone('next') ?>
    </a>
<?php elseif ($service === null): ?>
    <div class="alert alert-info"><?= icone('info') ?><span class="alert-body">Pointez votre arrivée pour accéder aux rondes du site.</span></div>
<?php else: ?>
    <h2 class="list-heading">À effectuer sur <?= e($service['site']) ?></h2>
    <?php if ($aDemarrer === []): ?>
        <div class="card empty">Aucune ronde planifiée pour le moment.</div>
    <?php else: ?>
        <ul class="list card">
            <?php foreach ($aDemarrer as $ronde): ?>
                <li class="list-item">
                    <span class="list-icon"><?= icone('clock') ?></span>
                    <span class="grow"><span class="list-title"><?= e($ronde['modele']) ?></span><span class="list-sub">Prévue à <?= e(heure((string) $ronde['prevue_le'])) ?> · <?= e($ronde['nb_points_prevus']) ?> points</span></span>
                    <button class="btn btn-primary btn-sm" type="button" data-demarrer-ronde="<?= e($ronde['id']) ?>">Démarrer</button>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <button class="btn btn-outline btn-block" type="button" data-demarrer-ronde=""><?= icone('route') ?>Ronde libre (tous les points du site)</button>
<?php endif ?>

<h2 class="list-heading mt-5">Mes dernières rondes</h2>
<?php if ($recentes === []): ?>
    <div class="card empty">Aucune ronde effectuée.</div>
<?php else: ?>
    <ul class="list card">
        <?php foreach ($recentes as $ronde): ?>
            <?php [$libelle, $classe] = $statuts[$ronde['statut']] ?? [$ronde['statut'], '']; ?>
            <li class="list-item">
                <span class="grow">
                    <span class="list-title"><?= e($ronde['modele'] ?? 'Ronde libre') ?> · <?= e($ronde['site']) ?></span>
                    <span class="list-sub"><?= e(date_heure((string) ($ronde['demarree_le'] ?? $ronde['prevue_le']))) ?> · <?= e($ronde['nb_points_controles']) ?>/<?= e($ronde['nb_points_prevus']) ?> points</span>
                </span>
                <?= badge($libelle, $classe) ?>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
