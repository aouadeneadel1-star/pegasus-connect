<?php

// Ce fichier doit rester lisible par toute version de PHP : sur une version trop ancienne,
// le reste de l'application ne pourrait pas être interprété et le visiteur n'aurait qu'une
// page d'erreur vide. On affiche plutôt ce qu'il faut régler.
if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 600');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Pegasus Connect</title></head><body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 20px;color:#111214;line-height:1.5">'
        . '<h1 style="font-size:1.4rem">Version de PHP trop ancienne</h1>'
        . '<p>Pegasus Connect nécessite PHP 8.2 ou plus récent. Le serveur utilise PHP ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '.</p>'
        . '<p>Dans cPanel, ouvrez <strong>Sélecteur de version PHP</strong>, choisissez <strong>8.3</strong>, puis cliquez sur <strong>Appliquer</strong>. '
        . 'Dans l\'onglet <strong>Extensions</strong>, vérifiez que <em>gd, intl, mbstring, pdo_mysql, sodium, zip, fileinfo</em> et <em>dom</em> sont cochées.</p>'
        . '</body></html>';
    exit;
}

return require dirname(__DIR__) . '/app/web.php';
