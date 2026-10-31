<?php
/**
 * @var array<string, mixed> $agent
 * @var string $nirMasque
 * @var bool $peutRevelerNir
 * @var list<array<string, mixed>> $affectations
 */
$contrats = ['CDI' => 'CDI', 'CDD' => 'CDD', 'interim' => 'Intérim', 'apprentissage' => 'Apprentissage', 'autre' => 'Autre'];
$aujourdhui = \App\Core\Clock::now()->setTimezone(\App\Core\Clock::local())->format('Y-m-d');
?>
<div class="grid-2">
    <section class="card">
        <h2 class="card-header">Identité</h2>
        <dl class="dl">
            <dt>Nom</dt><dd><?= e(trim(($agent['civilite'] ?? '') . ' ' . $agent['nom'])) ?></dd>
            <?php if (!empty($agent['nom_naissance'])): ?>
                <dt>Nom de naissance</dt><dd><?= e($agent['nom_naissance']) ?></dd>
            <?php endif ?>
            <dt>Prénom</dt><dd><?= e($agent['prenom']) ?></dd>
            <dt>Naissance</dt><dd><?= e(date_courte((string) $agent['date_naissance'])) ?> à <?= e($agent['lieu_naissance']) ?></dd>
            <dt>Nationalité</dt><dd><?= e($agent['nationalite']) ?></dd>
            <dt>Sécurité sociale</dt>
            <dd>
                <span class="secret-box" data-nir>
                    <span class="mono" data-nir-valeur><?= e($nirMasque) ?></span>
                    <?php if ($peutRevelerNir && $agent['nir_fin'] !== null): ?>
                        <button class="btn btn-ghost btn-sm" type="button" data-reveler-nir="/admin/agents/<?= e($agent['id']) ?>/nir">
                            <?= icone('eye', 'icon-sm') ?>Afficher
                        </button>
                    <?php endif ?>
                </span>
            </dd>
        </dl>
    </section>

    <section class="card">
        <h2 class="card-header">Coordonnées</h2>
        <dl class="dl">
            <dt>Adresse</dt><dd><?= e($agent['adresse']) ?><br><?= e($agent['code_postal']) ?> <?= e($agent['ville']) ?></dd>
            <dt>Téléphone</dt><dd><a href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string) $agent['telephone'])) ?>"><?= e($agent['telephone']) ?></a></dd>
            <dt>E-mail</dt><dd><a href="mailto:<?= e($agent['email']) ?>"><?= e($agent['email']) ?></a></dd>
            <dt>Dernière connexion</dt><dd><?= $agent['derniere_connexion'] === null ? '<span class="muted">Jamais</span>' : e(date_heure((string) $agent['derniere_connexion'])) ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2 class="card-header">Contrat</h2>
        <dl class="dl">
            <dt>Fonction</dt><dd><?= e($agent['fonction']) ?></dd>
            <dt>Contrat</dt><dd><?= e($contrats[$agent['type_contrat']] ?? $agent['type_contrat']) ?>, temps <?= e($agent['temps_travail']) ?></dd>
            <dt>Heures mensuelles</dt><dd class="tnum"><?= e(nombre((float) $agent['heures_mensuelles'], 2)) ?> h</dd>
            <?php if ($agent['taux_horaire_base'] !== null): ?>
                <dt>Taux horaire brut</dt><dd class="tnum"><?= e(nombre((float) $agent['taux_horaire_base'], 4)) ?> €</dd>
            <?php endif ?>
            <dt>Embauche</dt><dd><?= e(date_courte((string) $agent['date_embauche'])) ?></dd>
            <?php if ($agent['date_sortie'] !== null): ?>
                <dt>Sortie</dt><dd><?= e(date_courte((string) $agent['date_sortie'])) ?></dd>
            <?php endif ?>
            <dt>Équipe</dt><dd><?= e($agent['equipe'] ?? '—') ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2 class="card-header">Affectations</h2>
        <?php if ($affectations === []): ?>
            <p class="muted">Aucun site affecté. L'agent ne pourra pointer que sur un site où il est planifié.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($affectations as $affectation): ?>
                    <?php $active = $affectation['date_fin'] === null || $affectation['date_fin'] >= $aujourdhui; ?>
                    <li class="list-item">
                        <span class="list-icon<?= $active ? ' brand' : '' ?>"><?= icone('building') ?></span>
                        <span class="grow">
                            <span class="list-title"><?= e($affectation['nom']) ?><?= $affectation['principal'] ? ' ' . badge('Principal', 'badge-brand') : '' ?></span>
                            <span class="list-sub">
                                Depuis le <?= e(date_courte((string) $affectation['date_debut'])) ?>
                                <?= $affectation['date_fin'] === null ? '' : ' · jusqu\'au ' . e(date_courte((string) $affectation['date_fin'])) ?>
                            </span>
                        </span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</div>
