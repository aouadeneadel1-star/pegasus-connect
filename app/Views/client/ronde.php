<?php
/**
 * @var array<string, mixed> $ronde
 * @var array<string, mixed>|null $etat
 * @var list<array<string, mixed>> $anomalies
 * @var bool $nomsAgents
 */
use App\Repositories\AnomalieRepository;

$this->layout('client', ['titre' => 'Ronde', 'menu' => 'rondes']);
?>
<p class="fil-ariane"><a href="/client/rondes">Rondes</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($ronde['modele'] ?? 'Ronde') ?> · <?= e($ronde['site']) ?></h1>
        <p class="muted"><?= $ronde['demarree_le'] ? 'Démarrée le ' . e(date_heure((string) $ronde['demarree_le'])) : 'Prévue le ' . e(date_heure((string) $ronde['prevue_le'])) ?><?= $ronde['cloturee_le'] ? ', terminée à ' . e(heure((string) $ronde['cloturee_le'])) : '' ?><?= $nomsAgents && $ronde['prenom'] ? ' · ' . e($ronde['prenom'] . ' ' . mb_substr((string) $ronde['nom'], 0, 1) . '.') : '' ?></p>
    </div>
    <?= $this->insert('partials/statut-ronde', ['statut' => (string) $ronde['statut']]) ?>
</div>
<section class="card">
    <h2 class="card-header">Points de contrôle<?= $etat === null ? '' : ' : ' . e($etat['nb_controles']) . ' / ' . e($etat['nb_prevus']) ?></h2>
    <?php if ($etat === null): ?>
        <p class="muted">Ronde non effectuée.</p>
    <?php else: ?>
        <ol class="list">
            <?php foreach ($etat['points'] as $point): ?>
                <li class="list-item">
                    <span class="list-icon<?= $point['controle'] ? ' success' : ' danger' ?>"><?= icone($point['controle'] ? 'check' : 'x') ?></span>
                    <span class="grow"><?= e($point['nom']) ?> <span class="muted small"><?= e($point['zone'] ?? '') ?></span></span>
                    <span class="tnum small"><?= e($point['heure'] ?? 'non contrôlé') ?></span>
                </li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>
</section>
<?php if ($anomalies !== []): ?>
    <section class="card mt-5">
        <h2 class="card-header">Anomalies signalées</h2>
        <ul class="list"><?php foreach ($anomalies as $anomalie): ?><li><a class="list-item" href="/client/anomalies/<?= e($anomalie['id']) ?>"><span class="grow"><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?></span><?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']]) ?></a></li><?php endforeach ?></ul>
    </section>
<?php endif ?>
