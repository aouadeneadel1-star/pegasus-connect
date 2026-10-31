<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Clock;
use App\Services\Securite\LimiteDebit;
use DateTimeImmutable;

final class LimiteDebitTest extends BaseDeDonneesTestCase
{
    protected function tables(): array
    {
        return ['limites_debit'];
    }

    public function testBloqueAuDelaDuMaximum(): void
    {
        $limite = new LimiteDebit($this->pdo);

        self::assertTrue($limite->essayer('connexion:192.0.2.1', 3, 60));
        self::assertTrue($limite->essayer('connexion:192.0.2.1', 3, 60));
        self::assertTrue($limite->essayer('connexion:192.0.2.1', 3, 60));
        self::assertFalse($limite->essayer('connexion:192.0.2.1', 3, 60));
        self::assertTrue($limite->estBloque('connexion:192.0.2.1', 3));
        self::assertTrue($limite->essayer('connexion:192.0.2.2', 3, 60), 'les compteurs sont indépendants');
    }

    public function testFenetreExpiree(): void
    {
        $limite = new LimiteDebit($this->pdo);
        Clock::freeze(new DateTimeImmutable('2026-10-28 10:00:00', Clock::utc()));
        $limite->essayer('pin:12', 1, 60);
        self::assertFalse($limite->essayer('pin:12', 1, 60));

        Clock::freeze(new DateTimeImmutable('2026-10-28 10:01:01', Clock::utc()));
        self::assertFalse($limite->estBloque('pin:12', 1));
        self::assertTrue($limite->essayer('pin:12', 1, 60));
    }

    public function testCleNonStockeeEnClair(): void
    {
        (new LimiteDebit($this->pdo))->essayer('connexion:karim@pegasus.fr', 5, 60);

        self::assertSame(hash('sha256', 'connexion:karim@pegasus.fr'), $this->pdo->query('SELECT cle FROM limites_debit')->fetchColumn());
    }

    public function testReinitialisation(): void
    {
        $limite = new LimiteDebit($this->pdo);
        $limite->essayer('pin:3', 1, 60);
        $limite->essayer('pin:3', 1, 60);
        $limite->reinitialiser('pin:3');

        self::assertFalse($limite->estBloque('pin:3', 1));
    }
}
