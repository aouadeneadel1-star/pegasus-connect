<?php

use App\Services\Pointage\PointageService;

/**
 * @var int $annee
 * @var int $mois
 * @var list<array<string, mixed>> $services
 * @var array<string, int> $totaux
 */
$this->layout('agent', ['titre' => 'Historique et heures', 'onglet' => 'plus', 'retour' => '/plus']);
$precedent = (new DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $mois)))->modify('-1 month');
$suivant = (new DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $mois)))->modify('+1 month');
$statuts = ['ouvert' => ['En cours', 'badge-success'], 'clos' => ['', ''], 'depart_manquant' => ['Départ manquant', 'badge-warning'], 'corrige' => ['Corrigé', 'badge-info']];
?>
<nav class="row row-between" aria-label="Mois">
    <a class="btn btn-ghost btn-icon" href="/historique?mois=<?= e($precedent->format('Y-m')) ?>" aria-label="Mois précédent"><?= icone('back') ?></a>
    <h2><?= e(ucfirst(nom_mois($mois))) ?> <?= e($annee) ?></h2>
    <a class="btn btn-ghost btn-icon" href="/historique?mois=<?= e($suivant->format('Y-m')) ?>" aria-label="Mois suivant"><?= icone('next') ?></a>
</nav>

<div class="kpis">
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($totaux['travaillees'])) ?></span><span class="kpi-label">Heures pointées</span></div>
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($totaux['planifiees'])) ?></span><span class="kpi-label">Heures planifiées</span></div>
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($totaux['nuit'])) ?></span><span class="kpi-label">Dont nuit</span></div>
    <div class="kpi"><span class="kpi-value"><?= e(heures_decimales($totaux['dimanche'] + $totaux['ferie'])) ?></span><span class="kpi-label">Dimanche et férié</span></div>
</div>
<p class="help">Ventilation indicative : les heures retenues pour la paie sont validées par le service RH.</p>

<?php if ($services === []): ?>
    <div class="card empty">Aucun service ce mois-ci.</div>
<?php else: ?>
    <ul class="list card">
        <?php foreach ($services as $service): ?>
            <?php [$libelle, $classe] = $statuts[$service['statut']] ?? ['', '']; ?>
            <li class="list-item">
                <span class="grow">
                    <span class="list-title"><?= e(ucfirst(jour_court(date_locale((string) $service['debut'])))) ?> <?= e(date_courte((string) $service['debut'])) ?> · <?= e($service['site']) ?></span>
                    <span class="list-sub tnum">
                        <?= e(heure((string) $service['debut'])) ?> – <?= $service['fin'] === null ? '…' : e(heure((string) $service['fin'])) ?>
                        <?= $service['duree_minutes'] === null ? '' : ' · ' . e(duree((int) $service['duree_minutes'])) ?>
                    </span>
                    <?php foreach (array_intersect($service['indicateurs'], ['retard', 'depart_anticipe', 'hors_planning', 'hors_zone']) as $indicateur): ?>
                        <?= badge(PointageService::libelleIndicateur($indicateur), 'badge-warning') ?>
                    <?php endforeach ?>
                </span>
                <?= $libelle === '' ? '' : badge($libelle, $classe) ?>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
