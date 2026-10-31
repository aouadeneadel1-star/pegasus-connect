<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Clock;
use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de test migrée au préalable (composer test). Chaque test repart de tables vides :
 * les tables listées par la classe fille sont vidées avant le test.
 */
abstract class BaseDeDonneesTestCase extends TestCase
{
    protected PDO $pdo;

    /**
     * @return list<string>
     */
    abstract protected function tables(): array;

    protected function setUp(): void
    {
        $this->pdo = Database::connection();
        foreach ($this->tables() as $table) {
            $this->pdo->exec('DELETE FROM ' . $table);
        }
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
    }
}
