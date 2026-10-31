<?php
/**
 * @var array<string, mixed> $ronde
 * @var array<string, mixed>|null $etat
 * @var list<array<string, mixed>> $passages
 * @var list<array<string, mixed>> $anomalies
 */
use App\Core\Clock;
use App\Repositories\AnomalieRepository;

$this->layout('admin', ['titre' => 'Ronde', 'menu' => 'rondes']);
$libellesIndicateurs = ['hors_zone' => 'Hors zone', 'imprecise' => 'GPS imprécis', 'ordre' => 'Hors ordre', 'trop_rapide' => 'Trop rapide', 'scan_groupe' => 'Scans groupés', 'sync_tardive' => 'Transmis tard'];
?>
<p class="fil-ariane"><a href="/admin/rondes">Rondes</a> ›</p>
<div class="page-head">
    <div>
        <h1><?= e($ronde['modele'] ?? 'Ronde libre') ?> · <?= e($ronde['site']) ?></h1>
        <p class="muted">
            <?= $ronde['prevue_le'] ? 'Prévue le ' . e(date_heure((string) $ronde['prevue_le'])) . ' · ' : '' ?>
            <?= $ronde['demarree_le'] ? 'démarrée à ' . e(heure((string) $ronde['demarree_le'])) : 'non démarrée' ?>
            <?= $ronde['cloturee_le'] ? ', clôturée à ' . e(heure((string) $ronde['cloturee_le'])) . ($ronde['cloture_auto'] ? ' (automatiquement)' : '') : '' ?>
            <?= $ronde['agent_id'] ? ' · ' . e($ronde['prenom'] . ' ' . $ronde['nom']) : '' ?>
        </p>
    </div>
    <?= $this->insert('partials/statut-ronde', ['statut' => (string) $ronde['statut']]) ?>
</div>

<?php if (!empty($ronde['commentaire_cloture'])): ?>
    <div class="alert alert-info"><?= icone('msg') ?><span class="alert-body"><b>Commentaire de fin de ronde :</b> <?= e($ronde['commentaire_cloture']) ?></span></div>
<?php endif ?>

<div class="grid-2">
    <section class="card">
        <h2 class="card-header">Points prévus<?= $etat === null ? '' : ' (' . e($etat['nb_controles']) . ' / ' . e($etat['nb_prevus']) . ')' ?></h2>
        <?php if ($etat === null): ?>
            <p class="muted">La liste des points est figée au démarrage de la ronde.</p>
        <?php else: ?>
            <ol class="list">
                <?php foreach ($etat['points'] as $point): ?>
                    <li class="list-item">
                        <span class="list-icon<?= $point['controle'] ? ' success' : ($point['obligatoire'] ? ' danger' : '') ?>"><?= icone($point['controle'] ? 'check' : 'x') ?></span>
                        <span class="grow"><span class="list-title"><?= e($point['ordre']) ?>. <?= e($point['nom']) ?></span><span class="list-sub"><?= e($point['zone'] ?? '') ?><?= $point['obligatoire'] ? '' : ' · facultatif' ?></span></span>
                        <span class="tnum small"><?= e($point['heure'] ?? '') ?></span>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">Passages enregistrés</h2>
        <?php if ($passages === []): ?>
            <p class="muted">Aucun passage.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Heure</th><th scope="col">Point</th><th scope="col">Distance</th><th scope="col">Contrôles</th></tr></thead>
                    <tbody>
                        <?php foreach ($passages as $passage): ?>
                            <tr>
                                <td class="tnum"><?= e(Clock::fromSql((string) $passage['horodatage'])->setTimezone(Clock::local())->format('H:i:s')) ?><?= $passage['hors_ligne'] ? ' ' . icone('wifi-off', 'icon-sm') : '' ?></td>
                                <td><?= e($passage['point']) ?> <span class="mono small muted"><?= e($passage['identifiant']) ?></span></td>
                                <td class="tnum"><?= $passage['distance_point_m'] === null ? '—' : e($passage['distance_point_m']) . ' m' ?></td>
                                <td><?php foreach (explode(',', (string) $passage['indicateurs']) as $code): ?><?= $code === '' ? '' : badge($libellesIndicateurs[$code] ?? $code, 'badge-warning') ?> <?php endforeach ?><?= $passage['statut'] === 'valide' ? '' : badge($passage['statut'] === 'a_verifier' ? 'À vérifier' : 'Rejeté', 'badge-warning') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
</div>

<?php if ($anomalies !== []): ?>
    <section class="card mt-5">
        <h2 class="card-header">Anomalies signalées pendant la ronde</h2>
        <ul class="list">
            <?php foreach ($anomalies as $anomalie): ?>
                <li><a class="list-item" href="/admin/anomalies/<?= e($anomalie['id']) ?>"><span class="grow"><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?><?= $anomalie['point'] ? ' · ' . e($anomalie['point']) : '' ?></span><?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']]) ?></a></li>
            <?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
