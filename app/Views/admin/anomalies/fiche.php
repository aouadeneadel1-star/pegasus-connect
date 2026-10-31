<?php
/**
 * @var array<string, mixed> $anomalie
 * @var list<string> $photos
 * @var bool $peutTraiter
 */
use App\Repositories\AnomalieRepository;

$this->layout('admin', ['titre' => 'Anomalie', 'menu' => 'anomalies']);
$classes = ['critique' => 'badge-danger', 'majeure' => 'badge-warning', 'mineure' => 'badge-info', 'info' => ''];
?>
<p class="fil-ariane"><a href="/admin/anomalies">Anomalies</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?> · <?= e($anomalie['site']) ?></h1>
        <p class="muted">Signalée le <?= e(date_heure((string) $anomalie['created_at'])) ?> par <?= e($anomalie['prenom']) ?> <?= e($anomalie['nom']) ?><?= $anomalie['point'] ? ' · ' . e($anomalie['point']) : '' ?><?= $anomalie['ronde_id'] ? ' · <a href="/admin/rondes/' . e($anomalie['ronde_id']) . '">voir la ronde</a>' : '' ?></p>
    </div>
    <div class="row"><?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']], $classes[$anomalie['gravite']]) ?> <?= badge(AnomalieRepository::STATUTS[$anomalie['statut']]) ?></div>
</div>

<div class="grid-2-1">
    <div class="stack stack-lg">
        <section class="card">
            <h2 class="card-header">Description</h2>
            <p><?= nl2br(e($anomalie['description'])) ?></p>
            <?php if ($photos !== []): ?>
                <div class="photos-apercu mt-4">
                    <?php foreach ($photos as $url): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener"><img src="<?= e($url) ?>" alt="Photo jointe au signalement" loading="lazy"></a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
        <?php if ($anomalie['commentaire_traitement']): ?>
            <section class="card">
                <h2 class="card-header">Suites données</h2>
                <p><?= nl2br(e($anomalie['commentaire_traitement'])) ?></p>
                <p class="muted small"><?= e($anomalie['traitee_par_nom'] ?? '') ?>, le <?= e(date_heure((string) $anomalie['traitee_le'])) ?></p>
            </section>
        <?php endif ?>
    </div>

    <?php if ($peutTraiter): ?>
        <form class="card stack" method="post" action="/admin/anomalies/<?= e($anomalie['id']) ?>">
            <?= csrf_field() ?>
            <h2 class="card-header">Traitement</h2>
            <div class="field"><label for="t-statut">Statut</label><select class="select" id="t-statut" name="statut">
                <?php foreach (AnomalieRepository::STATUTS as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($anomalie['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
            <div class="field"><label for="t-commentaire">Suites données</label><textarea class="textarea" id="t-commentaire" name="commentaire" rows="5" maxlength="5000"><?= e($anomalie['commentaire_traitement'] ?? '') ?></textarea></div>
            <label class="check"><input type="checkbox" name="visible_client" value="1"<?= coche((bool) $anomalie['visible_client']) ?>> Visible par le client sur son portail</label>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    <?php endif ?>
</div>
