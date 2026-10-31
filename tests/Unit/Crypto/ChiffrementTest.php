<?php

declare(strict_types=1);

namespace Tests\Unit\Crypto;

use App\Services\Crypto\Chiffrement;
use App\Services\Crypto\Cles;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChiffrementTest extends TestCase
{
    private Cles $cles;

    protected function setUp(): void
    {
        $this->cles = new Cles(base64_encode(str_repeat("\x07", 32)));
    }

    public function testAllerRetour(): void
    {
        $chiffrement = new Chiffrement($this->cles, 'nir');

        self::assertSame('185057800608491', $chiffrement->dechiffrer($chiffrement->chiffrer('185057800608491')));
    }

    public function testDeuxChiffrementsDuMemeTexteDifferent(): void
    {
        $chiffrement = new Chiffrement($this->cles, 'nir');

        self::assertNotSame($chiffrement->chiffrer('secret'), $chiffrement->chiffrer('secret'));
    }

    public function testDonneeAltereeRefusee(): void
    {
        $chiffrement = new Chiffrement($this->cles, 'nir');
        $chiffre = $chiffrement->chiffrer('secret');
        $chiffre[strlen($chiffre) - 1] = chr(ord($chiffre[strlen($chiffre) - 1]) ^ 1);

        $this->expectException(RuntimeException::class);
        $chiffrement->dechiffrer($chiffre);
    }

    public function testUnAutreUsageNePeutPasDechiffrer(): void
    {
        $chiffre = (new Chiffrement($this->cles, 'nir'))->chiffrer('secret');

        $this->expectException(RuntimeException::class);
        (new Chiffrement($this->cles, 'iban'))->dechiffrer($chiffre);
    }

    public function testVersionDeCleInconnueRefusee(): void
    {
        $chiffre = (new Chiffrement($this->cles, 'nir'))->chiffrer('secret');
        $chiffre[0] = chr(9);

        $this->expectException(RuntimeException::class);
        (new Chiffrement($this->cles, 'nir'))->dechiffrer($chiffre);
    }

    public function testIndexAveugleDeterministeEtPropreALUsage(): void
    {
        self::assertSame($this->cles->indexAveugle('badge', '04A1B2C3'), $this->cles->indexAveugle('badge', '04A1B2C3'));
        self::assertNotSame($this->cles->indexAveugle('badge', '04A1B2C3'), $this->cles->indexAveugle('email', '04A1B2C3'));
        self::assertStringNotContainsString('04A1B2C3', $this->cles->indexAveugle('badge', '04A1B2C3'));
    }

    public function testCleMaitreDeMauvaiseTailleRefusee(): void
    {
        $this->expectException(RuntimeException::class);

        new Cles(base64_encode('trop courte'));
    }
}
