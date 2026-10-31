<?php
/**
 * @var list<array<string, mixed>> $echeances
 * @var int $horizon
 * @var list<int> $horizons
 */
$this->layout('admin', ['titre' => 'Échéances', 'menu' => 'echeances']);
?>
<div class="page-head">
    <h1>Échéances de conformité</h1>
    <a class="btn btn-outline btn-sm" href="/admin/echeances/export.csv"><?= icone('dl', 'icon-sm') ?>Exporter (90 jours)</a>
</div>

<nav class="tabs" aria-label="Horizon">
    <?php foreach ($horizons as $jours): ?>
        <a href="/admin/echeances?jours=<?= e($jours) ?>"<?= $horizon === $jours ? ' aria-current="page"' : '' ?>>Sous <?= e($jours) ?> jours</a>
    <?php endforeach ?>
</nav>

<?php if ($echeances === []): ?>
    <div class="card empty">Aucune échéance dans les <?= e($horizon) ?> prochains jours.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Agent</th><th scope="col">Matricule</th><th scope="col">Objet</th><th scope="col">Échéance</th><th scope="col">Statut</th></tr></thead>
            <tbody>
                <?php foreach ($echeances as $echeance): ?>
                    <tr>
                        <td><a href="/admin/agents/<?= e($echeance['agent_id']) ?>?onglet=conformite"><?= e($echeance['nom']) ?> <?= e($echeance['prenom']) ?></a></td>
                        <td class="mono"><?= e($echeance['matricule']) ?></td>
                        <td><?= e($echeance['libelle']) ?></td>
                        <td><?= e(date_courte((string) $echeance['echeance'])) ?></td>
                        <td><?= badge_echeance($echeance['statut']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
