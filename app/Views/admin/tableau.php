<?php
/**
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var list<array{valeur: int|string, libelle: string, lien: string, etat: string}> $indicateurs
 * @var list<array<string, mixed>> $enService
 * @var list<array<string, mixed>> $aVerifier
 * @var list<array<string, mixed>> $anomalies
 * @var list<array<string, mixed>> $echeances
 * @var list<array<string, mixed>> $absences
 */
use App\Core\Clock;
use App\Repositories\AnomalieRepository;
use App\Services\Agents\Echeance;
use App\Services\Pointage\PointageService;

$absences ??= [];
$this->layout('admin', ['titre' => 'Tableau de bord', 'menu' => 'tableau', 'scripts' => ['rafraichir.js']]);
$classesGravite = ['critique' => 'badge-danger', 'majeure' => 'badge-warning', 'mineure' => 'badge-info', 'info' => ''];
?>
<div class="page-head">
    <div>
        <h1>Bonjour <?= e($utilisateur->prenom()) ?></h1>
        <p class="muted"><?= e(ucfirst(date_longue(Clock::now()->setTimezone(Clock::local())))) ?></p>
    </div>
</div>

<?php if ($indicateurs !== []): ?>
    <div class="kpis kpis-lg" data-rafraichir="60">
        <?php foreach ($indicateurs as $indicateur): ?>
            <a class="kpi" href="<?= e($indicateur['lien']) ?>">
                <span class="kpi-value<?= $indicateur['etat'] === '' ? '' : ' ' . e($indicateur['etat']) ?>"><?= e($indicateur['valeur']) ?></span>
                <span class="kpi-label"><?= e($indicateur['libelle']) ?></span>
            </a>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div class="grid-2 mt-5">
    <?php if ($utilisateur->peut('pointages.suivre')): ?>
        <section class="card">
            <div class="row row-between"><h2 class="card-header">En service</h2><a class="small" href="/admin/pointages?vue=services">Tout voir</a></div>
            <?php if ($enService === []): ?>
                <p class="muted">Aucun agent en service.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach (array_slice($enService, 0, 8) as $service): ?>
                        <li class="list-item">
                            <span class="grow"><span class="list-title"><?= e($service['prenom']) ?> <?= e($service['nom']) ?></span><span class="list-sub"><?= e($service['site']) ?></span></span>
                            <span class="tnum small">depuis <?= e(heure((string) $service['debut'])) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <section class="card">
            <h2 class="card-header">Arrivées non pointées</h2>
            <?php if ($absences === []): ?>
                <p class="muted">Toutes les vacations commencées ont une arrivée.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach (array_slice($absences, 0, 8) as $absence): ?>
                        <li class="list-item">
                            <span class="list-icon danger"><?= icone('alert') ?></span>
                            <span class="grow"><span class="list-title"><?= e($absence['prenom']) ?> <?= e($absence['nom']) ?></span><span class="list-sub"><?= e($absence['site']) ?> · prévu à <?= e(heure((string) $absence['debut'])) ?></span></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <section class="card">
            <div class="row row-between"><h2 class="card-header">Pointages à vérifier</h2><a class="small" href="/admin/pointages?vue=a_verifier">Traiter</a></div>
            <?php if ($aVerifier === []): ?>
                <p class="muted">Rien à vérifier.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($aVerifier as $pointage): ?>
                        <li class="list-item">
                            <span class="grow">
                                <span class="list-title"><?= e($pointage['prenom']) ?> <?= e($pointage['nom']) ?> · <?= $pointage['type'] === 'arrivee' ? 'arrivée' : 'départ' ?></span>
                                <span class="list-sub"><?= e($pointage['site']) ?> · <?= e(date_heure((string) $pointage['horodatage'])) ?> · <?= e(implode(', ', array_map(PointageService::libelleIndicateur(...), PointageService::indicateurs($pointage['indicateurs'])))) ?></span>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    <?php endif ?>

    <?php if ($utilisateur->peut('rondes.suivre')): ?>
        <section class="card">
            <div class="row row-between"><h2 class="card-header">Anomalies à traiter</h2><a class="small" href="/admin/anomalies">Tout voir</a></div>
            <?php if ($anomalies === []): ?>
                <p class="muted">Aucune anomalie ouverte.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($anomalies as $anomalie): ?>
                        <li><a class="list-item" href="/admin/anomalies/<?= e($anomalie['id']) ?>">
                            <span class="grow"><span class="list-title"><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']] ?? '') ?> · <?= e($anomalie['site']) ?></span><span class="list-sub"><?= e(date_heure((string) $anomalie['created_at'])) ?></span></span>
                            <?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']], $classesGravite[$anomalie['gravite']]) ?>
                        </a></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    <?php endif ?>

    <?php if ($utilisateur->peut('echeances.voir')): ?>
        <section class="card">
            <div class="row row-between"><h2 class="card-header">Échéances sous 30 jours</h2><a class="small" href="/admin/echeances">Tout voir</a></div>
            <?php if ($echeances === []): ?>
                <p class="muted">Aucune échéance proche.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($echeances as $echeance): ?>
                        <li><a class="list-item" href="/admin/agents/<?= e($echeance['agent_id']) ?>?onglet=conformite">
                            <span class="grow"><span class="list-title"><?= e($echeance['prenom']) ?> <?= e($echeance['nom']) ?></span><span class="list-sub"><?= e($echeance['libelle']) ?> · <?= e(date_courte((string) $echeance['echeance'])) ?></span></span>
                            <?= badge_echeance(Echeance::statut((string) $echeance['echeance'])) ?>
                        </a></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    <?php endif ?>
</div>
