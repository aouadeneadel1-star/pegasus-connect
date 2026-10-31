<?php
/**
 * @var list<array<string, mixed>> $sites
 * @var list<array{id: int, nom: string}> $clients
 * @var array{q: string, client: int, actif: string} $filtres
 * @var bool $peutGerer
 */
$this->layout('admin', ['titre' => 'Sites', 'menu' => 'sites']);
?>
<div class="page-head">
    <h1>Sites <span class="muted">(<?= e(count($sites)) ?>)</span></h1>
    <?php if ($peutGerer): ?><a class="btn btn-primary btn-sm" href="/admin/sites/nouveau"><?= icone('plus', 'icon-sm') ?>Nouveau site</a><?php endif ?>
</div>

<form class="filtres" method="get" action="/admin/sites">
    <div class="field"><label for="s-q">Recherche</label><input class="input input-sm" id="s-q" name="q" type="search" value="<?= e($filtres['q']) ?>" placeholder="Nom, ville, code"></div>
    <div class="field"><label for="s-client">Client</label>
        <select class="select select-sm" id="s-client" name="client"><option value="">Tous</option>
            <?php foreach ($clients as $client): ?><option value="<?= e($client['id']) ?>"<?= selectionne($filtres['client'] === $client['id']) ?>><?= e($client['nom']) ?></option><?php endforeach ?>
        </select></div>
    <label class="check"><input type="checkbox" name="actif" value="tous"<?= coche($filtres['actif'] === 'tous') ?>> Inclure les sites inactifs</label>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>

<?php if ($sites === []): ?>
    <div class="card empty">Aucun site.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Site</th><th scope="col">Client</th><th scope="col">Ville</th><th scope="col">Pointage</th><th scope="col">Bornes</th><th scope="col">Points</th></tr></thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                    <tr>
                        <td><a href="/admin/sites/<?= e($site['id']) ?>"><?= e($site['nom']) ?></a> <span class="muted mono small"><?= e($site['code']) ?></span><?= $site['actif'] ? '' : ' ' . badge('Inactif') ?></td>
                        <td><?= e($site['client']) ?></td>
                        <td><?= e($site['ville']) ?></td>
                        <td><?= $site['mode_pointage'] === 'dynamique' ? 'Borne' : 'QR fixe' ?><?= $site['badge_nfc_actif'] ? ' + badge' : '' ?></td>
                        <td class="tnum"><?= e($site['nb_bornes']) ?></td>
                        <td class="tnum"><?= e($site['nb_points']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
