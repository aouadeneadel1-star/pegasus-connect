<?php
/**
 * @var array<string, mixed> $point
 * @var list<array<string, mixed>> $passages
 * @var string $du
 * @var string $au
 * @var bool $peutGerer
 */
use App\Core\Clock;

$this->layout('admin', ['titre' => $point['nom'], 'menu' => 'sites']);
$statuts = ['valide' => ['Valide', 'badge-success'], 'a_verifier' => ['À vérifier', 'badge-warning'], 'rejete' => ['Rejeté', 'badge-danger']];
?>
<p class="fil-ariane"><a href="/admin/sites">Sites</a> › <a href="/admin/sites/<?= e($point['site_id']) ?>?onglet=points"><?= e($point['site_nom']) ?></a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($point['nom']) ?> <?= $point['actif'] ? '' : badge('Désactivé') ?></h1>
        <p class="muted"><span class="mono"><?= e($point['identifiant']) ?></span> · étiquette version <?= e($point['qr_version']) ?><?= $point['zone'] ? ' · ' . e($point['zone']) : '' ?></p>
    </div>
    <?php if ($peutGerer): ?>
        <div class="row row-wrap">
            <?php if ($point['actif']): ?>
                <a class="btn btn-outline btn-sm" href="/admin/sites/<?= e($point['site_id']) ?>/etiquettes.pdf?points=<?= e($point['id']) ?>"><?= icone('print', 'icon-sm') ?>Étiquette</a>
                <form method="post" action="/admin/points/<?= e($point['id']) ?>/regenerer" data-confirmer="L'étiquette actuelle sera refusée immédiatement. Générer une nouvelle étiquette ?">
                    <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icone('refresh', 'icon-sm') ?>Remplacer l'étiquette</button>
                </form>
            <?php endif ?>
            <form method="post" action="/admin/points/<?= e($point['id']) ?>/actif">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icone('power', 'icon-sm') ?><?= $point['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
            </form>
        </div>
    <?php endif ?>
</div>

<div class="grid-2-1">
    <section class="card">
        <h2 class="card-header">Passages</h2>
        <form class="filtres" method="get">
            <div class="field"><label for="pt-du">Du</label><input class="input input-sm" id="pt-du" type="date" name="du" value="<?= e($du) ?>"></div>
            <div class="field"><label for="pt-au">Au</label><input class="input input-sm" id="pt-au" type="date" name="au" value="<?= e($au) ?>"></div>
            <button class="btn btn-outline btn-sm" type="submit">Afficher</button>
        </form>
        <?php if ($passages === []): ?>
            <p class="muted">Aucun passage sur la période.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Date et heure</th><th scope="col">Agent</th><th scope="col">Ronde</th><th scope="col">Statut</th></tr></thead>
                    <tbody>
                        <?php foreach ($passages as $passage): ?>
                            <?php [$libelle, $classe] = $statuts[$passage['statut']] ?? [$passage['statut'], '']; ?>
                            <tr>
                                <td class="tnum"><?= e(Clock::fromSql((string) $passage['horodatage'])->setTimezone(Clock::local())->format('d/m/Y H:i:s')) ?></td>
                                <td><?= e($passage['prenom']) ?> <?= e($passage['nom']) ?></td>
                                <td><a href="/admin/rondes/<?= e($passage['ronde_id']) ?>">n° <?= e($passage['ronde_id']) ?></a></td>
                                <td><?= badge($libelle, $classe) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <?php if ($peutGerer): ?>
        <section class="card">
            <h2 class="card-header">Modifier</h2>
            <form class="stack" method="post" action="/admin/points/<?= e($point['id']) ?>">
                <?= csrf_field() ?>
                <div class="field"><label for="m-nom">Nom *</label><input class="input" id="m-nom" name="nom" value="<?= e($point['nom']) ?>" maxlength="120" required></div>
                <div class="field"><label for="m-zone">Zone</label><input class="input" id="m-zone" name="zone" value="<?= e($point['zone'] ?? '') ?>" maxlength="60"></div>
                <div class="field"><label for="m-ordre">Ordre</label><input class="input" id="m-ordre" name="ordre" type="number" min="0" max="999" value="<?= e($point['ordre']) ?>"></div>
                <div class="field"><label for="m-desc">Consigne</label><input class="input" id="m-desc" name="description" value="<?= e($point['description'] ?? '') ?>" maxlength="255"></div>
                <div class="field"><label for="m-lat">Latitude</label><input class="input" id="m-lat" name="latitude" value="<?= e($point['latitude'] ?? '') ?>" inputmode="decimal"></div>
                <div class="field"><label for="m-lng">Longitude</label><input class="input" id="m-lng" name="longitude" value="<?= e($point['longitude'] ?? '') ?>" inputmode="decimal"></div>
                <div class="field"><label for="m-rayon">Rayon (m)</label><input class="input" id="m-rayon" name="rayon_metres" type="number" min="5" max="500" value="<?= e($point['rayon_metres'] ?? '') ?>"></div>
                <button class="btn btn-primary btn-sm" type="submit">Enregistrer</button>
            </form>
        </section>
    <?php endif ?>
</div>
