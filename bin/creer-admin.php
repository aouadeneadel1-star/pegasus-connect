<?php

declare(strict_types=1);

// Usage : php bin/creer-admin.php email@domaine.fr "Prénom Nom"
// Crée le premier compte administrateur et affiche son lien d'activation (valable 72 h).
// Le lien est affiché ici car l'envoi d'e-mails n'est pas forcément configuré à l'installation.

use App\Core\Console;
use App\Factory;
use App\Models\Role;

$racine = require __DIR__ . '/bootstrap.php';

$email = mb_strtolower(trim($argv[1] ?? ''));
$nom = trim($argv[2] ?? '');

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $nom === '') {
    Console::error('Usage : php bin/creer-admin.php email@domaine.fr "Prénom Nom"');
    exit(1);
}

$factory = new Factory($racine);
$utilisateurs = $factory->utilisateurs();

if ($utilisateurs->emailUtilise($email)) {
    Console::error('Un compte existe déjà avec cette adresse.');
    exit(1);
}

$id = $utilisateurs->creer(Role::Admin, $email, $nom);
$jeton = $factory->jetons()->creer($id, 'invitation', 72 * 3600);
$factory->audit()->journaliser('utilisateur.creer', 'utilisateur', $id, ['role' => 'admin', 'origine' => 'console']);

Console::ok(sprintf('Compte administrateur créé pour %s.', $email));
Console::line('Lien d\'activation (72 h) : ' . $factory->appUrl() . '/invitation/' . $jeton);
