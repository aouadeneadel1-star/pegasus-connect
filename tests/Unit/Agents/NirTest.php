<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use App\Services\Agents\Nir;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NirTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function numeros(): iterable
    {
        yield 'valide' => ['185057800608491', true];
        yield 'valide avec espaces' => ['1 85 05 78 006 084 91', true];
        yield 'valide avec points' => ['1.85.05.78.006.084.91', true];
        yield 'Corse 2B' => ['269022B12345667', true];
        yield 'Corse en minuscules' => ['269022b12345667', true];
        yield 'clé fausse' => ['185057800608492', false];
        yield 'trop court' => ['18505780060849', false];
        yield 'sexe invalide' => ['985057800608491', false];
        yield 'lettre hors Corse' => ['18505C800608491', false];
        yield 'vide' => ['', false];
    }

    #[DataProvider('numeros')]
    public function testValidation(string $nir, bool $attendu): void
    {
        self::assertSame($attendu, Nir::estValide($nir));
    }

    public function testFormatage(): void
    {
        self::assertSame('1 85 05 78 006 084 91', Nir::formater('185057800608491'));
    }

    public function testMasquageNeLaisseQueLaCle(): void
    {
        self::assertStringEndsWith(' 91', Nir::masquer('91'));
        self::assertStringNotContainsString('85', Nir::masquer('91'));
        self::assertSame('Non renseigné', Nir::masquer(null));
    }
}
