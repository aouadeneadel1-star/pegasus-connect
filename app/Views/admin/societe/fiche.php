<?php
/**
 * @var array<string, mixed> $document
 * @var list<array<string, mixed>> $versions
 * @var array<string, mixed>|null $brouillon
 * @var list<array<string, mixed>> $suivi
 * @var array<string, string> $categories
 */
$this->layout('admin', ['titre' => $document['titre'], 'menu' => 'societe']);
$base = '/admin/societe/' . $document['id'];
$accuses = count(array_filter($suivi, static fn (array $l): bool => $l['accuse_le'] !== null));
?>
<p class="fil-ariane"><a href="/admin/societe">Documents société</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($document['titre']) ?></h1>
        <p class="muted"><?= e($categories[$document['categorie']] ?? '') ?><?= $document['version'] ? ' · version ' . e($document['version']) . ' publiée le ' . e(date_courte((string) $document['publiee_le'])) : ' · jamais publié' ?></p>
    </div>
    <div class="row row-wrap">
        <?php if ($brouillon !== null): ?>
            <form method="post" action="<?= e($base) ?>/publier" data-confirmer="Publier la version <?= e($brouillon['version']) ?> ? Les destinataires seront prévenus<?= $document['accuse_requis'] ? ' et devront confirmer leur lecture' : '' ?>.">
                <?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= icone('send', 'icon-sm') ?>Publier la version <?= e($brouillon['version']) ?></button>
            </form>
        <?php endif ?>
        <?php if ($document['statut'] !== 'archive'): ?>
            <form method="post" action="<?= e($base) ?>/archiver" data-confirmer="Archiver ce document ? Il ne sera plus visible des agents.">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Archiver</button>
            </form>
        <?php endif ?>
    </div>
</div>

<div class="grid-2-1">
    <div class="stack stack-lg">
        <?php if ($document['version_courante_id'] !== null && $document['accuse_requis']): ?>
            <section class="card">
                <div class="row row-between">
                    <h2 class="card-header">Accusés de lecture : <?= e($accuses) ?> / <?= e(count($suivi)) ?></h2>
                    <?php if ($accuses < count($suivi)): ?>
                        <form method="post" action="<?= e($base) ?>/relancer"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= icone('bell', 'icon-sm') ?>Relancer</button></form>
                    <?php endif ?>
                </div>
                <progress class="meter" max="<?= e(max(1, count($suivi))) ?>" value="<?= e($accuses) ?>"></progress>
                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead><tr><th scope="col">Destinataire</th><th scope="col">Ouvert</th><th scope="col">Lecture confirmée</th></tr></thead>
                        <tbody>
                            <?php foreach ($suivi as $ligne): ?>
                                <tr>
                                    <td><?= e($ligne['nom_complet']) ?> <span class="mono small muted"><?= e($ligne['matricule'] ?? '') ?></span></td>
                                    <td><?= $ligne['ouvert_le'] ? e(date_heure((string) $ligne['ouvert_le'])) : '<span class="muted">—</span>' ?></td>
                                    <td><?= $ligne['accuse_le'] ? badge(date_heure((string) $ligne['accuse_le']), 'badge-success') : badge('En attente', 'badge-warning') ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif ?>

        <section class="card">
            <h2 class="card-header">Versions</h2>
            <ul class="list">
                <?php foreach ($versions as $version): ?>
                    <li class="list-item">
                        <span class="grow">
                            <a class="list-title" href="<?= e($version['url']) ?>" target="_blank" rel="noopener">Version <?= e($version['version']) ?></a>
                            <span class="list-sub"><?= $version['publiee_le'] ? 'Publiée le ' . e(date_heure((string) $version['publiee_le'])) . ' par ' . e($version['publiee_par_nom'] ?? '') : 'Non publiée' ?><?= $version['note_version'] ? ' · ' . e($version['note_version']) : '' ?></span>
                        </span>
                        <?= (int) $version['id'] === (int) $document['version_courante_id'] ? badge('En vigueur', 'badge-success') : '' ?>
                    </li>
                <?php endforeach ?>
            </ul>
            <?php if ($document['statut'] !== 'archive'): ?>
                <form class="stack mt-4" method="post" action="<?= e($base) ?>/version" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <h3 class="small">Déposer une nouvelle version</h3>
                    <input class="input" name="fichier" type="file" accept="application/pdf" required aria-label="Fichier PDF">
                    <input class="input" name="note_version" maxlength="255" placeholder="Ce qui change" aria-label="Ce qui change">
                    <input class="input" name="date_effet" type="date" aria-label="Date d'effet">
                    <button class="btn btn-outline btn-sm" type="submit"><?= icone('up', 'icon-sm') ?>Déposer</button>
                    <p class="help">À la publication, les accusés de lecture repartent de zéro.</p>
                </form>
            <?php endif ?>
        </section>
    </div>

    <form class="card stack" method="post" action="<?= e($base) ?>">
        <?= csrf_field() ?>
        <h2 class="card-header">Paramètres</h2>
        <?= $this->insert('admin/societe/_ciblage', [...$__data, 'erreurs' => [], 'ancien' => []]) ?>
        <button class="btn btn-primary btn-sm" type="submit">Enregistrer</button>
    </form>
</div>
