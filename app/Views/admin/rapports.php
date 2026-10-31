<?php
/**
 * @var int $annee
 * @var int $mois
 * @var list<array<string, mixed>> $agents
 * @var array<int, array<string, mixed>> $rapports
 * @var bool $peutGerer
 */
$this->layout('admin', ['titre' => 'Rapports mensuels', 'menu' => 'rapports']);
$periode = sprintf('%04d-%02d', $annee, $mois);
$debut = new DateTimeImmutable($periode . '-01');
$statuts = ['en_file' => ['En cours', 'badge-info'], 'genere' => ['Généré', 'badge-warning'], 'envoye' => ['Envoyé', 'badge-success'], 'erreur' => ['Erreur', 'badge-danger']];
?>
<div class="page-head">
    <div>
        <h1>Rapports mensuels</h1>
        <p class="muted"><?= e(ucfirst(nom_mois($mois))) ?> <?= e($annee) ?></p>
    </div>
    <div class="row">
        <a class="btn btn-ghost btn-icon btn-sm" href="/admin/rapports?mois=<?= e($debut->modify('-1 month')->format('Y-m')) ?>" aria-label="Mois précédent"><?= icone('back', 'icon-sm') ?></a>
        <a class="btn btn-ghost btn-icon btn-sm" href="/admin/rapports?mois=<?= e($debut->modify('+1 month')->format('Y-m')) ?>" aria-label="Mois suivant"><?= icone('next', 'icon-sm') ?></a>
    </div>
</div>
<p class="help">Chaque rapport reprend les heures planifiées et pointées, les majorations, les retards, les absences et les rondes du mois. Une régénération crée une nouvelle version ; un rapport envoyé n'est jamais modifié.</p>

<?php if ($agents === []): ?>
    <div class="card empty">Aucun agent actif dans votre périmètre.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Agent</th><th scope="col">Rapport</th><th scope="col">Heures pointées</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                <?php foreach ($agents as $agent): ?>
                    <?php $rapport = $rapports[(int) $agent['id']] ?? null; ?>
                    <tr>
                        <td><?= e($agent['nom']) ?> <?= e($agent['prenom']) ?> <span class="mono small muted"><?= e($agent['matricule']) ?></span></td>
                        <td>
                            <?php if ($rapport === null): ?>
                                <span class="muted">Non généré</span>
                            <?php else: ?>
                                <?= badge(...$statuts[$rapport['statut']]) ?> <span class="small muted">v<?= e($rapport['version']) ?> · <?= e(date_heure((string) ($rapport['genere_le'] ?? $rapport['envoye_le']))) ?></span>
                            <?php endif ?>
                        </td>
                        <td class="tnum"><?= $rapport === null ? '—' : e(heures_decimales((int) $rapport['minutes_pointees'])) ?></td>
                        <td class="row row-end">
                            <?php if ($rapport !== null && $rapport['document_id'] !== null): ?>
                                <a class="btn btn-ghost btn-sm" href="/admin/rapports/<?= e($rapport['id']) ?>/telecharger" target="_blank" rel="noopener"><?= icone('file', 'icon-sm') ?>Voir</a>
                            <?php endif ?>
                            <?php if ($peutGerer): ?>
                                <form method="post" action="/admin/rapports">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="agent_id" value="<?= e($agent['id']) ?>"><input type="hidden" name="mois" value="<?= e($periode) ?>">
                                    <button class="btn btn-outline btn-sm" type="submit"><?= icone('refresh', 'icon-sm') ?><?= $rapport === null ? 'Générer' : 'Régénérer' ?></button>
                                </form>
                                <?php if ($rapport !== null && $rapport['statut'] === 'genere'): ?>
                                    <form method="post" action="/admin/rapports/<?= e($rapport['id']) ?>/envoyer">
                                        <?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= icone('send', 'icon-sm') ?>Envoyer</button>
                                    </form>
                                <?php endif ?>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
