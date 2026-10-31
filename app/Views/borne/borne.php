<?php
/**
 * Borne de pointage plein écran. Aucune donnée d'agent n'est affichée en dehors
 * du prénom et de l'initiale, le temps de la confirmation.
 *
 * @var array<string, mixed> $site
 * @var array<string, mixed>|null $borne
 * @var bool $badgeActif
 * @var bool $pinRequis
 * @var int $dureeConfirmation
 */
$configuration = $borne === null ? null : [
    'code' => $site['code'],
    'site' => (int) $site['id'],
    'borne' => (int) $borne['id'],
    'cleVersion' => (int) $borne['cle_version'],
    'badge' => $badgeActif,
    'lecteur' => $borne['lecteur_nfc'],
    'pin' => $pinRequis,
    'confirmation' => $dureeConfirmation,
];
?>
<!doctype html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#111214">
<title>Borne · <?= e($site['nom']) ?></title>
<link rel="icon" href="<?= e(asset('icons/favicon-32.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/borne.css')) ?>">
<script type="module" src="<?= e(asset('js/borne.js')) ?>"></script>
</head>
<body class="borne">
<?php if ($configuration === null): ?>
    <main class="borne-enrolement" data-enrolement="<?= e($site['code']) ?>">
        <img src="<?= e(asset('img/logo.png')) ?>" alt="Pegasus Insure" class="borne-logo">
        <h1><?= e($site['nom']) ?></h1>
        <p>Cette tablette n'est pas encore enregistrée comme borne de pointage.<br>Saisissez le code d'enrôlement fourni par l'administrateur.</p>
        <form class="stack" data-formulaire-enrolement autocomplete="off">
            <label class="sr-only" for="code-enrolement">Code d'enrôlement</label>
            <input class="input borne-code" id="code-enrolement" name="code" maxlength="9" required autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX">
            <button class="btn btn-primary btn-xl" type="submit">Enregistrer la borne</button>
            <p class="field-error" data-erreur hidden role="alert"></p>
        </form>
    </main>
<?php else: ?>
    <main class="borne-ecran" data-borne="<?= e(json_encode($configuration, JSON_THROW_ON_ERROR)) ?>">
        <header class="borne-entete">
            <img src="<?= e(asset('img/ecusson.png')) ?>" alt="" width="42" height="48">
            <div>
                <p class="borne-site"><?= e($site['nom']) ?></p>
                <p class="borne-libelle"><?= e($borne['libelle']) ?></p>
            </div>
            <p class="borne-heure tnum" data-horloge>--:--</p>
        </header>

        <section class="borne-qr" aria-label="QR code de pointage">
            <canvas data-qr width="360" height="360" aria-label="QR code à scanner avec l'application Pegasus" role="img"></canvas>
            <div class="borne-decompte" aria-hidden="true"><i data-decompte></i></div>
            <p class="borne-consigne">Scannez avec l'application Pegasus</p>
            <?php if ($badgeActif): ?>
                <p class="borne-consigne-badge"><?= icone('nfc') ?> ou présentez votre badge</p>
            <?php endif ?>
        </section>

        <footer class="borne-pied">
            <span class="borne-etat" data-etat-reseau>Connexion…</span>
            <?php if ($badgeActif): ?><span class="borne-etat" data-etat-lecteur>Lecteur de badge…</span><?php endif ?>
            <span class="borne-etat" data-etat-file hidden></span>
        </footer>

        <?php if ($badgeActif): ?>
            <input class="borne-capture" data-capture-badge type="text" inputmode="none" autocomplete="off" aria-hidden="true" tabindex="-1">
        <?php endif ?>
    </main>

    <div class="borne-voile" data-pin hidden role="dialog" aria-modal="true" aria-labelledby="pin-titre">
        <div class="borne-pin">
            <h2 id="pin-titre">Bonjour <span data-pin-nom></span></h2>
            <p>Saisissez votre code PIN</p>
            <div class="borne-pin-points" data-pin-points aria-live="polite"><i></i><i></i><i></i><i></i></div>
            <div class="borne-clavier">
                <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $chiffre): ?>
                    <button type="button" data-touche="<?= $chiffre ?>"><?= $chiffre ?></button>
                <?php endforeach ?>
                <button type="button" data-touche="annuler" aria-label="Annuler"><?= icone('x') ?></button>
                <button type="button" data-touche="0">0</button>
                <button type="button" data-touche="effacer" aria-label="Effacer"><?= icone('back') ?></button>
            </div>
        </div>
    </div>

    <div class="borne-voile borne-resultat" data-resultat hidden role="status" aria-live="assertive">
        <span class="rond" data-resultat-icone></span>
        <h2 data-resultat-titre></h2>
        <p class="borne-resultat-heure tnum" data-resultat-heure></p>
        <p data-resultat-message></p>
    </div>

    <template data-icone-ok><?= icone('check', 'icon-xl') ?></template>
    <template data-icone-erreur><?= icone('x', 'icon-xl') ?></template>
    <template data-icone-attente><?= icone('sync', 'icon-xl') ?></template>
<?php endif ?>
</body>
</html>
