<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

final class AlerteRepository extends Repository
{
    /**
     * Réserve une alerte : renvoie false si elle a déjà été émise.
     */
    public function reserver(string $type, string $occurrence): bool
    {
        return $this->executer(
            'INSERT IGNORE INTO alertes_envoyees (cle, envoye_le) VALUES (:cle, :le)',
            ['cle' => hash('sha256', $type . ':' . $occurrence), 'le' => Clock::sql(Clock::now())],
        )->rowCount() === 1;
    }

    public function purgerAvant(DateTimeImmutable $limite): int
    {
        return $this->executer('DELETE FROM alertes_envoyees WHERE envoye_le < :limite', ['limite' => Clock::sql($limite)])->rowCount();
    }
}
