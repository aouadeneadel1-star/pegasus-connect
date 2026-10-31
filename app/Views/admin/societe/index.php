<?php
/**
 * @var list<array<string, mixed>> $documents
 * @var string $statut
 * @var array<string, string> $categories
 */
$this->layout('admin', ['titre' => 'Documents société', 'menu' => 'societe']);
$statuts = ['brouillon' => ['Brouillon', 'badge-info'], 'publie' => ['Publié', 'badge-success'], 'archive' => ['Archivé', '']];
?>
<div class="page-head">
    <h1>Documents de la société</h1>
    <a class="btn btn-primary btn-sm" href="/admin/societe/nouveau"><?= icone('plus', 'icon-sm') ?>Nouveau document</a>
</div>
<nav class="tabs" aria-label="Statut">
    <a href="/admin/societe"<?= $statut === '' ? ' aria-current="page"' : '' ?>>En cours</a>
    <a href="/admin/societe?statut=archive"<?= $statut === 'archive' ? ' aria-current="page"' : '' ?>>Archivés</a>
</nav>
<?php if ($documents === []): ?>
    <div class="card empty">Aucun document.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Titre</th><th scope="col">Catégorie</th><th scope="col">Version</th><th scope="col">Accusés</th><th scope="col">Statut</th></tr></thead>
            <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td><a href="/admin/societe/<?= e($document['id']) ?>"><?= e($document['titre']) ?></a></td>
                        <td><?= e($categories[$document['categorie']] ?? '') ?></td>
                        <td><?= $document['version'] === null ? '—' : 'v' . e($document['version']) . ' · ' . e(date_courte((string) $document['publiee_le'])) ?></td>
                        <td class="tnum"><?= $document['accuse_requis'] ? e($document['nb_accuses']) : '<span class="muted">non requis</span>' ?></td>
                        <td><?= badge(...$statuts[$document['statut']]) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
