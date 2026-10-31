<?php
/**
 * @var string $titre
 * @var string $contenu
 * @var string $menu entrée active du menu
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var list<string> $scripts
 */
$menu ??= '';
$scripts ??= [];
$sections = [
    'Pilotage' => [
        ['tableau', '/admin', 'chart', 'Tableau de bord', null],
        ['pointages', '/admin/pointages', 'clock', 'Pointages', 'pointages.suivre'],
        ['rondes', '/admin/rondes', 'route', 'Rondes', 'rondes.suivre'],
        ['anomalies', '/admin/anomalies', 'alert', 'Anomalies', 'rondes.suivre'],
    ],
    'Personnel' => [
        ['agents', '/admin/agents', 'users', 'Agents', 'agents.voir'],
        ['echeances', '/admin/echeances', 'shield', 'Échéances', 'echeances.voir'],
        ['badges', '/admin/badges', 'nfc', 'Badges', 'badges.gerer'],
        ['planning', '/admin/planning', 'cal', 'Planning', 'planning.voir'],
        ['heures', '/admin/heures', 'history', 'Heures', 'heures.voir'],
        ['paie', '/admin/paie', 'euro', 'Paie', 'paie.gerer'],
        ['societe', '/admin/societe', 'file', 'Documents société', 'societe.gerer'],
    ],
    'Activité' => [
        ['rapports', '/admin/rapports', 'chart', 'Rapports', 'rapports.voir'],
        ['demandes', '/admin/demandes', 'msg', 'Demandes', 'demandes.traiter'],
        ['demandes-profil', '/admin/demandes-profil', 'edit', 'Modifications de profil', 'demandes_profil.traiter'],
    ],
    'Paramétrage' => [
        ['sites', '/admin/sites', 'building', 'Sites et QR codes', 'sites.voir'],
        ['clients', '/admin/clients', 'shield', 'Clients', 'clients.gerer'],
        ['utilisateurs', '/admin/utilisateurs', 'user', 'Utilisateurs', 'utilisateurs.gerer'],
        ['roles', '/admin/roles', 'key', 'Rôles et droits', 'roles.gerer'],
        ['parametres', '/admin/parametres', 'gear', 'Paramètres', 'parametres.gerer'],
        ['majorations', '/admin/majorations', 'euro', 'Majorations', 'majorations.gerer'],
        ['audit', '/admin/audit', 'history', 'Journal d\'audit', 'audit.voir'],
    ],
];
?>
<!doctype html>
<html lang="fr">
<head>
<?= $this->insert('partials/head', ['titre' => $titre, 'styles' => ['admin.css']]) ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script type="module" src="<?= e(asset('js/' . $script)) ?>"></script>
<?php endforeach ?>
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<div class="shell">
    <nav class="sidebar" aria-label="Menu d'administration">
        <a class="sidebar-marque" href="/admin">
            <img src="<?= e(asset('img/ecusson.png')) ?>" alt="" width="30" height="34">
            <span class="marque-texte">Pegasus<small>Insure</small></span>
        </a>
        <?php foreach ($sections as $groupe => $liens): ?>
            <?php $visibles = array_filter($liens, static fn (array $lien): bool => $lien[4] === null || $utilisateur->peut($lien[4])); ?>
            <?php if ($visibles === []) {
                continue;
            } ?>
            <span class="nav-groupe"><?= e($groupe) ?></span>
            <?php foreach ($visibles as [$cle, $lien, $icone, $libelle]): ?>
                <a class="nav-lien" href="<?= e($lien) ?>"<?= $menu === $cle ? ' aria-current="page"' : '' ?>>
                    <?= icone($icone) ?><?= e($libelle) ?>
                </a>
            <?php endforeach ?>
        <?php endforeach ?>
        <?php if ($utilisateur->role->estTerrain()): ?>
            <span class="nav-groupe">Terrain</span>
            <a class="nav-lien" href="/"><?= icone('home') ?>Mon espace agent</a>
        <?php endif ?>
    </nav>

    <div class="principal">
        <header class="topbar">
            <?php if ($utilisateur->peut('agents.voir')): ?>
                <form class="recherche" method="get" action="/admin/agents" role="search">
                    <?= icone('search', 'icon-sm') ?>
                    <label class="sr-only" for="recherche-globale">Rechercher un agent</label>
                    <input id="recherche-globale" name="q" type="search" placeholder="Rechercher un agent, un matricule…">
                </form>
            <?php endif ?>
            <div class="compte">
                <a class="btn btn-ghost btn-icon" href="/admin/notifications" aria-label="Notifications<?= $notificationsNonLues > 0 ? ', ' . e($notificationsNonLues) . ' non lues' : '' ?>">
                    <?= icone('bell') ?>
                    <?php if ($notificationsNonLues > 0): ?>
                        <span class="nav-compteur" aria-hidden="true"><?= e($notificationsNonLues) ?></span>
                    <?php endif ?>
                </a>
                <details class="menu-compte">
                    <summary class="row" aria-label="Menu du compte">
                        <span class="avatar avatar-sm"><?= e($utilisateur->initiales()) ?></span>
                        <span class="compte-nom"><b><?= e($utilisateur->nomComplet) ?></b><br><span class="muted"><?= e($utilisateur->role->libelle()) ?></span></span>
                    </summary>
                    <div class="menu">
                        <a href="/compte/mot-de-passe"><?= icone('key') ?>Changer de mot de passe</a>
                        <?php if (!$utilisateur->totpActif): ?>
                            <a href="/compte/2fa"><?= icone('lock') ?>Activer la double authentification</a>
                        <?php endif ?>
                        <button type="button" data-bascule-theme><?= icone('moon') ?>Changer de thème</button>
                        <form method="post" action="/deconnexion">
                            <?= csrf_field() ?>
                            <button type="submit"><?= icone('logout') ?>Se déconnecter</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="page" id="contenu">
            <?= $this->insert('partials/flash', ['flash' => $flash ?? []]) ?>
            <?= $contenu ?>
        </main>
    </div>
</div>
<div class="toasts" aria-live="polite" data-toasts></div>
</body>
</html>
