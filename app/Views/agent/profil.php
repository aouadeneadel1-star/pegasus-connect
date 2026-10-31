<?php

use App\Services\Agents\Echeance;

/**
 * @var array<string, mixed> $agent
 * @var string $nirMasque
 * @var array<string, mixed>|null $carte
 * @var list<array<string, mixed>> $qualifications
 * @var list<array<string, mixed>> $affectations
 * @var list<array<string, mixed>> $demandes
 * @var array<string, mixed>|null $badge
 * @var array<string, string> $champs
 */
$this->layout('agent', ['titre' => 'Mon profil', 'onglet' => 'plus', 'retour' => '/plus']);
$enAttente = array_filter($demandes, static fn (array $d): bool => $d['statut'] === 'en_attente');
?>
<section class="card">
    <div class="entete-fiche">
        <span class="avatar avatar-lg" aria-hidden="true"><?= e(mb_substr((string) $agent['prenom'], 0, 1) . mb_substr((string) $agent['nom'], 0, 1)) ?></span>
        <div>
            <h2><?= e($agent['prenom']) ?> <?= e($agent['nom']) ?></h2>
            <p class="muted small"><span class="mono"><?= e($agent['matricule']) ?></span> · <?= e($agent['fonction']) ?></p>
        </div>
    </div>
    <dl class="dl mt-4">
        <dt>Naissance</dt><dd><?= e(date_courte((string) $agent['date_naissance'])) ?>, <?= e($agent['lieu_naissance']) ?></dd>
        <dt>Sécurité sociale</dt><dd class="mono"><?= e($nirMasque) ?></dd>
        <dt>Adresse</dt><dd><?= e($agent['adresse']) ?>, <?= e($agent['code_postal']) ?> <?= e($agent['ville']) ?></dd>
        <dt>Téléphone</dt><dd><?= e($agent['telephone']) ?></dd>
        <dt>E-mail</dt><dd><?= e($agent['email']) ?></dd>
        <dt>Embauche</dt><dd><?= e(date_courte((string) $agent['date_embauche'])) ?></dd>
        <dt>Sites</dt><dd><?= e(implode(', ', array_map(static fn (array $a): string => (string) $a['nom'], array_filter($affectations, static fn (array $a): bool => $a['date_fin'] === null)))) ?: '—' ?></dd>
    </dl>
</section>

<section class="card">
    <h2 class="card-header">Carte professionnelle</h2>
    <?php if ($carte === null): ?>
        <p class="muted">Non enregistrée. Transmettez-la au service RH.</p>
    <?php else: ?>
        <dl class="dl">
            <dt>Numéro</dt><dd class="mono"><?= e($carte['numero']) ?></dd>
            <dt>Expire le</dt><dd><?= e(date_courte((string) $carte['date_expiration'])) ?> <?= badge_echeance(Echeance::statut((string) $carte['date_expiration'])) ?></dd>
        </dl>
    <?php endif ?>
</section>

<section class="card">
    <h2 class="card-header">Qualifications</h2>
    <?php if ($qualifications === []): ?>
        <p class="muted">Aucune qualification enregistrée.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($qualifications as $qualification): ?>
                <li class="list-item">
                    <span class="grow"><span class="list-title"><?= e($qualification['type_libelle']) ?></span>
                        <span class="list-sub"><?= $qualification['date_echeance'] === null ? 'Sans échéance' : 'Échéance : ' . e(date_courte((string) $qualification['date_echeance'])) ?></span></span>
                    <?= badge_echeance(Echeance::statut($qualification['date_echeance'] === null ? null : (string) $qualification['date_echeance'])) ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>

<a class="card row" href="/profil/badge">
    <span class="list-icon"><?= icone('nfc') ?></span>
    <span class="grow"><b>Mon badge</b><br><span class="muted small"><?= $badge === null ? 'Aucun badge actif' : 'N° ' . e($badge['numero']) ?></span></span>
    <?= icone('next') ?>
</a>

<section class="card stack" id="modifier">
    <h2 class="card-header">Demander une modification</h2>
    <?php if ($enAttente !== []): ?>
        <div class="alert alert-info"><?= icone('clock') ?><span class="alert-body">Une demande est en cours de traitement par le service RH.</span></div>
    <?php endif ?>
    <form class="stack" method="post" action="/profil/demande" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <p class="help">Renseignez uniquement les champs à changer. Les coordonnées bancaires se transmettent directement au service paie.</p>
        <?php foreach ($champs as $champ => $libelle): ?>
            <div class="field">
                <label for="modif-<?= e($champ) ?>"><?= e($libelle) ?></label>
                <input class="input" id="modif-<?= e($champ) ?>" name="<?= e($champ) ?>" type="<?= $champ === 'email' ? 'email' : ($champ === 'telephone' ? 'tel' : 'text') ?>" placeholder="<?= e($agent[$champ]) ?>">
            </div>
        <?php endforeach ?>
        <div class="field">
            <label for="modif-justificatif">Justificatif <span class="muted">(facultatif)</span></label>
            <input class="input" id="modif-justificatif" name="justificatif" type="file" accept="application/pdf,image/jpeg,image/png,image/webp">
        </div>
        <button class="btn btn-secondary" type="submit"><?= icone('send') ?>Envoyer la demande</button>
    </form>
    <?php foreach ($demandes as $demande): ?>
        <?php if ($demande['statut'] === 'refusee'): ?>
            <p class="small"><?= badge('Refusée', 'badge-danger') ?> Demande du <?= e(date_courte((string) $demande['created_at'])) ?> : <?= e($demande['motif_refus'] ?? '') ?></p>
        <?php endif ?>
    <?php endforeach ?>
</section>
