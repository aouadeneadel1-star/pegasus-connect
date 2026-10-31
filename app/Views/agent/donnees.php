<?php
/**
 * @var list<array<string, mixed>> $consentements
 * @var bool $geolocalisation
 * @var int $retentionGeoloc
 * @var string $versionNotice
 * @var string $emailRh
 */
$this->layout('agent', ['titre' => 'Mes données', 'onglet' => 'plus', 'retour' => '/plus']);
$types = ['geolocalisation' => 'Géolocalisation', 'information_salarie' => 'Notice d\'information', 'charte_utilisation' => 'Charte d\'utilisation'];
?>
<section class="card stack">
    <h2 class="card-header">Géolocalisation</h2>
    <p>Votre position est relevée <b>uniquement</b> au moment d'un pointage ou du scan d'un point de ronde, jamais en continu. Elle sert à vérifier que le scan a lieu sur le site. Les coordonnées sont effacées après <?= e($retentionGeoloc) ?> jours ; seule la distance au site est conservée.</p>
    <form method="post" action="/profil/donnees/geolocalisation" class="row row-between">
        <?= csrf_field() ?>
        <input type="hidden" name="accorde" value="<?= $geolocalisation ? '0' : '1' ?>">
        <span><?= $geolocalisation ? badge('Autorisée', 'badge-success') : badge('Refusée', 'badge-warning') ?></span>
        <button class="btn btn-outline btn-sm" type="submit"><?= $geolocalisation ? 'Ne plus transmettre ma position' : 'Autoriser la géolocalisation' ?></button>
    </form>
    <p class="help">Sur un site équipé d'une borne, refuser la géolocalisation n'empêche pas de pointer. Sur un site à QR fixe, vos pointages seront vérifiés par votre chef d'équipe.</p>
</section>

<section class="card stack">
    <h2 class="card-header">Vos droits</h2>
    <p>Vous pouvez demander l'accès à vos données, leur rectification ou la limitation de leur traitement. Les demandes sont traitées par le service RH dans un délai d'un mois, dans le respect des durées légales de conservation.</p>
    <p><a class="btn btn-outline btn-sm" href="/contact/nouveau?categorie=rh"><?= icone('msg', 'icon-sm') ?>Faire une demande</a>
        <?php if ($emailRh !== ''): ?><a class="btn btn-ghost btn-sm" href="mailto:<?= e($emailRh) ?>"><?= icone('mail', 'icon-sm') ?><?= e($emailRh) ?></a><?php endif ?></p>
    <p class="small"><a href="/consentements">Relire la notice d'information (version <?= e($versionNotice) ?>)</a></p>
</section>

<section class="card">
    <h2 class="card-header">Historique de mes choix</h2>
    <?php if ($consentements === []): ?>
        <p class="muted">Aucun choix enregistré.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($consentements as $consentement): ?>
                <li class="list-item">
                    <span class="grow"><span class="list-title"><?= e($types[$consentement['type']] ?? $consentement['type']) ?></span>
                        <span class="list-sub"><?= e(date_heure((string) $consentement['created_at'])) ?> · version <?= e($consentement['version_texte']) ?></span></span>
                    <?= $consentement['accorde'] ? badge('Accepté', 'badge-success') : badge('Refusé') ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
