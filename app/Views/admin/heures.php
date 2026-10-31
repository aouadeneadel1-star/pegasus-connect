<?php
/**
 * @var int $annee
 * @var int $mois
 * @var list<array<string, mixed>> $lignes
 * @var bool $cloture
 * @var bool $peutGerer
 * @var array<string, array<string, mixed>> $regles
 */
$this->layout('admin', ['titre' => 'Heures', 'menu' => 'heures']);
$periode = sprintf('%04d-%02d', $annee, $mois);
$debut = new DateTimeImmutable($periode . '-01');
$somme = static fn (string $cle): int => array_sum(array_map(static fn (array $l): int => (int) $l['totaux'][$cle], $lignes));
?>
<div class="page-head">
    <div>
        <h1>Heures et majorations</h1>
        <p class="muted"><?= e(ucfirst(nom_mois($mois))) ?> <?= e($annee) ?><?= $cloture ? ' · ' . badge('Mois clôturé', 'badge-info') : '' ?></p>
    </div>
    <div class="row row-wrap">
        <a class="btn btn-ghost btn-icon btn-sm" href="/admin/heures?mois=<?= e($debut->modify('-1 month')->format('Y-m')) ?>" aria-label="Mois précédent"><?= icone('back', 'icon-sm') ?></a>
        <a class="btn btn-ghost btn-icon btn-sm" href="/admin/heures?mois=<?= e($debut->modify('+1 month')->format('Y-m')) ?>" aria-label="Mois suivant"><?= icone('next', 'icon-sm') ?></a>
        <a class="btn btn-outline btn-sm" href="/admin/heures/export-paie.csv?mois=<?= e($periode) ?>"><?= icone('dl', 'icon-sm') ?>Variables de paie (CSV)</a>
        <?php if ($peutGerer): ?>
            <form method="post" action="/admin/heures/<?= $cloture ? 'rouvrir' : 'cloturer' ?>" data-confirmer="<?= $cloture ? 'Rouvrir le mois ? Les corrections seront de nouveau possibles.' : 'Clôturer le mois ? Plus aucune correction ne sera possible sur la période.' ?>">
                <?= csrf_field() ?><input type="hidden" name="mois" value="<?= e($periode) ?>">
                <button class="btn btn-<?= $cloture ? 'ghost' : 'secondary' ?> btn-sm" type="submit"><?= icone($cloture ? 'lock' : 'check', 'icon-sm') ?><?= $cloture ? 'Rouvrir' : 'Clôturer le mois' ?></button>
            </form>
        <?php endif ?>
    </div>
</div>

<div class="kpis">
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($somme('travaillees'))) ?></span><span class="kpi-label">Heures travaillées</span></div>
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($somme('planifiees'))) ?></span><span class="kpi-label">Heures planifiées</span></div>
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($somme('nuit'))) ?></span><span class="kpi-label">Nuit (<?= e(rtrim(rtrim((string) ($regles['nuit']['taux'] ?? '10'), '0'), '.')) ?> %)</span></div>
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($somme('hs25') + $somme('hs50'))) ?></span><span class="kpi-label">Heures supplémentaires</span></div>
</div>

<?php if ($lignes === []): ?>
    <div class="card empty">Aucun agent actif dans votre périmètre.</div>
<?php else: ?>
    <div class="table-wrap mt-5">
        <table class="table">
            <thead>
                <tr><th scope="col">Agent</th><th scope="col">Planifiées</th><th scope="col">Travaillées</th><th scope="col">Écart</th><th scope="col">Nuit</th><th scope="col">Dimanche</th><th scope="col">Férié</th><th scope="col">HS 25 %</th><th scope="col">HS 50 %</th><th scope="col">À vérifier</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): ?>
                    <?php $t = $ligne['totaux']; $ecart = $t['travaillees'] - $t['planifiees']; ?>
                    <tr>
                        <td><a href="/admin/agents/<?= e($ligne['id']) ?>?onglet=activite"><?= e($ligne['nom']) ?> <?= e($ligne['prenom']) ?></a> <span class="mono small muted"><?= e($ligne['matricule']) ?></span></td>
                        <td class="tnum"><?= e(heures_decimales($t['planifiees'])) ?></td>
                        <td class="tnum"><?= e(heures_decimales($t['travaillees'])) ?></td>
                        <td class="tnum <?= abs($ecart) > 60 ? ($ecart < 0 ? 'text-danger' : 'text-warning') : '' ?>"><?= $ecart >= 0 ? '+' : '−' ?><?= e(heures_decimales(abs($ecart))) ?></td>
                        <td class="tnum"><?= e(heures_decimales($t['nuit'])) ?></td>
                        <td class="tnum"><?= e(heures_decimales($t['dimanche'])) ?></td>
                        <td class="tnum"><?= e(heures_decimales($t['ferie'])) ?></td>
                        <td class="tnum"><?= e(heures_decimales($t['hs25'])) ?></td>
                        <td class="tnum"><?= e(heures_decimales($t['hs50'])) ?></td>
                        <td><?= $t['a_verifier'] > 0 ? badge((string) $t['a_verifier'], 'badge-warning') : '' ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="help">Heures en décimal (7,50 = 7 h 30). Les services sont rattachés au mois de leur début ; les heures supplémentaires sont calculées par semaine civile et rattachées au mois du dimanche. Les règles appliquées sont celles en vigueur au 1er du mois.</p>
<?php endif ?>
