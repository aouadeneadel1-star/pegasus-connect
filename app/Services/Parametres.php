<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use PDO;

/**
 * Paramètres métier modifiables par l'administrateur (annexe A du cahier des charges).
 * Les valeurs sont stockées en JSON ; un paramètre absent prend sa valeur par défaut.
 */
final class Parametres
{
    /** @var array<string, mixed>|null */
    private ?array $valeurs = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $cle, mixed $defaut = null): mixed
    {
        $this->charger();

        return array_key_exists($cle, $this->valeurs) ? $this->valeurs[$cle] : $defaut;
    }

    public function int(string $cle, int $defaut): int
    {
        $valeur = $this->get($cle, $defaut);

        return is_numeric($valeur) ? (int) $valeur : $defaut;
    }

    public function bool(string $cle, bool $defaut): bool
    {
        $valeur = $this->get($cle, $defaut);

        return is_bool($valeur) ? $valeur : $defaut;
    }

    /**
     * @return array<string, mixed>
     */
    public function tous(): array
    {
        $this->charger();

        return $this->valeurs;
    }

    public function definir(string $cle, mixed $valeur, ?int $utilisateurId): void
    {
        $this->pdo->prepare(
            'INSERT INTO parametres (cle, valeur, modifie_par, modifie_le) VALUES (:cle, :valeur, :par, :le)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), modifie_par = VALUES(modifie_par), modifie_le = VALUES(modifie_le)'
        )->execute([
            'cle' => $cle,
            'valeur' => json_encode($valeur, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'par' => $utilisateurId,
            'le' => Clock::sql(Clock::now()),
        ]);
        $this->valeurs = null;
    }

    private function charger(): void
    {
        if ($this->valeurs !== null) {
            return;
        }

        $this->valeurs = [];
        foreach ($this->pdo->query('SELECT cle, valeur FROM parametres')->fetchAll(PDO::FETCH_KEY_PAIR) as $cle => $json) {
            $this->valeurs[(string) $cle] = json_decode((string) $json, true);
        }
    }
}
