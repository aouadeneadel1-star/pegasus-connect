<?php
/**
 * @var array<string, mixed> $site
 * @var bool $peutGerer
 * @var list<array<string, mixed>> $modeles
 */
$base = '/admin/sites/' . $site['id'];
?>
<section class="card">
    <div class="row row-between">
        <h2 class="card-header">Rondes planifiées</h2>
        <?php if ($peutGerer): ?><a class="btn btn-primary btn-sm" href="<?= e($base) ?>/modeles-ronde/nouveau"><?= icone('plus', 'icon-sm') ?>Nouveau modèle</a><?php endif ?>
    </div>
    <?php if ($modeles === []): ?>
        <p class="muted">Aucune ronde planifiée. Les agents peuvent faire des rondes libres sur tous les points du site.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">Modèle</th><th scope="col">Plage</th><th scope="col">Fréquence</th><th scope="col">Jours</th><th scope="col">Points</th><th scope="col">Durée max.</th></tr></thead>
                <tbody>
                    <?php foreach ($modeles as $modele): ?>
                        <tr>
                            <td><?php if ($peutGerer): ?><a href="/admin/modeles-ronde/<?= e($modele['id']) ?>"><?= e($modele['nom']) ?></a><?php else: ?><?= e($modele['nom']) ?><?php endif ?><?= $modele['actif'] ? '' : ' ' . badge('Inactif') ?><?= $modele['ordre_impose'] ? ' ' . badge('Ordre imposé', 'badge-info') : '' ?></td>
                            <td class="tnum"><?= e(substr((string) $modele['heure_debut'], 0, 5)) ?> – <?= e(substr((string) $modele['heure_fin'], 0, 5)) ?></td>
                            <td>toutes les <?= e(duree((int) $modele['frequence_minutes'])) ?></td>
                            <td><?= e($modele['jours']) ?></td>
                            <td class="tnum"><?= e($modele['nb_points']) ?></td>
                            <td><?= e(duree((int) $modele['duree_max_minutes'])) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
