<?php

use App\Services\Agents\Echeance;

/**
 * @var array<string, mixed> $agent
 * @var string $onglet
 * @var string $nirMasque
 * @var bool $peutGerer
 * @var bool $peutRevelerNir
 * @var list<array<string, mixed>> $affectations
 * @var array<string, mixed>|null $carte
 */
$nom = $agent['prenom'] . ' ' . $agent['nom'];
$this->layout('admin', ['titre' => $nom, 'menu' => 'agents']);
$base = '/admin/agents/' . $agent['id'];
$onglets = [
    'identite' => 'Identité et contrat',
    'conformite' => 'Carte pro et qualifications',
    'documents' => 'Documents',
    'activite' => 'Activité',
    'badge' => 'Badge NFC',
];
$statutCarte = $carte === null ? null : Echeance::statut((string) $carte['date_expiration']);
?>
<p class="fil-ariane"><a href="/admin/agents">Agents</a> ›</p>
<div class="page-head">
    <div class="entete-fiche">
        <span class="avatar avatar-lg" aria-hidden="true"><?= e(mb_substr((string) $agent['prenom'], 0, 1) . mb_substr((string) $agent['nom'], 0, 1)) ?></span>
        <div>
            <h1><?= e($nom) ?></h1>
            <p class="muted">
                <span class="mono"><?= e($agent['matricule']) ?></span> · <?= e($agent['fonction']) ?>
                <?= $agent['role_code'] === 'chef' ? ' · Chef d\'équipe' : '' ?>
            </p>
            <p class="row row-wrap mt-2">
                <?= badge_compte((string) $agent['statut']) ?>
                <?= $statutCarte === null ? badge('Carte pro absente', 'badge-danger') : badge('Carte pro : ' . $statutCarte['libelle'], $statutCarte['classe']) ?>
                <?= $agent['totp_actif'] ? badge('2FA active', 'badge-success') : '' ?>
            </p>
        </div>
    </div>
    <?php if ($peutGerer): ?>
        <div class="row row-wrap">
            <?php if ($agent['statut'] === 'invite'): ?>
                <form method="post" action="<?= e($base) ?>/inviter">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm" type="submit"><?= icone('send', 'icon-sm') ?>Renvoyer l'invitation</button>
                </form>
            <?php endif ?>
            <a class="btn btn-outline btn-sm" href="<?= e($base) ?>/modifier"><?= icone('edit', 'icon-sm') ?>Modifier</a>
            <?php if ($utilisateur->peut('rgpd.exporter')): ?>
                <form method="post" action="/admin/rgpd/<?= e($agent['id']) ?>/export" data-confirmer="Générer l'export des données personnelles de <?= e($nom) ?> ? L'opération est journalisée.">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= icone('dl', 'icon-sm') ?>Export RGPD</button>
                </form>
            <?php endif ?>
            <details class="menu-compte">
                <summary class="btn btn-ghost btn-sm" aria-label="Autres actions"><?= icone('chevron-down', 'icon-sm') ?>Statut</summary>
                <div class="menu">
                    <?php foreach (['actif' => ['Réactiver le compte', 'check'], 'suspendu' => ['Suspendre le compte', 'lock'], 'desactive' => ['Désactiver (départ)', 'power']] as $statut => [$libelle, $icone]): ?>
                        <?php if ($agent['statut'] === $statut || ($statut === 'actif' && $agent['statut'] === 'invite')) {
                            continue;
                        } ?>
                        <form method="post" action="<?= e($base) ?>/statut" data-confirmer="<?= e($libelle . ' de ' . $nom . ' ?') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="statut" value="<?= e($statut) ?>">
                            <button type="submit"><?= icone($icone) ?><?= e($libelle) ?></button>
                        </form>
                    <?php endforeach ?>
                </div>
            </details>
        </div>
    <?php endif ?>
</div>

<nav class="tabs" aria-label="Sections de la fiche">
    <?php foreach ($onglets as $cle => $libelle): ?>
        <a href="<?= e($base . ($cle === 'identite' ? '' : '?onglet=' . $cle)) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
    <?php endforeach ?>
</nav>

<?= $this->insert('admin/agents/onglets/' . $onglet, $__data) ?>
