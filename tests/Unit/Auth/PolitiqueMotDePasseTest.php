<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Services\Auth\PolitiqueMotDePasse;
use PHPUnit\Framework\TestCase;

final class PolitiqueMotDePasseTest extends TestCase
{
    private string $fichier;

    private PolitiqueMotDePasse $politique;

    protected function setUp(): void
    {
        $this->fichier = (string) tempnam(sys_get_temp_dir(), 'mdp');
        file_put_contents($this->fichier, "azerty\nmotdepasse\nsoleil\n");
        $this->politique = new PolitiqueMotDePasse($this->fichier);
    }

    protected function tearDown(): void
    {
        @unlink($this->fichier);
    }

    public function testPhraseDePasseAcceptee(): void
    {
        self::assertNull($this->politique->erreur('Cheval Batterie Agrafe 42'));
    }

    public function testLongueurMinimale(): void
    {
        self::assertStringContainsString('12 caractères', (string) $this->politique->erreur('Court#2026'));
    }

    public function testLongueurMaximale(): void
    {
        self::assertNotNull($this->politique->erreur(str_repeat('abcdefgh', 17)));
    }

    public function testMotDePasseRepetitif(): void
    {
        self::assertStringContainsString('répétitif', (string) $this->politique->erreur('abababababab'));
    }

    public function testMotDePasseCourantMemeAvecSuffixe(): void
    {
        self::assertStringContainsString('courant', (string) $this->politique->erreur('Azerty2024!!'));
        self::assertStringContainsString('courant', (string) $this->politique->erreur('MotDePasse123'));
    }

    public function testMotDePasseReprenantLIdentite(): void
    {
        $erreur = $this->politique->erreur('Cheval benali Agrafe', ['karim.benali@pegasus.fr', 'Benali', 'Karim']);

        self::assertStringContainsString('nom', (string) $erreur);
    }

    public function testElementsDIdentiteTropCourtsIgnores(): void
    {
        self::assertNull($this->politique->erreur('Cheval Batterie Agrafe 42', ['Li']));
    }

    public function testHachageVerifiable(): void
    {
        $hash = PolitiqueMotDePasse::hacher('Cheval Batterie Agrafe 42');

        self::assertTrue(password_verify('Cheval Batterie Agrafe 42', $hash));
        self::assertFalse(password_verify('cheval batterie agrafe 42', $hash));
        self::assertFalse(PolitiqueMotDePasse::doitRehacher($hash));
    }
}
