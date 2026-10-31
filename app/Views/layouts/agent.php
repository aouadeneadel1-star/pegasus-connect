<?php
/**
 * @var string $titre
 * @var string $contenu
 * @var string $onglet accueil, planning, pointer, documents ou plus
 * @var ?string $retour lien du bouton retour
 * @var \App\Models\UtilisateurConnecte $utilisateur
 * @var int $notificationsNonLues
 * @var list<string> $scripts modules JS de la page
 */
$onglet ??= '';
$retour ??= null;
$scripts ??= [];
$onglets = [
    'accueil' => ['/', 'home', 'Accueil'],
    'planning' => ['/planning', 'cal', 'Planning'],
    'pointer' => ['/pointer', 'qr', 'Pointer'],
    'documents' => ['/documents', 'file', 'Documents'],
    'plus' => ['/plus', 'menu', 'Plus'],
];
?>
<!doctype html>
<html lang="fr">
<head>
<?= $this->insert('partials/head', ['titre' => $titre, 'styles' => ['agent.css'], 'pwa' => true]) ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script type="module" src="<?= e(asset('js/' . $script)) ?>"></script>
<?php endforeach ?>
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<div class="app">
    <header class="app-bar">
        <?php if ($retour !== null): ?>
            <a class="icon-btn" href="<?= e($retour) ?>" aria-label="Retour"><?= icone('back') ?></a>
        <?php else: ?>
            <img class="app-bar-logo" src="<?= e(asset('img/ecusson.png')) ?>" alt="" width="28" height="32">
        <?php endif ?>
        <h1><?= e($titre) ?></h1>
        <a class="icon-btn" href="/notifications" aria-label="Notifications<?= $notificationsNonLues > 0 ? ', ' . e($notificationsNonLues) . ' non lues' : '' ?>">
            <?= icone('bell') ?>
            <?php if ($notificationsNonLues > 0): ?>
                <span class="compteur" aria-hidden="true"><?= $notificationsNonLues > 99 ? '99+' : e($notificationsNonLues) ?></span>
            <?php endif ?>
        </a>
    </header>

    <main class="app-content" id="contenu">
        <?= $this->insert('partials/flash', ['flash' => $flash ?? []]) ?>
        <div class="alert alert-warning" data-file-hors-ligne hidden role="status">
            <?= icone('sync') ?>
            <span class="alert-body"><b data-file-compteur>0</b> enregistrement(s) en attente : ils seront transmis dès le retour du réseau.</span>
        </div>
        <?= $contenu ?>
    </main>

    <nav class="tabbar" aria-label="Navigation principale">
        <?php foreach ($onglets as $cle => [$lien, $icone, $libelle]): ?>
            <a class="tab<?= $cle === 'pointer' ? ' tab-pointer' : '' ?>" href="<?= e($lien) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>>
                <?php if ($cle === 'pointer'): ?>
                    <span class="disque"><?= icone($icone, 'icon-lg') ?></span>
                <?php else: ?>
                    <?= icone($icone) ?>
                <?php endif ?>
                <?= e($libelle) ?>
            </a>
        <?php endforeach ?>
    </nav>
</div>
<div class="toasts" aria-live="polite" data-toasts></div>
</body>
</html>
