<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Audit\AuditLogger;
use App\Services\Audit\VerificationAudit;
use PDO;

final class AuditTest extends BaseDeDonneesTestCase
{
    protected function tables(): array
    {
        return ['audit'];
    }

    /**
     * @return list<int>
     */
    private function journaliser(int $nombre): array
    {
        $journal = new AuditLogger($this->pdo);
        $journal->definirContexte(null, '192.0.2.10', 'Test');
        for ($i = 1; $i <= $nombre; $i++) {
            $journal->journaliser('test.action', 'agent', $i, ['rang' => $i]);
        }

        return array_map('intval', $this->pdo->query('SELECT id FROM audit ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array{lignes: int, rupture: ?int}
     */
    private function verifier(): array
    {
        return (new VerificationAudit($this->pdo))->verifier();
    }

    public function testChaineIntacte(): void
    {
        $this->journaliser(5);

        self::assertSame(['lignes' => 5, 'rupture' => null], $this->verifier());
    }

    public function testLignesChaineesALaPrecedente(): void
    {
        $this->journaliser(2);
        $lignes = $this->pdo->query('SELECT hash_precedent, hash FROM audit ORDER BY id')->fetchAll();

        self::assertSame(AuditLogger::GENESE, $lignes[0]['hash_precedent']);
        self::assertSame($lignes[0]['hash'], $lignes[1]['hash_precedent']);
    }

    public function testModificationDetectee(): void
    {
        $ids = $this->journaliser(4);
        $this->pdo->exec("UPDATE audit SET details = '{\"rang\":99}' WHERE id = " . $ids[2]);

        self::assertSame($ids[2], $this->verifier()['rupture']);
    }

    public function testSuppressionDetectee(): void
    {
        $ids = $this->journaliser(4);
        $this->pdo->exec('DELETE FROM audit WHERE id = ' . $ids[1]);

        self::assertSame($ids[2], $this->verifier()['rupture']);
    }

    public function testPurgeJournaliseeAcceptee(): void
    {
        $ids = $this->journaliser(5);
        $this->pdo->exec(sprintf('DELETE FROM audit WHERE id BETWEEN %d AND %d', $ids[1], $ids[2]));
        (new AuditLogger($this->pdo))->journaliser('audit.purge', null, null, ['plages' => [[$ids[1], $ids[2]]]]);

        self::assertNull($this->verifier()['rupture']);
    }

    public function testPurgeNonCouverteDetectee(): void
    {
        $ids = $this->journaliser(5);
        $this->pdo->exec(sprintf('DELETE FROM audit WHERE id BETWEEN %d AND %d', $ids[1], $ids[2]));
        (new AuditLogger($this->pdo))->journaliser('audit.purge', null, null, ['plages' => [[$ids[1], $ids[1]]]]);

        self::assertSame($ids[3], $this->verifier()['rupture']);
    }
}
