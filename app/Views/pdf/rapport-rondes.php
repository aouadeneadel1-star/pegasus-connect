<?php
/**
 * @var array<string, mixed> $site
 * @var DateTimeImmutable $du
 * @var DateTimeImmutable $au
 * @var list<array<string, mixed>> $rondes
 * @var array<string, int> $compteurs
 * @var ?float $taux
 * @var list<array<string, mixed>> $anomalies
 * @var bool $nomsAgents
 * @var string $societe
 * @var DateTimeImmutable $genereLe
 * @var string $racinePublique
 */
use App\Core\Clock;
use App\Repositories\AnomalieRepository;

$statuts = ['terminee' => 'Terminée', 'incomplete' => 'Incomplète', 'manquee' => 'Manquée', 'en_cours' => 'En cours'];
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Rapport de rondes</title><?= $this->insert('pdf/_style') ?></head>
<body>
<table class="entete">
    <tr>
        <td><img class="logo" src="<?= e($racinePublique) ?>/assets/img/logo.png" alt="<?= e($societe) ?>"></td>
        <td class="droite">
            <h1>Rapport de rondes</h1>
            <b><?= e($site['nom']) ?></b><br>
            <span class="muted">Du <?= e($du->setTimezone(Clock::local())->format('d/m/Y')) ?> au <?= e($au->modify('-1 second')->setTimezone(Clock::local())->format('d/m/Y')) ?></span>
        </td>
    </tr>
</table>

<table class="kpis">
    <tr>
        <td><b><?= e($compteurs['prevues']) ?></b>rondes</td>
        <td><b><?= e($compteurs['terminee']) ?></b>terminées</td>
        <td><b><?= e($compteurs['incomplete'] + $compteurs['manquee']) ?></b>incomplètes ou manquées</td>
        <td><b><?= $taux === null ? '—' : e($taux) . ' %' ?></b>des points contrôlés</td>
    </tr>
</table>

<h2>Rondes</h2>
<?php if ($rondes === []): ?>
    <p class="muted">Aucune ronde sur la période.</p>
<?php else: ?>
    <table class="donnees">
        <thead><tr><th>Date</th><th>Ronde</th><th>Début</th><th>Fin</th><?php if ($nomsAgents): ?><th>Agent</th><?php endif ?><th class="n">Points</th><th>Statut</th></tr></thead>
        <tbody>
            <?php foreach ($rondes as $ronde): ?>
                <tr>
                    <td><?= e(Clock::fromSql((string) ($ronde['demarree_le'] ?? $ronde['prevue_le']))->setTimezone(Clock::local())->format('d/m/Y')) ?></td>
                    <td><?= e($ronde['modele'] ?? 'Ronde libre') ?></td>
                    <td><?= $ronde['demarree_le'] ? e(heure((string) $ronde['demarree_le'])) : '—' ?></td>
                    <td><?= $ronde['cloturee_le'] ? e(heure((string) $ronde['cloturee_le'])) : '—' ?></td>
                    <?php if ($nomsAgents): ?><td><?= e(trim(($ronde['prenom'] ?? '') . ' ' . mb_substr((string) ($ronde['nom'] ?? ''), 0, 1))) ?></td><?php endif ?>
                    <td class="n"><?= e($ronde['nb_points_controles']) ?> / <?= e($ronde['nb_points_prevus']) ?></td>
                    <td><?= e($statuts[$ronde['statut']] ?? $ronde['statut']) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<h2>Anomalies</h2>
<?php if ($anomalies === []): ?>
    <p class="muted">Aucune anomalie signalée.</p>
<?php else: ?>
    <table class="donnees">
        <thead><tr><th>Date</th><th>Catégorie</th><th>Gravité</th><th>Description</th><th>Statut</th></tr></thead>
        <tbody>
            <?php foreach ($anomalies as $anomalie): ?>
                <tr>
                    <td><?= e(date_heure((string) $anomalie['created_at'])) ?></td>
                    <td><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?></td>
                    <td><?= e(AnomalieRepository::GRAVITES[$anomalie['gravite']]) ?></td>
                    <td><?= e(mb_strimwidth((string) $anomalie['description'], 0, 180, '…')) ?></td>
                    <td><?= e(AnomalieRepository::STATUTS[$anomalie['statut']]) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<div class="pied"><?= e($societe) ?> · Rapport généré le <?= e($genereLe->setTimezone(Clock::local())->format('d/m/Y à H:i')) ?> · Horodatages en heure de Paris, établis par le serveur.</div>
</body>
</html>
