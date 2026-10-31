<?php

declare(strict_types=1);

namespace Tests\Unit\Sauvegarde;

use App\Services\Sauvegarde\SauvegardeBase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SauvegardeBaseTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir() . '/pgs-sauvegarde-' . bin2hex(random_bytes(4));
        mkdir($this->dossier);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dossier . '/*') ?: []);
        rmdir($this->dossier);
    }

    private function sauvegarde(string $cle): SauvegardeBase
    {
        return new SauvegardeBase(['host' => '', 'port' => 3306, 'name' => 'pegasus', 'user' => '', 'password' => ''], $cle, $this->dossier);
    }

    /**
     * @return resource
     */
    private static function flux(string $contenu)
    {
        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        return $flux;
    }

    private function restaurer(SauvegardeBase $sauvegarde, string $fichier): string
    {
        $sortie = fopen('php://temp', 'r+');
        $sauvegarde->dechiffrer($fichier, $sortie);
        rewind($sortie);

        return (string) stream_get_contents($sortie);
    }

    public function testAllerRetourSurPlusieursBlocs(): void
    {
        $sql = '';
        for ($i = 0; $i < 120000; $i++) {
            $sql .= sprintf("INSERT INTO pointages VALUES (%d, '%s');\n", $i, bin2hex(random_bytes(8)));
        }
        $sauvegarde = $this->sauvegarde(str_repeat("\x11", 32));
        $sauvegarde->chiffrer(self::flux($sql), $this->dossier . '/a.sql.gz.chiffre');

        $chiffre = (string) file_get_contents($this->dossier . '/a.sql.gz.chiffre');
        self::assertStringStartsWith('PGSB1', $chiffre);
        self::assertStringNotContainsString('INSERT', $chiffre);
        self::assertGreaterThan(1048576, strlen($chiffre), 'le jeu de test doit produire plusieurs blocs');
        self::assertSame($sql, $this->restaurer($sauvegarde, $this->dossier . '/a.sql.gz.chiffre'));
    }

    public function testMauvaiseCleRefusee(): void
    {
        $this->sauvegarde(str_repeat("\x11", 32))->chiffrer(self::flux('SELECT 1;'), $this->dossier . '/a.sql.gz.chiffre');

        $this->expectException(RuntimeException::class);
        $this->restaurer($this->sauvegarde(str_repeat("\x22", 32)), $this->dossier . '/a.sql.gz.chiffre');
    }

    public function testFichierTronqueDetecte(): void
    {
        $sauvegarde = $this->sauvegarde(str_repeat("\x11", 32));
        $sauvegarde->chiffrer(self::flux(str_repeat('SELECT 1;', 10)), $this->dossier . '/a.sql.gz.chiffre');
        $contenu = (string) file_get_contents($this->dossier . '/a.sql.gz.chiffre');
        file_put_contents($this->dossier . '/a.sql.gz.chiffre', substr($contenu, 0, -10));

        $this->expectException(RuntimeException::class);
        $this->restaurer($sauvegarde, $this->dossier . '/a.sql.gz.chiffre');
    }

    public function testCleDeMauvaiseTailleRefusee(): void
    {
        $this->expectException(RuntimeException::class);

        $this->sauvegarde('courte');
    }
}
