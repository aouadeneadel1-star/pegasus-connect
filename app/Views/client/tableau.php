<?php
/**
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var list<array<string, mixed>> $sites
 * @var list<array<string, mixed>> $anomalies
 * @var bool $nomsAgents
 */
use App\Repositories\AnomalieRepository;

$this->layout('client', ['titre' => 'Tableau de bord', 'menu' => 'tableau']);
$classes = ['critique' => 'badge-danger', 'majeure' => 'badge-warning', 'mineure' => 'badge-info', 'info' => ''];
?>
<div class="page-head">
    <div>
        <h1>Bonjour <?= e($utilisateur->prenom()) ?></h1>
        <p class="muted">Situation de vos sites en temps réel.</p>
    </div>
</div>

<?php if ($sites === []): ?>
    <div class="card empty">Aucun site n'est rattaché à votre compte.</div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($sites as $site): ?>
            <a class="card stack stack-sm carte-site" href="/client/sites/<?= e($site['id']) ?>">
                <div class="row row-between"><b><?= e($site['nom']) ?></b><?= icone('next', 'icon-sm') ?></div>
                <span class="muted small"><?= e($site['ville']) ?></span>
                <span class="row">
                    <span class="statut-pastille<?= $site['presents'] === [] ? '' : ' actif' ?>" aria-hidden="true"></span>
                    <?= $site['presents'] === [] ? 'Aucun agent en service' : e(count($site['presents'])) . ' agent(s) en service' ?>
                </span>
                <?php $derniere = $site['rondes'][0] ?? null; ?>
                <span class="small"><?= $derniere === null ? '<span class="muted">Pas de ronde récente</span>' : 'Dernière ronde : ' . e(date_heure((string) ($derniere['demarree_le'] ?? $derniere['prevue_le']))) . ' ' . $this->insert('partials/statut-ronde', ['statut' => (string) $derniere['statut']]) ?></span>
            </a>
        <?php endforeach ?>
    </div>
<?php endif ?>

<section class="card mt-5">
    <div class="row row-between"><h2 class="card-header">Anomalies en cours</h2><a class="small" href="/client/anomalies">Historique</a></div>
    <?php if ($anomalies === []): ?>
        <p class="muted">Aucune anomalie en cours sur vos sites.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($anomalies as $anomalie): ?>
                <li><a class="list-item" href="/client/anomalies/<?= e($anomalie['id']) ?>">
                    <span class="grow"><span class="list-title"><?= e(AnomalieRepository::CATEGORIES[$anomalie['categorie']]) ?> · <?= e($anomalie['site']) ?></span><span class="list-sub"><?= e(date_heure((string) $anomalie['created_at'])) ?></span></span>
                    <?= badge(AnomalieRepository::GRAVITES[$anomalie['gravite']], $classes[$anomalie['gravite']]) ?>
                </a></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
