<?php
/**
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var bool $accesAdmin
 * @var string $telephone
 */
$this->layout('agent', ['titre' => 'Plus', 'onglet' => 'plus']);
$liens = [
    ['/profil', 'user', 'Mon profil', 'Identité, carte professionnelle, qualifications'],
    ['/profil/badge', 'nfc', 'Mon badge', 'Code PIN, perte'],
    ['/historique', 'history', 'Historique et heures', 'Services du mois, total'],
    ['/paie', 'euro', 'Mes bulletins de paie', null],
    ['/rondes', 'route', 'Rondes', null],
    ['/contact', 'msg', 'Contact', 'RH, paie, planning, urgence'],
    ['/profil/donnees', 'lock', 'Mes données', 'Géolocalisation, consentements'],
    ['/compte/mot-de-passe', 'key', 'Mot de passe', null],
];
?>
<ul class="list card">
    <?php foreach ($liens as [$lien, $icone, $libelle, $sous]): ?>
        <li>
            <a class="list-item" href="<?= e($lien) ?>">
                <span class="list-icon"><?= icone($icone) ?></span>
                <span class="grow list-lignes"><span class="list-title"><?= e($libelle) ?></span><?php if ($sous !== null): ?><span class="list-sub"><?= e($sous) ?></span><?php endif ?></span>
                <?= icone('next', 'icon-sm') ?>
            </a>
        </li>
    <?php endforeach ?>
    <?php if ($accesAdmin): ?>
        <li>
            <a class="list-item" href="/admin">
                <span class="list-icon brand"><?= icone('chart') ?></span>
                <span class="grow list-lignes"><span class="list-title">Espace chef d'équipe</span><span class="list-sub">Suivi, planning, corrections</span></span>
                <?= icone('next', 'icon-sm') ?>
            </a>
        </li>
    <?php endif ?>
</ul>

<?php if ($telephone !== ''): ?>
    <a class="btn btn-outline btn-block" href="tel:<?= e(preg_replace('/[^+0-9]/', '', $telephone)) ?>"><?= icone('phone') ?>Appeler la permanence</a>
<?php endif ?>

<div class="row row-between">
    <button class="btn btn-ghost" type="button" data-bascule-theme><?= icone('moon') ?>Thème</button>
    <form method="post" action="/deconnexion">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit"><?= icone('logout') ?>Se déconnecter</button>
    </form>
</div>
