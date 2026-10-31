<?php
/**
 * @var array<string, mixed> $agent
 * @var list<array<string, mixed>> $documents
 * @var bool $peutDeposer
 * @var array<string, string> $categories
 */
$base = '/admin/agents/' . $agent['id'];
?>
<?php if (!$peutDeposer): ?>
    <div class="card empty">Les documents RH de l'agent ne sont consultables que par le service RH.</div>
<?php else: ?>
<div class="grid-2-1">
    <section class="card">
        <h2 class="card-header">Documents de l'agent</h2>
        <?php if ($documents === []): ?>
            <p class="muted">Aucun document déposé.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($documents as $document): ?>
                    <li class="list-item">
                        <span class="list-icon"><?= icone($document['mime'] === 'application/pdf' ? 'file' : 'camera') ?></span>
                        <span class="grow">
                            <a class="list-title" href="<?= e($document['url']) ?>" target="_blank" rel="noopener"><?= e($document['libelle']) ?></a>
                            <span class="list-sub">
                                <?= e($categories[$document['categorie']] ?? $document['categorie']) ?>
                                · <?= e(taille_fichier((int) $document['taille_octets'])) ?>
                                · déposé le <?= e(date_courte((string) $document['created_at'])) ?>
                                <?= $document['date_validite'] === null ? '' : ' · valable jusqu\'au ' . e(date_courte((string) $document['date_validite'])) ?>
                                <?= $document['visible_agent'] ? '' : ' · ' . badge('Non visible par l\'agent') ?>
                            </span>
                        </span>
                        <form method="post" action="<?= e($base) ?>/documents/<?= e($document['id']) ?>/supprimer" data-confirmer="Retirer « <?= e($document['libelle']) ?> » ? Le fichier sera effacé à l'issue de la durée de conservation.">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-icon btn-sm" type="submit" aria-label="Retirer <?= e($document['libelle']) ?>"><?= icone('trash', 'icon-sm') ?></button>
                        </form>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">Déposer un document</h2>
        <form class="stack" method="post" action="<?= e($base) ?>/documents" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
                <label for="d-categorie">Catégorie *</label>
                <select class="select" id="d-categorie" name="categorie" required>
                    <?php foreach ($categories as $code => $libelle): ?>
                        <option value="<?= e($code) ?>"><?= e($libelle) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label for="d-libelle">Libellé *</label>
                <input class="input" id="d-libelle" name="libelle" required maxlength="150" placeholder="Contrat de travail 2026">
            </div>
            <div class="field">
                <label for="d-date">Date du document</label>
                <input class="input" id="d-date" name="date_document" type="date">
            </div>
            <div class="field">
                <label for="d-validite">Valable jusqu'au</label>
                <input class="input" id="d-validite" name="date_validite" type="date">
            </div>
            <div class="field">
                <label for="d-fichier">Fichier * <span class="muted">(PDF, JPG, PNG, 10 Mo max.)</span></label>
                <input class="input" id="d-fichier" name="fichier" type="file" required accept="application/pdf,image/jpeg,image/png,image/webp">
            </div>
            <label class="check"><input type="checkbox" name="visible_agent" value="1" checked> Visible par l'agent dans son espace</label>
            <button class="btn btn-primary btn-sm" type="submit"><?= icone('up', 'icon-sm') ?>Déposer</button>
        </form>
    </section>
</div>
<?php endif ?>
