<?php
/**
 * @var array<string, mixed> $anomalie
 * @var list<string> $photos
 */
use App\Repositories\AnomalieRepository;

$this->layout('client', ['titre' => 'Anomalie', 'menu' => 'anomalies']);
?>
<p class="fil-ariane"><a href="/client/anomalies">Anomalies</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?> · <?= e($anomalie['site']) ?></h1>
        <p class="muted">Signalée le <?= e(date_heure((string) $anomalie['created_at'])) ?><?= $anomalie['point'] ? ' · ' . e($anomalie['point']) : '' ?><?= $anomalie['prenom'] ? ' par ' . e($anomalie['prenom'] . ' ' . mb_substr((string) $anomalie['nom'], 0, 1) . '.') : '' ?></p>
    </div>
    <div class="row"><?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']]) ?> <?= badge(AnomalieRepository::STATUTS[$anomalie['statut']]) ?></div>
</div>
<section class="card">
    <p><?= nl2br(e($anomalie['description'])) ?></p>
    <?php if ($photos !== []): ?>
        <div class="photos-apercu mt-4"><?php foreach ($photos as $url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener"><img src="<?= e($url) ?>" alt="Photo jointe" loading="lazy"></a><?php endforeach ?></div>
    <?php endif ?>
</section>
<?php if ($anomalie['commentaire_traitement']): ?>
    <section class="card mt-4"><h2 class="card-header">Suites données</h2><p><?= nl2br(e($anomalie['commentaire_traitement'])) ?></p></section>
<?php endif ?>
<p class="mt-4"><a class="btn btn-outline btn-sm" href="/client/contact/nouveau"><?= icone('msg', 'icon-sm') ?>Une question sur cette anomalie ?</a></p>
