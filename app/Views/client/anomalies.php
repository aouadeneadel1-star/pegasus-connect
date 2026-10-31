<?php
/**
 * @var list<array<string, mixed>> $anomalies
 * @var array{site: int, statut: string, gravite: string} $filtres
 * @var list<array{id: int, nom: string}> $sites
 */
use App\Repositories\AnomalieRepository;

$this->layout('client', ['titre' => 'Anomalies', 'menu' => 'anomalies']);
$classes = ['critique' => 'badge-danger', 'majeure' => 'badge-warning', 'mineure' => 'badge-info', 'info' => ''];
?>
<div class="page-head"><h1>Anomalies</h1></div>
<form class="filtres" method="get" action="/client/anomalies">
    <div class="field"><label for="ca-site">Site</label><select class="select select-sm" id="ca-site" name="site"><option value="">Tous</option>
        <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>"<?= selectionne($filtres['site'] === $site['id']) ?>><?= e($site['nom']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="ca-statut">Statut</label><select class="select select-sm" id="ca-statut" name="statut"><option value="">Tous</option>
        <?php foreach (AnomalieRepository::STATUTS as $code => $libelle): ?><option value="<?= e($code) ?>"<?= selectionne($filtres['statut'] === $code) ?>><?= e($libelle) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= icone('filter', 'icon-sm') ?>Filtrer</button>
</form>
<?php if ($anomalies === []): ?>
    <div class="card empty">Aucune anomalie.</div>
<?php else: ?>
    <ul class="list card">
        <?php foreach ($anomalies as $anomalie): ?>
            <li><a class="list-item" href="/client/anomalies/<?= e($anomalie['id']) ?>">
                <span class="grow"><span class="list-title"><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?> · <?= e($anomalie['site']) ?></span><span class="list-sub"><?= e(date_heure((string) $anomalie['created_at'])) ?><?= $anomalie['point'] ? ' · ' . e($anomalie['point']) : '' ?></span></span>
                <?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']], $classes[$anomalie['gravite']]) ?> <?= badge(AnomalieRepository::STATUTS[$anomalie['statut']]) ?>
            </a></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
