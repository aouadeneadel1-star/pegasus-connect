<?php
/**
 * Étiquette A5 à afficher au poste d'un site sans borne.
 *
 * @var array<string, mixed> $site
 * @var string $qr data URI SVG
 * @var string $racinePublique
 */
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>QR de pointage</title>
<style>
    @page { size: A5 portrait; margin: 12mm; }
    body { font-family: "DejaVu Sans", sans-serif; text-align: center; color: #111214; }
    .logo { height: 16mm; margin-bottom: 6mm; }
    h1 { font-size: 16pt; margin: 0 0 2mm; }
    .qr { width: 95mm; height: 95mm; margin: 8mm auto; }
    .consigne { font-size: 12pt; font-weight: bold; }
    .muted { color: #5a606b; font-size: 8pt; margin-top: 6mm; }
</style></head>
<body>
    <img class="logo" src="<?= e($racinePublique) ?>/assets/img/logo.png" alt="Pegasus Insure">
    <h1><?= e($site['nom']) ?></h1>
    <p>Pointage de prise et de fin de service</p>
    <img class="qr" src="<?= e($qr) ?>" alt="QR code">
    <p class="consigne">Scannez avec l'application Pegasus</p>
    <p class="muted">Étiquette version <?= e($site['qr_statique_version']) ?>. La géolocalisation est vérifiée au moment du scan. Signalez toute étiquette abîmée à votre chef d'équipe.</p>
</body>
</html>
