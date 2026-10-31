#!/usr/bin/env bash
# Mise à jour de l'application sur le serveur, en SSH depuis la racine du projet :
#   bash bin/deploy.sh
#
# Pendant l'opération, le site répond 503 (fichier storage/maintenance) et l'application
# mobile garde ses pointages dans sa file hors ligne. Le drapeau est retiré même en cas d'échec.

set -euo pipefail

cd "$(dirname "$0")/.."
PHP="${PHP:-php}"

if [[ ! -f .env ]]; then
    echo "Fichier .env absent : installer l'application avant de la mettre à jour." >&2
    exit 1
fi

touch storage/maintenance
trap 'rm -f storage/maintenance' EXIT

if [[ -d .git ]]; then
    # sw.js est estampillé à chaque déploiement : on le rétablit pour que la mise à jour s'applique.
    git checkout -- public/sw.js
    git pull --ff-only
fi

if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
fi

"$PHP" bin/migrate.php
"$PHP" bin/installer-user-ini.php

# Un nouveau nom de cache force les téléphones à recharger l'application.
version="$(date +%Y%m%d%H%M%S)"
if [[ -d .git ]]; then
    version="${version}-$(git rev-parse --short HEAD)"
fi
sed -i "s/^const VERSION = '[^']*';/const VERSION = '${version}';/" public/sw.js

find storage/cache -type f ! -name '.gitkeep' -delete

"$PHP" bin/verifier-environnement.php || echo "Attention : un contrôle de l'environnement a échoué, voir ci-dessus." >&2

echo "Déploiement terminé (version ${version})."
