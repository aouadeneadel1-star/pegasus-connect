# Pegasus Connect

Plateforme employés de Pegasus Insure : pointage par QR code et badge NFC, rondes, dossiers agents, planning, paie, rapports et portail client.

La spécification de référence est dans `docs/` (cahier des charges v1.1 et maquettes).

## Stack

- PHP 8.3, MVC sans framework, PSR-4 (`App\` → `app/`)
- MariaDB, PDO avec requêtes préparées, dates stockées en UTC
- HTML, CSS et JavaScript sans framework ni étape de build
- Hébergement : mutualisé o2switch (Apache, PHP-FPM, cron cPanel)

Dépendances Composer : Dompdf (PDF), endroid/qr-code (étiquettes QR), PHPMailer (SMTP), Google2FA (double authentification). PHPUnit en développement.

## Installation locale

Prérequis :

- PHP 8.3 avec les extensions `pdo_mysql`, `sodium`, `gd`, `mbstring`, `fileinfo`, `zip`, `intl` ;
- MariaDB 10.11 (même version que l'hébergement, voir `bin/verifier-environnement.php`) et `mysqldump` ;
- Composer 2.

Créer deux bases, `pegasus` et `pegasus_test`, et un utilisateur ayant tous les droits sur elles :

```sql
CREATE DATABASE pegasus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pegasus_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pegasus'@'localhost' IDENTIFIED BY '<mot de passe>';
GRANT ALL PRIVILEGES ON pegasus.* TO 'pegasus'@'localhost';
GRANT ALL PRIVILEGES ON pegasus_test.* TO 'pegasus'@'localhost';
```

Puis, depuis la racine du projet :

```bash
composer install
cp .env.example .env                  # APP_ENV=local, APP_DEBUG=1, APP_URL=http://localhost:8000, accès à la base
cp .env.testing.example .env.testing  # même chose pour pegasus_test
php bin/generer-cle.php               # copier la ligne APP_KEY dans .env et .env.testing
php bin/migrate.php
php bin/seed.php
php bin/creer-admin.php vous@pegasus-insure.fr "Prénom Nom"   # affiche le lien d'activation
php bin/verifier-environnement.php
php -S localhost:8000 -t public public/index.php
```

L'application est alors sur http://localhost:8000. Sans serveur SMTP configuré, les e-mails restent en file et `emails:envoyer` les signale en échec : utiliser un serveur SMTP de test (Mailpit, MailHog) ou le lien affiché par `bin/creer-admin.php`.

La caméra (scan des QR) et l'installation de la PWA exigent HTTPS, sauf sur `localhost`. Pour tester depuis un téléphone, passer par un tunnel HTTPS ou par le serveur de recette.

## Tests

```bash
php bin/migrate.php --env=testing --fresh && php bin/seed.php --env=testing
vendor/bin/phpunit                    # ou : composer test
vendor/bin/phpunit --testsuite Unit   # tests unitaires seuls, sans base
```

Les tests d'intégration vident les tables qu'ils utilisent : ils refusent de tourner si `DB_NAME` ne se termine pas par `_test` dans `.env.testing`.

## Installation sur o2switch

1. **cPanel › Sélecteur de version PHP** : choisir PHP 8.3 et activer `pdo_mysql`, `sodium`, `gd`, `mbstring`, `fileinfo`, `zip`, `intl`.
2. **cPanel › Bases de données MySQL** : créer la base et son utilisateur, avec tous les privilèges sur cette base.
3. **cPanel › Domaines** : créer le sous-domaine (ex. `app.pegasus-insure.fr`) avec la racine `pegasus/public`, ou, s'il existe déjà, cliquer sur **Gérer** pour modifier sa racine. Activer le certificat SSL (AutoSSL). Si la racine reste sur le dossier du projet, le `.htaccess` racine renvoie tout vers `public/` : l'application fonctionne et le reste du projet n'est pas servi.
4. Déposer le projet dans `~/pegasus` (SSH, SFTP ou gestionnaire de fichiers), sans `.env` ni `vendor/`, puis en SSH :

   ```bash
   cd ~/pegasus
   composer install --no-dev --optimize-autoloader
   cp .env.example .env            # puis compléter : base, SMTP, APP_URL en https
   php bin/generer-cle.php         # uniquement à la première installation
   php bin/installer-user-ini.php
   php bin/migrate.php
   php bin/seed.php
   php bin/creer-admin.php direction@pegasus-insure.fr "Prénom Nom"
   php bin/verifier-environnement.php
   ```

   La version de PHP en ligne de commande peut différer de celle du site : `php -v` doit afficher la même version que celle choisie dans cPanel. Sinon, appeler le binaire complet (ex. `/usr/local/bin/php83` selon le serveur) dans les commandes et dans le cron.

5. Vérifier que `https://<sous-domaine>/.env`, `/.user.ini` et `/composer.json` ne renvoient jamais leur contenu (page introuvable ou erreur 403). Le reste du projet ne doit jamais être accessible.

`APP_KEY` ne doit plus jamais changer une fois l'application en service : elle protège les numéros de sécurité sociale chiffrés, signe les QR codes imprimés et chiffre les sauvegardes. La conserver dans le coffre-fort de mots de passe de la direction.

### Mises à jour

```bash
cd ~/pegasus && bash bin/deploy.sh
```

Le script passe le site en maintenance (les téléphones gardent leurs pointages en file), récupère le code si le projet est un dépôt git, installe les dépendances, applique les migrations, change la version du Service Worker pour que les téléphones rechargent l'application, puis relance les contrôles d'environnement. Si les fichiers sont déposés à la main, les copier avant de lancer le script.

## Tâches planifiées

À créer dans **cPanel › Tâches Cron** :

```
*/5 * * * *  php ~/pegasus/bin/cron.php rondes:generer        >> ~/pegasus/storage/logs/cron.log 2>&1
*/5 * * * *  php ~/pegasus/bin/cron.php rondes:surveiller     >> ~/pegasus/storage/logs/cron.log 2>&1
*/5 * * * *  php ~/pegasus/bin/cron.php pointages:surveiller  >> ~/pegasus/storage/logs/cron.log 2>&1
*/5 * * * *  php ~/pegasus/bin/cron.php emails:envoyer        >> ~/pegasus/storage/logs/cron.log 2>&1
0   2 * * *  php ~/pegasus/bin/cron.php alertes:echeances     >> ~/pegasus/storage/logs/cron.log 2>&1
15  2 * * *  php ~/pegasus/bin/cron.php rgpd:purger           >> ~/pegasus/storage/logs/cron.log 2>&1
30  2 * * *  php ~/pegasus/bin/cron.php audit:verifier        >> ~/pegasus/storage/logs/cron.log 2>&1
0   3 * * *  php ~/pegasus/bin/cron.php sauvegarde:base       >> ~/pegasus/storage/logs/cron.log 2>&1
0   7 * * *  php ~/pegasus/bin/cron.php recap:quotidien       >> ~/pegasus/storage/logs/cron.log 2>&1
0   4 * * 0  php ~/pegasus/bin/cron.php nettoyage:tmp         >> ~/pegasus/storage/logs/cron.log 2>&1
30  4 * * 0  php ~/pegasus/bin/cron.php calendrier:feries     >> ~/pegasus/storage/logs/cron.log 2>&1
```

| Tâche | Rôle |
|---|---|
| `rondes:generer` | Crée les rondes à venir sur 24 h glissantes à partir des modèles |
| `rondes:surveiller` | Rondes non démarrées ou en retard, clôture automatique, alertes |
| `pointages:surveiller` | Absences, départs manquants, bornes muettes |
| `emails:envoyer` | Envoi de la file d'e-mails par lots de 50, avec reprise |
| `alertes:echeances` | Carte professionnelle, SST, diplômes : alertes à J-90, J-30, J-7 et J0 |
| `rgpd:purger` | Durées de conservation (géolocalisation, journaux, jetons, notifications) |
| `audit:verifier` | Contrôle de la chaîne d'intégrité du journal d'audit |
| `sauvegarde:base` | `mysqldump` compressé et chiffré dans `storage/backups`, conservé 14 jours |
| `recap:quotidien` | Récapitulatif de la veille pour les chefs d'équipe et les administrateurs |
| `nettoyage:tmp` | Fichiers temporaires, sessions et jetons expirés |
| `calendrier:feries` | Ajoute les jours fériés jusqu'à deux ans d'avance |

Une tâche déjà en cours n'est pas relancée (verrou dans `storage/locks`).

## Sauvegarde et restauration

La sauvegarde quotidienne complète les sauvegardes JetBackup d'o2switch. Le fichier produit est chiffré avec une clé dérivée d'`APP_KEY` : il est inexploitable sans elle. Copier chaque semaine le dossier `storage/backups` hors du serveur (stockage UE).

Restauration dans une base vide :

```bash
php bin/restaurer-sauvegarde.php storage/backups/<fichier>.sql.gz.chiffre > restauration.sql
mysql -u <utilisateur> -p <base> < restauration.sql
rm restauration.sql
```

Si `mysqldump` n'est pas dans le `PATH` du cron, renseigner `MYSQLDUMP_PATH` dans `.env`. Tester une restauration chaque trimestre sur une base de recette.

## Matériel

- **Borne** : tablette fixe (Android, iPad ou Windows) en mode kiosque, ouverte sur `/borne/<code>` puis enrôlée depuis **Administration › Bornes**.
- **Lecteur NFC USB** 13,56 MHz en émulation clavier, compatible PC/SC et NDEF : le configurer pour envoyer l'UID en **hexadécimal**, suivi de la touche Entrée. Un UID décimal ne correspondrait pas aux badges déjà associés.
- **Cartes** : cartes NFC standard au format CR80, reconnues par leur UID. L'UID n'est jamais stocké en clair (empreinte et 4 derniers caractères). Le passage à des puces anti-clonage (NTAG 424 DNA, DESFire) est prévu en V2 sans changer de lecteur.

## Sécurité

- Les secrets (`.env`, `APP_KEY`, mots de passe SMTP et base) ne sont jamais versionnés ni envoyés par e-mail.
- Le numéro de sécurité sociale n'apparaît dans aucun journal, export, PDF ou e-mail ; son affichage en clair exige la double authentification et est journalisé.
- La politique de sécurité du contenu interdit les scripts et styles en ligne : tout passe par `public/assets/`.
- `app/config/mots-de-passe-courants.txt` contient une liste courte de mots de passe refusés. La remplacer par une liste plus large (plusieurs dizaines de milliers d'entrées, une par ligne) avant la mise en service.

## Commandes utiles

| Commande | Rôle |
|---|---|
| `php bin/verifier-environnement.php` | Contrôle PHP, extensions, MariaDB, fuseau SQL, chiffrement ZIP, mysqldump et droits d'écriture |
| `php bin/migrate.php` | Applique les migrations en attente |
| `php bin/migrate.php --statut` | Liste les migrations passées et en attente |
| `php bin/migrate.php --env=testing --fresh` | Reconstruit la base de test (refusé hors `local` et `testing`) |
| `php bin/seed.php` | Rejoue les données initiales (idempotent) |
| `php bin/creer-admin.php <e-mail> "<nom>"` | Crée un administrateur et affiche son lien d'activation |
| `php bin/installer-user-ini.php` | Génère `public/.user.ini` avec les chemins absolus du serveur |
| `php bin/generer-cle.php` | Produit une clé maître pour `APP_KEY` |
| `php bin/restaurer-sauvegarde.php <fichier>` | Déchiffre une sauvegarde vers la sortie standard |
| `bash bin/deploy.sh` | Met à jour l'application en production |

Toutes les commandes PHP acceptent `--env=testing` pour utiliser `.env.testing`.

## Organisation du code

```
app/Core/          noyau : routeur, requête, réponse, vues, base, session, middlewares
app/Controllers/   Agent/, Admin/, Client/, Api/, Borne
app/Services/      règles métier et transactions
app/Repositories/  requêtes SQL
app/Models/        objets métier typés
app/Views/         gabarits PHP (HTML), e-mails et PDF
app/config/        routes, liste des mots de passe refusés
bin/               scripts d'exploitation et tâches planifiées
database/          migrations et données initiales
docs/              cahier des charges et maquettes
public/            seule racine web : front controller, CSS, JS, images, Service Worker
storage/           fichiers, journaux, sessions, sauvegardes (hors web)
tests/             Unit/ et Integration/
```

Conventions : PSR-12, `declare(strict_types=1)` partout, classes `final` par défaut, domaine métier nommé en français (`Pointage`, `Ronde`, `Vacation`), noyau technique en anglais (`Router`, `Request`). Messages de commit en français : `type(module): description`.

## Évolutions prévues

Reportées par le cahier des charges (§ 13), avec une solution de repli déjà en place :

| Évolution | En attendant |
|---|---|
| Import CSV des agents | Création des fiches une à une |
| Impression des cartes CR80 en PDF | Cartes commandées pré-imprimées, association du badge sur la borne |
| Plan du site en temps réel | Liste des points de contrôle et état de la ronde |
| Rapports en masse et archive ZIP | Génération et envoi rapport par rapport |
| Tableaux de bord détaillés | Indicateurs de l'accueil et exports CSV |
