<?php
/**
 * @var string $titre
 * @var list<string> $styles feuilles propres à l'espace (agent.css, admin.css…)
 * @var bool $pwa
 */
$pwa ??= false;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#111214">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titre) ?> · Pegasus Connect</title>
<link rel="icon" href="<?= e(asset('icons/favicon-32.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
<?php if ($pwa): ?>
<link rel="manifest" href="/manifest.webmanifest">
<?php endif ?>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<?php foreach ($styles as $feuille): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . $feuille)) ?>">
<?php endforeach ?>
<script src="<?= e(asset('js/theme.js')) ?>"></script>
