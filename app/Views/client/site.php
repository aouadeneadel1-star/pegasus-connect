<?php
/**
 * @var array<string, mixed> $site
 * @var list<array<string, mixed>> $presents
 * @var list<array<string, mixed>> $rondes
 * @var list<array<string, mixed>> $anomalies
 * @var bool $nomsAgents
 */
use App\Repositories\AnomalieRepository;

$this->layout('client', ['titre' => $site['nom'], 'menu' => 'tableau']);
?>
<p class="fil-ariane"><a href="/client">Tableau de bord</a> ›</p>
<div class="page-head">
    <div><h1><?= e($site['nom']) ?></h1><p class="muted"><?= e($site['adresse']) ?>, <?= e($site['code_postal']) ?> <?= e($site['ville']) ?></p></div>
    <a class="btn btn-outline btn-sm" href="/client/rapports?site=<?= e($site['id']) ?>"><?= icone('file', 'icon-sm') ?>Rapport</a>
</div>

<div class="grid-2">
    <section class="card">
        <h2 class="card-header">Présence</h2>
        <?php if ($presents === []): ?>
            <p class="muted">Aucun agent en service actuellement.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($presents as $present): ?>
                    <li class="list-item"><span class="list-icon success"><?= icone('user') ?></span><span class="grow"><?= $present['nom'] === null ? 'Agent de sécurité' : e($present['nom']) ?></span><span class="small tnum">depuis <?= e(heure((string) $present['debut'])) ?></span></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <section class="card">
        <div class="row row-between"><h2 class="card-header">Rondes des dernières 48 heures</h2><a class="small" href="/client/rondes?site=<?= e($site['id']) ?>">Tout voir</a></div>
        <?php if ($rondes === []): ?>
            <p class="muted">Aucune ronde.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach (array_slice($rondes, 0, 10) as $ronde): ?>
                    <li><a class="list-item" href="/client/rondes/<?= e($ronde['id']) ?>">
                        <span class="grow"><span class="list-title"><?= e($ronde['modele'] ?? 'Ronde') ?></span><span class="list-sub"><?= e(date_heure((string) ($ronde['demarree_le'] ?? $ronde['prevue_le']))) ?> · <?= e($ronde['nb_points_controles']) ?>/<?= e($ronde['nb_points_prevus']) ?> points</span></span>
                        <?= $this->insert('partials/statut-ronde', ['statut' => (string) $ronde['statut']]) ?>
                    </a></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</div>

<section class="card mt-5">
    <h2 class="card-header">Anomalies</h2>
    <?php if ($anomalies === []): ?>
        <p class="muted">Aucune anomalie signalée.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($anomalies as $anomalie): ?>
                <li><a class="list-item" href="/client/anomalies/<?= e($anomalie['id']) ?>"><span class="grow"><span class="list-title"><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?></span><span class="list-sub"><?= e(date_heure((string) $anomalie['created_at'])) ?></span></span><?= badge(AnomalieRepository::STATUTS[$anomalie['statut']]) ?></a></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
