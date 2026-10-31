<?php
/**
 * Rapport mensuel d'activité d'un agent. Le NIR n'y figure jamais.
 *
 * @var array<string, mixed> $agent
 * @var int $annee
 * @var int $mois
 * @var array<string, int> $totaux
 * @var array{nombre: int, minutes: int} $retards
 * @var int $absences
 * @var array{rondes: int, points: int} $rondes
 * @var array<string, mixed>|null $carte
 * @var list<array<string, mixed>> $services
 * @var string $societe
 * @var string $reference
 * @var DateTimeImmutable $genereLe
 * @var string $racinePublique
 */
use App\Core\Clock;
use App\Services\Pointage\PointageService;

$h = static fn (int $minutes): string => heures_decimales($minutes);
$aVerifier = array_filter($services, static fn (array $s): bool => (bool) $s['a_verifier']);
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Rapport mensuel</title><?= $this->insert('pdf/_style') ?></head>
<body>
<table class="entete">
    <tr>
        <td><img class="logo" src="<?= e($racinePublique) ?>/assets/img/logo.png" alt="<?= e($societe) ?>"></td>
        <td class="droite">
            <h1>Rapport d'activité — <?= e(ucfirst(nom_mois($mois))) ?> <?= e($annee) ?></h1>
            <span class="muted">Référence <?= e($reference) ?></span>
        </td>
    </tr>
</table>

<table class="donnees">
    <tr><th>Agent</th><td><?= e(mb_strtoupper((string) $agent['nom'])) ?> <?= e($agent['prenom']) ?></td><th>Matricule</th><td><?= e($agent['matricule']) ?></td></tr>
    <tr><th>Fonction</th><td><?= e($agent['fonction']) ?></td><th>Carte professionnelle</th><td><?= $carte === null ? 'Non enregistrée' : e($carte['numero']) . ' (exp. ' . e(date_courte((string) $carte['date_expiration'])) . ')' ?></td></tr>
    <tr><th>Contrat</th><td><?= e($agent['type_contrat']) ?>, <?= e(nombre((float) $agent['heures_mensuelles'], 2)) ?> h / mois</td><th>Équipe</th><td><?= e($agent['equipe'] ?? '—') ?></td></tr>
</table>

<h2>Synthèse</h2>
<table class="kpis">
    <tr>
        <td><b><?= e($h($totaux['travaillees'])) ?></b>heures pointées</td>
        <td><b><?= e($h($totaux['planifiees'])) ?></b>heures planifiées</td>
        <td><b><?= e($retards['nombre']) ?></b>retard(s), <?= e(duree($retards['minutes'])) ?></td>
        <td><b><?= e($absences) ?></b>absence(s) non pointée(s)</td>
    </tr>
</table>

<h2>Ventilation des heures</h2>
<table class="donnees">
    <thead><tr><th>Catégorie</th><th class="n">Heures</th></tr></thead>
    <tbody>
        <tr><td>Heures travaillées</td><td class="n"><?= e($h($totaux['travaillees'])) ?></td></tr>
        <tr><td>dont heures de nuit</td><td class="n"><?= e($h($totaux['nuit'])) ?></td></tr>
        <tr><td>dont heures du dimanche</td><td class="n"><?= e($h($totaux['dimanche'])) ?></td></tr>
        <tr><td>dont heures de jours fériés</td><td class="n"><?= e($h($totaux['ferie'])) ?></td></tr>
        <tr><td>Heures supplémentaires, premier taux</td><td class="n"><?= e($h($totaux['hs25'])) ?></td></tr>
        <tr><td>Heures supplémentaires, second taux</td><td class="n"><?= e($h($totaux['hs50'])) ?></td></tr>
        <tr><td>Rondes effectuées / points contrôlés</td><td class="n"><?= e($rondes['rondes']) ?> / <?= e($rondes['points']) ?></td></tr>
    </tbody>
</table>
<?php if ($aVerifier !== []): ?>
    <p class="alerte"><?= e(count($aVerifier)) ?> service(s) comportent un pointage encore à vérifier : ils sont comptés mais peuvent être corrigés.</p>
<?php endif ?>

<h2>Détail des services</h2>
<?php if ($services === []): ?>
    <p class="muted">Aucun service sur la période.</p>
<?php else: ?>
    <table class="donnees">
        <thead><tr><th>Date</th><th>Site</th><th>Arrivée</th><th>Départ</th><th class="n">Durée</th><th>Observations</th></tr></thead>
        <tbody>
            <?php foreach ($services as $service): ?>
                <tr>
                    <td><?= e(Clock::fromSql((string) $service['debut'])->setTimezone(Clock::local())->format('d/m/Y')) ?></td>
                    <td><?= e($service['site']) ?></td>
                    <td><?= e(heure((string) $service['debut'])) ?></td>
                    <td><?= $service['fin'] === null ? '—' : e(heure((string) $service['fin'])) ?></td>
                    <td class="n"><?= $service['duree_minutes'] === null ? '—' : e($h((int) $service['duree_minutes'])) ?></td>
                    <td><?= e(implode(', ', array_map(PointageService::libelleIndicateur(...), array_intersect($service['indicateurs'], ['retard', 'depart_anticipe', 'hors_planning', 'hors_zone'])))) ?><?= $service['a_verifier'] ? ' (à vérifier)' : '' ?><?= $service['statut'] === 'corrige' ? ' (corrigé)' : '' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<div class="pied"><?= e($societe) ?> · Document généré le <?= e($genereLe->setTimezone(Clock::local())->format('d/m/Y à H:i')) ?> · Heures en décimal (7,50 = 7 h 30). Les variables de paie définitives sont établies par le service paie.</div>
</body>
</html>
