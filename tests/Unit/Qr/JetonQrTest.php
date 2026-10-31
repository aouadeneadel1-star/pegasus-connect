<?php

declare(strict_types=1);

namespace Tests\Unit\Qr;

use App\Services\Crypto\Cles;
use App\Services\Qr\JetonInvalideException;
use App\Services\Qr\JetonQr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JetonQrTest extends TestCase
{
    private JetonQr $jetons;

    protected function setUp(): void
    {
        $this->jetons = new JetonQr(new Cles(base64_encode(str_repeat("\x2a", 32))));
    }

    public function testJetonDynamiqueValideAvecLaBonneVersionDeCle(): void
    {
        $contenu = $this->jetons->dynamique(3, 7, 2, 29_000_000);
        $qr = JetonQr::lire($contenu);

        self::assertStringStartsWith('PGS1.D.3.7.29000000.', $contenu);
        self::assertSame([3, 7, 29_000_000], $qr->valeurs);
        self::assertTrue($this->jetons->signatureDynamiqueValide($qr, 2));
        self::assertFalse($this->jetons->signatureDynamiqueValide($qr, 1), 'une borne réenrôlée invalide les anciens QR');
    }

    public function testJetonModifieEstRefuse(): void
    {
        $contenu = $this->jetons->dynamique(3, 7, 1, 100);
        $falsifie = str_replace('PGS1.D.3.7.100.', 'PGS1.D.3.7.101.', $contenu);

        self::assertFalse($this->jetons->signatureDynamiqueValide(JetonQr::lire($falsifie), 1));
    }

    public function testLesUsagesNeSontPasInterchangeables(): void
    {
        $statique = JetonQr::lire($this->jetons->statique(4, 1));
        $point = JetonQr::lire($this->jetons->pointControle(4, 1));

        self::assertTrue($this->jetons->signatureStatiqueValide($statique));
        self::assertTrue($this->jetons->signaturePointValide($point));
        self::assertFalse($this->jetons->signaturePointValide($statique));
        self::assertFalse($this->jetons->signatureStatiqueValide($point));
        self::assertNotSame($statique->signature, $point->signature);
    }

    public function testUneAutreCleMaitreProduitDesSignaturesDifferentes(): void
    {
        $autre = new JetonQr(new Cles(base64_encode(str_repeat("\x01", 32))));

        self::assertFalse($autre->signatureStatiqueValide(JetonQr::lire($this->jetons->statique(1, 1))));
    }

    public function testFenetreDeSoixanteSecondes(): void
    {
        self::assertSame(0, JetonQr::fenetre(59));
        self::assertSame(1, JetonQr::fenetre(60));
        self::assertSame(1, JetonQr::fenetre(119));
    }

    public function testSignatureCourteEtSansCaracteresSpeciaux(): void
    {
        $signature = JetonQr::lire($this->jetons->statique(1, 1))->signature;

        self::assertSame(22, strlen($signature));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $signature);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contenusInvalides(): iterable
    {
        yield 'autre préfixe' => ['XYZ1.S.1.1.abc'];
        yield 'type inconnu' => ['PGS1.X.1.1.abc'];
        yield 'partie manquante' => ['PGS1.D.1.1.abc'];
        yield 'partie en trop' => ['PGS1.S.1.1.1.abc'];
        yield 'valeur non numérique' => ['PGS1.S.1a.1.abc'];
        yield 'valeur négative' => ['PGS1.R.-1.1.abc'];
        yield 'texte libre' => ['https://exemple.fr'];
    }

    #[DataProvider('contenusInvalides')]
    public function testContenuIllisibleRefuse(string $contenu): void
    {
        $this->expectException(JetonInvalideException::class);

        JetonQr::lire($contenu);
    }
}
