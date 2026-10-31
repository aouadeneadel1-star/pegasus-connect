<?php
/**
 * @var list<array<string, mixed>> $documents
 * @var array<string, string> $categories
 * @var list<array<string, mixed>> $societe
 * @var array<string, string> $categoriesSociete
 * @var int $fichesNonConsultees
 */
$this->layout('agent', ['titre' => 'Documents', 'onglet' => 'documents']);
$parCategorie = [];
foreach ($documents as $document) {
    $parCategorie[$document['categorie']][] = $document;
}
?>
<a class="card row" href="/paie">
    <span class="list-icon brand"><?= icone('euro') ?></span>
    <span class="grow"><b>Mes bulletins de paie</b><?php if ($fichesNonConsultees > 0): ?><br><span class="small"><?= badge($fichesNonConsultees . ' nouveau(x)', 'badge-brand') ?></span><?php endif ?></span>
    <?= icone('next') ?>
</a>

<h2 class="list-heading" id="societe">Documents de la société</h2>
<?php if ($societe === []): ?>
    <div class="card empty">Aucun document publié pour vous.</div>
<?php else: ?>
    <ul class="list card">
        <?php foreach ($societe as $doc): ?>
            <?php $aLire = (bool) $doc['accuse_requis'] && $doc['accuse_le'] === null; ?>
            <li>
                <a class="list-item" href="/societe/<?= e($doc['version_id']) ?>">
                    <span class="list-icon<?= $aLire ? ' warning' : '' ?>"><?= icone('file') ?></span>
                    <span class="grow">
                        <span class="list-title"><?= e($doc['titre']) ?></span>
                        <span class="list-sub"><?= e($categoriesSociete[$doc['categorie']] ?? '') ?> · version <?= e($doc['version']) ?> du <?= e(date_courte((string) $doc['publiee_le'])) ?></span>
                    </span>
                    <?php if ($aLire): ?><?= badge('À lire', 'badge-warning') ?><?php elseif ($doc['accuse_le'] !== null): ?><?= badge('Lu', 'badge-success') ?><?php endif ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<h2 class="list-heading mt-5">Mes documents</h2>
<?php if ($documents === []): ?>
    <div class="card empty">Aucun document disponible.</div>
<?php else: ?>
    <?php foreach ($parCategorie as $categorie => $liste): ?>
        <h3 class="small muted mt-4"><?= e($categories[$categorie] ?? $categorie) ?></h3>
        <ul class="list card">
            <?php foreach ($liste as $document): ?>
                <li>
                    <a class="list-item" href="<?= e($document['url']) ?>" target="_blank" rel="noopener">
                        <span class="list-icon"><?= icone($document['mime'] === 'application/pdf' ? 'file' : 'camera') ?></span>
                        <span class="grow"><span class="list-title"><?= e($document['libelle']) ?></span>
                            <span class="list-sub"><?= e(date_courte((string) ($document['date_document'] ?? $document['created_at']))) ?> · <?= e(taille_fichier((int) $document['taille_octets'])) ?></span></span>
                        <?= icone('dl', 'icon-sm') ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endforeach ?>
<?php endif ?>
