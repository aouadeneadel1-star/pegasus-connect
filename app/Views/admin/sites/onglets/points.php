<?php
/**
 * @var array<string, mixed> $site
 * @var bool $peutGerer
 * @var list<array<string, mixed>> $points
 */
$base = '/admin/sites/' . $site['id'];
$actifs = array_filter($points, static fn (array $p): bool => (bool) $p['actif']);
?>
<div class="grid-2-1">
    <section class="card">
        <div class="row row-between">
            <h2 class="card-header">Points de contrôle (<?= e(count($actifs)) ?> actifs)</h2>
            <?php if ($peutGerer && $actifs !== []): ?>
                <a class="btn btn-outline btn-sm" href="<?= e($base) ?>/etiquettes.pdf"><?= icone('print', 'icon-sm') ?>Imprimer toutes les étiquettes</a>
            <?php endif ?>
        </div>
        <?php if ($points === []): ?>
            <p class="muted">Aucun point de contrôle.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Ordre</th><th scope="col">Point</th><th scope="col">Zone</th><th scope="col">Dernier passage</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($points as $point): ?>
                            <tr>
                                <td class="tnum"><?= e($point['ordre']) ?></td>
                                <td><a href="/admin/points/<?= e($point['id']) ?>"><?= e($point['nom']) ?></a> <span class="mono small muted"><?= e($point['identifiant']) ?></span><?= $point['actif'] ? '' : ' ' . badge('Désactivé') ?></td>
                                <td><?= e($point['zone'] ?? '—') ?></td>
                                <td><?= $point['dernier_passage'] === null ? '<span class="muted">Jamais</span>' : e(date_heure((string) $point['dernier_passage'])) ?></td>
                                <td class="row-end">
                                    <?php if ($peutGerer && $point['actif']): ?>
                                        <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/etiquettes.pdf?points=<?= e($point['id']) ?>" aria-label="Étiquette de <?= e($point['nom']) ?>"><?= icone('print', 'icon-sm') ?></a>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <?php if ($peutGerer): ?>
        <section class="card">
            <h2 class="card-header">Nouveau point</h2>
            <form class="stack" method="post" action="<?= e($base) ?>/points">
                <?= csrf_field() ?>
                <div class="field"><label for="p-nom">Nom *</label><input class="input" id="p-nom" name="nom" maxlength="120" required placeholder="Issue de secours parking"></div>
                <div class="field"><label for="p-zone">Zone / étage</label><input class="input" id="p-zone" name="zone" maxlength="60"></div>
                <div class="field"><label for="p-ordre">Ordre de passage</label><input class="input" id="p-ordre" name="ordre" type="number" min="0" max="999" value="<?= e(count($points) + 1) ?>"></div>
                <div class="field"><label for="p-description">Consigne au point</label><input class="input" id="p-description" name="description" maxlength="255"></div>
                <details>
                    <summary class="small">Position (point en extérieur)</summary>
                    <div class="stack stack-sm mt-2">
                        <div class="field"><label for="p-lat">Latitude</label><input class="input" id="p-lat" name="latitude" inputmode="decimal"></div>
                        <div class="field"><label for="p-lng">Longitude</label><input class="input" id="p-lng" name="longitude" inputmode="decimal"></div>
                        <div class="field"><label for="p-rayon">Rayon (m)</label><input class="input" id="p-rayon" name="rayon_metres" type="number" min="5" max="500"></div>
                        <p class="help">Sans position, le contrôle de distance ne s'applique pas (points en intérieur).</p>
                    </div>
                </details>
                <button class="btn btn-primary btn-sm" type="submit"><?= icone('plus', 'icon-sm') ?>Créer le point</button>
            </form>
        </section>
    <?php endif ?>
</div>
