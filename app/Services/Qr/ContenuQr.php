<?php

declare(strict_types=1);

namespace App\Services\Qr;

final class ContenuQr
{
    /**
     * @param string $type D (borne), S (statique) ou R (point de ronde)
     * @param list<int> $valeurs D : site, borne, fenêtre — S : site, version — R : point, version
     * @param string $charge partie signée
     */
    public function __construct(
        public readonly string $type,
        public readonly array $valeurs,
        public readonly string $charge,
        public readonly string $signature,
    ) {
    }
}
