<?php

declare(strict_types=1);

// Usage : php bin/generer-cle.php
// Affiche une clé maître à copier dans APP_KEY. Ne jamais remplacer la clé d'une
// installation en service : les NIR chiffrés et les QR imprimés deviendraient invalides.

fwrite(STDOUT, 'APP_KEY=' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) . PHP_EOL);
