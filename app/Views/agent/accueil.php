<?php

use App\Core\Clock;
use App\Services\Agents\Echeance;

/**
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var array<string, mixed>|null $service
 * @var array<string, mixed>|null $vacation
 * @var array<string, mixed>|null $ronde
 * @var list<array<string, mixed>> $rondesADemarrer
 * @var int $documentsNonLus
 * @var array<string, mixed>|null $carte
 * @var array<string, mixed>|null $badge
 */
$this->layout('agent', ['titre' => 'Bonjour ' . $utilisateur->prenom(), 'onglet' => 'accueil', 'scripts' => ['accueil.js']]);
$statutCarte = $carte === null ? null : Echeance::statut((string) $carte['date_expiration']);
?>
<section class="statut" aria-live="polite" data-etat-service>
    <?php if ($service !== null): ?>
        <span class="statut-etat en-service">En service</span>
        <h2>Depuis <?= e(heure((string) $service['debut'])) ?></h2>
        <p class="sous"><?= icone('pin', 'icon-sm') ?> <?= e($service['site']) ?></p>
    <?php else: ?>
        <span class="statut-etat">Hors service</span>
        <h2><?= $vacation === null ? 'Aucune vacation prévue' : 'Prochaine vacation' ?></h2>
        <?php if ($vacation !== null): ?>
            <p class="sous">
                <?= e(ucfirst(date_longue(date_locale((string) $vacation['debut'])))) ?>,
                <?= e(heure((string) $vacation['debut'])) ?> – <?= e(heure((string) $vacation['fin'])) ?><br>
                <?= icone('pin', 'icon-sm') ?> <?= e($vacation['site']) ?>
            </p>
        <?php endif ?>
    <?php endif ?>
    <a class="btn btn-primary btn-xl btn-block mt-2" href="/pointer"><?= icone('qr') ?><?= $service === null ? 'Pointer mon arrivée' : 'Pointer mon départ' ?></a>
</section>

<?php if ($ronde !== null): ?>
    <a class="card card-accent row" href="/rondes/<?= e($ronde['id']) ?>">
        <span class="list-icon brand"><?= icone('route') ?></span>
        <span class="grow"><b>Ronde en cours</b><br><span class="muted small"><?= e($ronde['modele'] ?? 'Ronde libre') ?> · <?= e($ronde['nb_points_controles']) ?>/<?= e($ronde['nb_points_prevus']) ?> points</span></span>
        <?= icone('next') ?>
    </a>
<?php elseif ($rondesADemarrer !== []): ?>
    <?php $prochaine = $rondesADemarrer[0]; ?>
    <section class="card card-accent stack stack-sm">
        <b><?= icone('route', 'icon-sm') ?> Ronde à effectuer</b>
        <span class="muted small"><?= e($prochaine['modele']) ?> prévue à <?= e(heure((string) $prochaine['prevue_le'])) ?> · <?= e($prochaine['nb_points_prevus']) ?> points</span>
        <button class="btn btn-secondary" type="button" data-demarrer-ronde="<?= e($prochaine['id']) ?>"><?= icone('route') ?>Démarrer la ronde</button>
    </section>
<?php elseif ($service !== null): ?>
    <button class="btn btn-outline btn-block" type="button" data-demarrer-ronde=""><?= icone('route') ?>Démarrer une ronde libre</button>
<?php endif ?>

<?php if ($statutCarte !== null && in_array($statutCarte['code'], ['expire', '7', '30'], true)): ?>
    <div class="alert <?= $statutCarte['code'] === 'expire' ? 'alert-danger' : 'alert-warning' ?>">
        <?= icone('shield') ?>
        <span class="alert-body">
            <?= $statutCarte['code'] === 'expire'
                ? 'Votre carte professionnelle est expirée. Contactez le service RH.'
                : 'Votre carte professionnelle expire dans ' . e($statutCarte['jours']) . ' jours. Pensez à son renouvellement.' ?>
        </span>
    </div>
<?php elseif ($carte === null): ?>
    <div class="alert alert-warning"><?= icone('shield') ?><span class="alert-body">Aucune carte professionnelle n'est enregistrée sur votre fiche.</span></div>
<?php endif ?>

<?php if ($badge !== null && ($badge['pin_hash'] === null || $badge['pin_a_changer'])): ?>
    <a class="alert alert-info" href="/profil/badge"><?= icone('nfc') ?><span class="alert-body">Définissez le code PIN de votre badge avant votre premier badgeage.</span></a>
<?php endif ?>

<?php if ($documentsNonLus > 0): ?>
    <a class="alert alert-info" href="/documents#societe"><?= icone('file') ?><span class="alert-body"><?= e($documentsNonLus) ?> document(s) de la société à lire et à confirmer.</span></a>
<?php endif ?>

<nav class="raccourcis" aria-label="Raccourcis">
    <a class="btn btn-outline" href="/planning"><?= icone('cal') ?>Planning</a>
    <a class="btn btn-outline" href="/rondes"><?= icone('route') ?>Rondes</a>
    <a class="btn btn-outline" href="/anomalie"><?= icone('alert') ?>Signaler</a>
    <a class="btn btn-outline" href="/historique"><?= icone('history') ?>Mes heures</a>
    <a class="btn btn-outline" href="/paie"><?= icone('euro') ?>Paie</a>
    <a class="btn btn-outline" href="/contact"><?= icone('msg') ?>Contact</a>
</nav>
