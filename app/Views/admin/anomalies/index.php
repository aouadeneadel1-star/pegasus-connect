<?php
/**
 * @var list<array<string, mixed>> $anomalies
 * @var array{site: int, statut: string, gravite: string} $filtres
 * @var list<array{id: int, nom: string}> $sites
 */
use App\Repositories\AnomalieRepository;

$this->layout('admin', ['titre' => 'Anomalies', 'menu' => 'anomalies']);
$classes = ['critique' => 'badge-danger', 'majeure' => 'badge-warning', 'mineure' => 'badge-info', 'info' => ''];
?>
<div class="page-head"><h1>Anomalies</h1></div>
<form class="filtres" method="get" action="/admin/anomalies">
    <div class="field"><label for="a-site">Site</label><select class="select select-sm" id="a-site" name="site"><option value="">Tous</option>
        <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"<?= selectionne($filtres['site'] === $site['id']) ?>><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="a-statut">Statut</label><select class="select select-sm" id="a-statut" name="statut">
        <option value="a_traiter"<?= selectionne($filtres['statut'] === 'a_traiter') ?>>À traiter</option>
        <option value="tous"<?= selectionne($filtres['statut'] === 'tous') ?>>Toutes</option>
        <?php foreach (AnomalieRepository::STATUTS as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="a-gravite">Gravité</label><select class="select select-sm" id="a-gravite" name="gravite"><option value="">Toutes</option>
        <?php foreach (AnomalieRepository::GRAVITES as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['gravite'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>
<?php if ($anomalies === []): ?>
    <div class="card empty">Aucune anomalie.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Date</th><th scope="col">Site</th><th scope="col">Catégorie</th><th scope="col">Gravité</th><th scope="col">Signalée par</th><th scope="col">Statut</th></tr></thead>
            <tbody>
                <?php foreach ($anomalies as $anomalie): ?>
                    <tr>
                        <td class="tnum"><a href="/admin/anomalies/<?= e($anomalie['id']) ?>"><?= e(date_heure((string) $anomalie['created_at'])) ?></a></td>
                        <td><?= e($anomalie['site']) ?><?= $anomalie['point'] ? '<br><span class="muted small">' . e($anomalie['point']) . '</span>' : '' ?></td>
                        <td><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?><?= (int) $anomalie['nb_photos'] > 0 ? ' ' . icone('camera', 'icon-sm') : '' ?></td>
                        <td><?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']], $classes[$anomalie['gravite']]) ?></td>
                        <td><?= e($anomalie['prenom']) ?> <?= e($anomalie['nom']) ?></td>
                        <td><?= badge(AnomalieRepository::STATUTS[$anomalie['statut']], in_array($anomalie['statut'], ['ouverte', 'en_cours'], true) ? 'badge-warning' : 'badge-success') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
