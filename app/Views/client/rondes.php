<?php
/**
 * @var list<array<string, mixed>> $rondes
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string, scalar> $filtres
 * @var list<array{id: int, nom: string}> $sites
 * @var bool $nomsAgents
 */
$this->layout('client', ['titre' => 'Rondes', 'menu' => 'rondes']);
?>
<div class="page-head"><h1>Rondes</h1></div>
<form class="filtres" method="get" action="/client/rondes">
    <div class="field"><label for="c-du">Du</label><input class="input input-sm" id="c-du" name="du" type="date" value="<?= e($filtres['du']) ?>"></div>
    <div class="field"><label for="c-au">Au</label><input class="input input-sm" id="c-au" name="au" type="date" value="<?= e($filtres['au']) ?>"></div>
    <div class="field"><label for="c-site">Site</label><select class="select select-sm" id="c-site" name="site"><option value="">Tous</option>
        <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"<?= selectionne((int) $filtres['site'] === $site['id']) ?>><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Afficher</button>
</form>
<?php if ($rondes === []): ?>
    <div class="card empty">Aucune ronde sur la période.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Date</th><th scope="col">Site</th><th scope="col">Ronde</th><?php if ($nomsAgents): ?><th scope="col">Agent</th><?php endif ?><th scope="col">Points</th><th scope="col">Statut</th></tr></thead>
            <tbody>
                <?php foreach ($rondes as $ronde): ?>
                    <tr>
                        <td class="tnum"><a href="/client/rondes/<?= e($ronde['id']) ?>"><?= e(date_heure((string) ($ronde['demarree_le'] ?? $ronde['prevue_le']))) ?></a></td>
                        <td><?= e($ronde['site']) ?></td>
                        <td><?= e($ronde['modele'] ?? 'Ronde libre') ?></td>
                        <?php if ($nomsAgents): ?><td><?= e(trim(($ronde['prenom'] ?? '') . ' ' . mb_substr((string) ($ronde['nom'] ?? ''), 0, 1))) ?></td><?php endif ?>
                        <td class="tnum"><?= e($ronde['nb_points_controles']) ?> / <?= e($ronde['nb_points_prevus']) ?></td>
                        <td><?= $this->insert('partials/statut-ronde', ['statut' => (string) $ronde['statut']]) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?= $this->insert('partials/pagination', ['chemin' => '/client/rondes', 'filtres' => $filtres, 'page' => $page, 'pages' => $pages, 'total' => $total]) ?>
<?php endif ?>
