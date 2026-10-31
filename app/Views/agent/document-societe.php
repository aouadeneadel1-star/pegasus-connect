<?php
/**
 * @var array<string, mixed> $version
 * @var string $url
 * @var ?string $accuseLe
 */
$this->layout('agent', ['titre' => $version['titre'], 'onglet' => 'documents', 'retour' => '/documents']);
?>
<section class="card stack stack-sm">
    <p class="muted small">Version <?= e($version['version']) ?> publiée le <?= e(date_courte((string) $version['publiee_le'])) ?><?= $version['date_effet'] ? ', en vigueur le ' . e(date_courte((string) $version['date_effet'])) : '' ?></p>
    <?php if (!empty($version['description'])): ?><p><?= nl2br(e($version['description'])) ?></p><?php endif ?>
    <?php if (!empty($version['note_version'])): ?><p class="small"><b>Ce qui change :</b> <?= e($version['note_version']) ?></p><?php endif ?>
    <a class="btn btn-outline" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= icone('file') ?>Ouvrir le document</a>
</section>

<?php if ((bool) $version['accuse_requis']): ?>
    <?php if ($accuseLe === null): ?>
        <form class="card stack" method="post" action="/societe/<?= e($version['id']) ?>/accuse">
            <?= csrf_field() ?>
            <label class="check"><input type="checkbox" required> J'ai lu ce document et j'en ai pris connaissance.</label>
            <button class="btn btn-primary" type="submit"><?= icone('check') ?>Confirmer ma lecture</button>
        </form>
    <?php else: ?>
        <div class="alert alert-success"><?= icone('check') ?><span class="alert-body">Lecture confirmée le <?= e(date_heure($accuseLe)) ?>.</span></div>
    <?php endif ?>
<?php endif ?>
