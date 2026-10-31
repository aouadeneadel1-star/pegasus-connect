<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use DateTimeImmutable;

final class TentativeConnexionRepository extends Repository
{
    public function enregistrer(string $email, string $ip, bool $succes): void
    {
        $this->inserer('tentatives_connexion', [
            'email_hash' => self::empreinte($email),
            'ip' => (string) inet_pton($ip),
            'succes' => $succes ? 1 : 0,
            'created_at' => Clock::sql(Clock::now()),
        ]);
    }

    public function echecsParEmail(string $email, DateTimeImmutable $depuis): int
    {
        return (int) $this->valeur(
            'SELECT COUNT(*) FROM tentatives_connexion
             WHERE email_hash = :hash AND succes = 0 AND created_at >= :depuis',
            ['hash' => self::empreinte($email), 'depuis' => Clock::sql($depuis)],
        );
    }

    public function echecsParIp(string $ip, DateTimeImmutable $depuis): int
    {
        return (int) $this->valeur(
            'SELECT COUNT(*) FROM tentatives_connexion
             WHERE ip = :ip AND succes = 0 AND created_at >= :depuis',
            ['ip' => (string) inet_pton($ip), 'depuis' => Clock::sql($depuis)],
        );
    }

    public function purgerAvant(DateTimeImmutable $limite): int
    {
        return $this->executer(
            'DELETE FROM tentatives_connexion WHERE created_at < :limite',
            ['limite' => Clock::sql($limite)],
        )->rowCount();
    }

    private static function empreinte(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}
