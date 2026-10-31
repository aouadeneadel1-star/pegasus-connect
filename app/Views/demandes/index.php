<?php

use App\Repositories\DemandeRepository;

/**
 * @var string $espace
 * @var string $prefixe
 * @var list<array<string, mixed>> $demandes
 * @var string $telephone
 */
$this->layout($espace, ['titre' => 'Contact', 'menu' => 'contact', 'onglet' => 'plus', 'retour' => $espace === 'agent' ? '/plus' : null]);
$classes = ['nouvelle' => 'badge-info', 'en_cours' => 'badge-warning', 'attente_demandeur' => 'badge-brand', 'resolue' => 'badge-success', 'close' => ''];
?>
<?php if ($espace !== 'agent'): ?>
    <div class="page-head">
        <h1>Contact</h1>
        <a class="btn btn-primary btn-sm" href="<?= e($prefixe) ?>/contact/nouveau"><?= icone('plus', 'icon-sm') ?>Nouvelle demande</a>
    </div>
<?php endif ?>

<?php if ($telephone !== ''): ?>
    <div class="alert alert-info">
        <?= icone('phone') ?>
        <span class="alert-body">Urgence sur site : appelez la permanence au <a class="strong" href="tel:<?= e(preg_replace('/[^+0-9]/', '', $telephone)) ?>"><?= e($telephone) ?></a>.</span>
    </div>
<?php endif ?>

<?php if ($espace === 'agent'): ?>
    <a class="btn btn-primary btn-block mt-4" href="/contact/nouveau"><?= icone('plus') ?>Nouvelle demande</a>
<?php endif ?>

<h2 class="list-heading mt-5">Mes demandes</h2>
<?php if ($demandes === []): ?>
    <div class="card empty">Vous n'avez encore envoyé aucune demande.</div>
<?php else: ?>
    <ul class="list card">
        <?php foreach ($demandes as $demande): ?>
            <li>
                <a class="list-item" href="<?= e($prefixe) ?>/contact/<?= e($demande['id']) ?>">
                    <span class="list-icon"><?= icone($demande['categorie'] === 'urgence' ? 'alert' : 'msg') ?></span>
                    <span class="grow list-lignes">
                        <span class="list-title"><?= e($demande['objet']) ?></span>
                        <span class="list-sub"><?= e(DemandeRepository::CATEGORIES[$demande['categorie']] ?? $demande['categorie']) ?> · <?= e(date_heure((string) ($demande['dernier_message'] ?? $demande['created_at']))) ?></span>
                    </span>
                    <?= badge(DemandeRepository::STATUTS[$demande['statut']] ?? $demande['statut'], $classes[$demande['statut']] ?? '') ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
