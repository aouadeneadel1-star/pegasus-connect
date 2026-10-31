<?php
/**
 * @var array<string, mixed> $import
 * @var list<array<string, mixed>> $lignes
 * @var list<array<string, mixed>> $fiches
 * @var list<array{id: int, nom: string}> $agents
 */
$this->layout('admin', ['titre' => 'Import de paie', 'menu' => 'paie']);
$modifiable = in_array($import['statut'], ['analyse', 'a_valider'], true);
$statuts = [
    'associe' => ['Rapproché', 'badge-success'], 'ambigu' => ['Ambigu', 'badge-warning'], 'introuvable' => ['Introuvable', 'badge-danger'],
    'doublon' => ['Doublon', 'badge-warning'], 'invalide' => ['Refusé', 'badge-danger'], 'deja_publie' => ['Déjà publié', 'badge-info'],
];
$associes = count(array_filter($lignes, static fn (array $l): bool => $l['statut'] === 'associe'));
?>
<p class="fil-ariane"><a href="/admin/paie">Paie</a> ›</p>
<div class="page-head">
    <div>
        <h1>Bulletins de <?= e(nom_mois((int) $import['mois'])) ?> <?= e($import['annee']) ?></h1>
        <p class="muted">Import du <?= e(date_heure((string) $import['created_at'])) ?> par <?= e($import['cree_par_nom']) ?></p>
    </div>
    <?php if ($modifiable): ?>
        <div class="row">
            <form method="post" action="/admin/paie/import/<?= e($import['id']) ?>/annuler" data-confirmer="Annuler cet import ? Les fichiers déposés seront supprimés.">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Annuler l'import</button>
            </form>
            <form method="post" action="/admin/paie/import/<?= e($import['id']) ?>/publier" data-confirmer="Publier <?= e($associes) ?> bulletin(s) ? Chaque agent sera prévenu.">
                <?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"<?= $associes === 0 ? ' disabled' : '' ?>><?= icone('send', 'icon-sm') ?>Publier <?= e($associes) ?> bulletin(s)</button>
            </form>
        </div>
    <?php endif ?>
</div>

<?php if ($modifiable && $associes < count($lignes)): ?>
    <div class="alert alert-warning"><?= icone('alert') ?><span class="alert-body">Seuls les bulletins rapprochés seront publiés. Associez les autres à la main ou laissez-les de côté.</span></div>
<?php endif ?>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th scope="col">Fichier</th><th scope="col">Agent</th><th scope="col">Statut</th><?php if ($modifiable): ?><th scope="col">Association</th><?php endif ?></tr></thead>
        <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <?php [$libelle, $classe] = $statuts[$ligne['statut']] ?? [$ligne['statut'], '']; ?>
                <tr>
                    <td><?php if ($ligne['url']): ?><a href="<?= e($ligne['url']) ?>" target="_blank" rel="noopener"><?= e($ligne['nom_fichier']) ?></a><?php else: ?><?= e($ligne['nom_fichier']) ?><?php endif ?></td>
                    <td><?= $ligne['agent_id'] ? e($ligne['nom'] . ' ' . $ligne['prenom']) . ' <span class="mono small muted">' . e($ligne['matricule']) . '</span>' . ($ligne['methode'] === 'nom' ? ' ' . badge('par le nom') : '') : '—' ?></td>
                    <td><?= badge($libelle, $classe) ?><?= $ligne['message'] ? '<br><span class="small muted">' . e($ligne['message']) . '</span>' : '' ?></td>
                    <?php if ($modifiable): ?>
                        <td>
                            <?php if ($ligne['document_id'] !== null && $ligne['statut'] !== 'associe'): ?>
                                <form class="row" method="post" action="/admin/paie/import/<?= e($import['id']) ?>/associer">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ligne_id" value="<?= e($ligne['id']) ?>">
                                    <label class="sr-only" for="ag-<?= e($ligne['id']) ?>">Agent</label>
                                    <select class="select select-sm" id="ag-<?= e($ligne['id']) ?>" name="agent_id" required>
                                        <option value="">Choisir…</option>
                                        <?php foreach ($agents as $agent): ?><option value="<?= e($agent['id']) ?>"<?= selectionne((int) $ligne['agent_id'] === $agent['id']) ?>><?= e($agent['nom']) ?></option><?php endforeach ?>
                                    </select>
                                    <button class="btn btn-outline btn-sm" type="submit">Associer</button>
                                </form>
                            <?php endif ?>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?php if ($fiches !== []): ?>
    <section class="card mt-5">
        <h2 class="card-header">Bulletins publiés</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">Agent</th><th scope="col">Statut</th><th scope="col">Consulté</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <?php foreach ($fiches as $fiche): ?>
                        <tr>
                            <td><?= e($fiche['nom']) ?> <?= e($fiche['prenom']) ?></td>
                            <td><?= badge(['publiee' => 'Publié', 'depubliee' => 'Retiré', 'remplacee' => 'Remplacé'][$fiche['statut']] ?? $fiche['statut']) ?></td>
                            <td><?= $fiche['premiere_consultation'] ? e(date_heure((string) $fiche['premiere_consultation'])) : '<span class="muted">Non</span>' ?></td>
                            <td>
                                <?php if ($fiche['statut'] === 'publiee'): ?>
                                    <form class="row" method="post" action="/admin/paie/fiches/<?= e($fiche['id']) ?>/depublier" data-confirmer="Retirer ce bulletin de l'espace de l'agent ?">
                                        <?= csrf_field() ?>
                                        <input class="input input-sm" name="motif" maxlength="200" placeholder="Motif" required aria-label="Motif du retrait">
                                        <button class="btn btn-ghost btn-sm" type="submit">Retirer</button>
                                    </form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>
