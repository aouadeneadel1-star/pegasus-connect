<?php
/**
 * @var string $titre
 * @var string $contenu
 * @var string $menu
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var int $notificationsNonLues
 * @var list<string> $scripts
 */
$menu ??= '';
$scripts ??= [];
$liens = [
    'tableau' => ['/client', 'chart', 'Tableau de bord'],
    'rondes' => ['/client/rondes', 'route', 'Rondes'],
    'anomalies' => ['/client/anomalies', 'alert', 'Anomalies'],
    'rapports' => ['/client/rapports', 'file', 'Rapports'],
    'contact' => ['/client/contact', 'msg', 'Contact'],
];
?>
<!doctype html>
<html lang="fr">
<head>
<?= $this->insert('partials/head', ['titre' => $titre, 'styles' => ['admin.css', 'client.css']]) ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script type="module" src="<?= e(asset('js/' . $script)) ?>"></script>
<?php endforeach ?>
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="client-entete">
    <div class="client-barre">
        <a class="sidebar-marque" href="/client">
            <img src="<?= e(asset('img/ecusson.png')) ?>" alt="" width="30" height="34">
            <span class="marque-texte">Pegasus<small>Insure</small></span>
        </a>
        <nav class="client-nav" aria-label="Menu principal">
            <?php foreach ($liens as $cle => [$lien, $icone, $libelle]): ?>
                <a href="<?= e($lien) ?>"<?= $menu === $cle ? ' aria-current="page"' : '' ?>><?= icone($icone, 'icon-sm') ?><?= e($libelle) ?></a>
            <?php endforeach ?>
        </nav>
        <div class="compte">
            <a class="btn btn-ghost btn-icon" href="/client/notifications" aria-label="Notifications<?= $notificationsNonLues > 0 ? ', ' . e($notificationsNonLues) . ' non lues' : '' ?>">
                <?= icone('bell') ?>
                <?php if ($notificationsNonLues > 0): ?><span class="nav-compteur" aria-hidden="true"><?= e($notificationsNonLues) ?></span><?php endif ?>
            </a>
            <details class="menu-compte">
                <summary class="row" aria-label="Menu du compte">
                    <span class="avatar avatar-sm"><?= e($utilisateur->initiales()) ?></span>
                </summary>
                <div class="menu">
                    <p class="menu-titre"><b><?= e($utilisateur->nomComplet) ?></b><br><span class="muted small"><?= e($utilisateur->email) ?></span></p>
                    <a href="/compte/mot-de-passe"><?= icone('key') ?>Changer de mot de passe</a>
                    <button type="button" data-bascule-theme><?= icone('moon') ?>Changer de thème</button>
                    <form method="post" action="/deconnexion">
                        <?= csrf_field() ?>
                        <button type="submit"><?= icone('logout') ?>Se déconnecter</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
<main class="page client-page" id="contenu">
    <?= $this->insert('partials/flash', ['flash' => $flash ?? []]) ?>
    <?= $contenu ?>
</main>
<footer class="client-pied">
    <span>Pegasus Insure — sécurité privée, Nice</span>
    <span class="muted">Les données affichées sont limitées à vos sites.</span>
</footer>
<div class="toasts" aria-live="polite" data-toasts></div>
</body>
</html>
