<?php
/**
 * @var list<array<string, mixed>> $services
 * @var list<array<string, mixed>> $rondes
 */
$statutsService = [
    'ouvert' => ['En service', 'badge-success'],
    'clos' => ['Clos', ''],
    'depart_manquant' => ['Départ manquant', 'badge-warning'],
    'corrige' => ['Corrigé', 'badge-info'],
    'annule' => ['Annulé', ''],
];
$statutsRonde = [
    'en_cours' => ['En cours', 'badge-info'],
    'terminee' => ['Terminée', 'badge-success'],
    'incomplete' => ['Incomplète', 'badge-warning'],
    'manquee' => ['Manquée', 'badge-danger'],
    'annulee' => ['Annulée', ''],
    'a_venir' => ['À venir', ''],
];
?>
<div class="grid-2">
    <section class="card">
        <h2 class="card-header">Derniers services</h2>
        <?php if ($services === []): ?>
            <p class="muted">Aucun service pointé.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Date</th><th scope="col">Site</th><th scope="col">Horaires</th><th scope="col">Durée</th><th scope="col">Statut</th></tr></thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <?php [$libelle, $classe] = $statutsService[$service['statut']] ?? [$service['statut'], '']; ?>
                            <tr>
                                <td class="nowrap"><?= e(date_courte((string) $service['debut'])) ?></td>
                                <td><?= e($service['site']) ?></td>
                                <td class="tnum nowrap"><?= e(heure((string) $service['debut'])) ?> – <?= $service['fin'] === null ? '…' : e(heure((string) $service['fin'])) ?></td>
                                <td class="tnum"><?= e(duree($service['duree_minutes'] === null ? null : (int) $service['duree_minutes'])) ?></td>
                                <td><?= badge($libelle, $classe) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="card">
        <h2 class="card-header">Dernières rondes</h2>
        <?php if ($rondes === []): ?>
            <p class="muted">Aucune ronde effectuée.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($rondes as $ronde): ?>
                    <?php [$libelle, $classe] = $statutsRonde[$ronde['statut']] ?? [$ronde['statut'], '']; ?>
                    <li class="list-item">
                        <span class="list-icon"><?= icone('route') ?></span>
                        <span class="grow">
                            <a class="list-title" href="/admin/rondes/<?= e($ronde['id']) ?>"><?= e($ronde['modele'] ?? 'Ronde libre') ?> · <?= e($ronde['site']) ?></a>
                            <span class="list-sub"><?= e(date_heure((string) ($ronde['demarree_le'] ?? $ronde['prevue_le']))) ?> · <?= e($ronde['nb_points_controles']) ?>/<?= e($ronde['nb_points_prevus']) ?> points</span>
                        </span>
                        <?= badge($libelle, $classe) ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</div>
