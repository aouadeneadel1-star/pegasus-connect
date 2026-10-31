<?php
/**
 * @var string $titre
 * @var string $contenu
 * @var bool $large
 */
$large ??= false;
?>
<!doctype html>
<html lang="fr">
<head>
<?= $this->insert('partials/head', ['titre' => $titre, 'styles' => ['auth.css'], 'pwa' => true]) ?>
</head>
<body>
<main class="auth-page">
    <div class="auth-card<?= $large ? ' auth-card-large' : '' ?>">
        <div class="auth-brand">
            <img src="<?= e(asset('img/logo.png')) ?>" alt="Pegasus Insure" width="132" height="132">
            <p>Espace collaborateurs</p>
        </div>
        <?= $contenu ?>
    </div>
</main>
</body>
</html>
