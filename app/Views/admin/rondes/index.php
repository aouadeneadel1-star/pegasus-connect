<?php
/**
 * @var list<array<string, mixed>> $rondes
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array{site: int, statut: string, agent: int, du: string, au: string} $filtres
 * @var list<array{id: int, nom: string}> $sites
 */
$this->layout('admin', ['titre' => 'Rondes', 'menu' => 'rondes']);
$statuts = ['a_venir' => 'À venir', 'en_cours' => 'En cours', 'terminee' => 'Terminées', 'incomplete' => 'Incomplètes', 'manquee' => 'Manquées'];
?>
<div class="page-head">
    <h1>Rondes</h1>
    <?php if ($utilisateur->peut('rapports_rondes.exporter')): ?>
        <a class="btn btn-outline btn-sm" href="/admin/rapports-rondes"><?= icone('file', 'icon-sm') ?>Rapport de rondes</a>
    <?php endif ?>
</div>

<form class="filtres" method="get" action="/admin/rondes">
    <div class="field"><label for="r-du">Du</label><input class="input input-sm" id="r-du" name="du" type="date" value="<?= e($filtres['du']) ?>"></div>
    <div class="field"><label for="r-au">Au</label><input class="input input-sm" id="r-au" name="au" type="date" value="<?= e($filtres['au']) ?>"></div>
    <div class="field"><label for="r-site">Site</label><select class="select select-sm" id="r-site" name="site"><option value="">Tous</option>
        <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"<?= selectionne($filtres['site'] === $site['id']) ?>><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="r-statut">Statut</label><select class="select select-sm" id="r-statut" name="statut"><option value="">Tous</option>
        <?php foreach ($statuts as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>

<?php if ($rondes === []): ?>
    <div class="card empty">Aucune ronde sur la période.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Prévue / démarrée</th><th scope="col">Site</th><th scope="col">Ronde</th><th scope="col">Agent</th><th scope="col">Points</th><th scope="col">Statut</th></tr></thead>
            <tbody>
                <?php foreach ($rondes as $ronde): ?>
                    <tr>
                        <td class="tnum"><a href="/admin/rondes/<?= e($ronde['id']) ?>"><?= e(date_heure((string) ($ronde['demarree_le'] ?? $ronde['prevue_le']))) ?></a></td>
                        <td><?= e($ronde['site']) ?></td>
                        <td><?= e($ronde['modele'] ?? 'Ronde libre') ?></td>
                        <td><?= $ronde['agent_id'] === null ? '—' : e($ronde['prenom'] . ' ' . $ronde['nom']) ?></td>
                        <td class="tnum"><?= e($ronde['nb_points_controles']) ?> / <?= e($ronde['nb_points_prevus']) ?></td>
                        <td><?= $this->insert('partials/statut-ronde', ['statut' => (string) $ronde['statut']]) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?= $this->insert('partials/pagination', ['chemin' => '/admin/rondes', 'filtres' => $filtres, 'page' => $page, 'pages' => $pages, 'total' => $total]) ?>
<?php endif ?>
