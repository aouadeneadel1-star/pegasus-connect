<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Clock;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Factory;
use App\Models\UtilisateurConnecte;

abstract class Controller
{
    public function __construct(protected readonly Factory $factory)
    {
    }

    /**
     * @param array<string, mixed> $donnees
     */
    protected function vue(string $gabarit, array $donnees = [], int $statut = 200): Response
    {
        $donnees += [
            'flash' => [
                'succes' => Session::getFlash('succes'),
                'erreur' => Session::getFlash('erreur'),
                'info' => Session::getFlash('info'),
            ],
            'erreurs' => [],
            'ancien' => [],
        ];

        return Response::html($this->factory->view()->render($gabarit, $donnees), $statut);
    }

    /**
     * Réaffiche un formulaire avec ses erreurs et la saisie de l'utilisateur.
     *
     * @param array<string, string> $erreurs
     * @param array<string, mixed> $donnees
     */
    protected function formulaireInvalide(Request $request, string $gabarit, array $erreurs, array $donnees = []): Response
    {
        $ancien = $request->all();
        unset($ancien['_csrf'], $ancien['mot_de_passe'], $ancien['mot_de_passe_confirmation'], $ancien['nir']);

        return $this->vue($gabarit, [...$donnees, 'erreurs' => $erreurs, 'ancien' => $ancien], 422);
    }

    protected function rediriger(string $url, ?string $succes = null): Response
    {
        if ($succes !== null) {
            Session::flash('succes', $succes);
        }

        return Response::redirect($url);
    }

    protected function redirigerAvecErreur(string $url, string $erreur): Response
    {
        Session::flash('erreur', $erreur);

        return Response::redirect($url);
    }

    /**
     * @param array<mixed> $donnees
     */
    protected function json(array $donnees, int $statut = 200): Response
    {
        return Response::json($donnees, $statut);
    }

    protected static function premiereErreur(Validator $validation): string
    {
        $erreurs = $validation->errors();

        return (string) reset($erreurs);
    }

    protected function utilisateur(Request $request): UtilisateurConnecte
    {
        return $request->requireUser();
    }

    /**
     * Espace d'où vient la requête : les pages communes (notifications, contact) prennent
     * le gabarit et les liens de cet espace.
     *
     * @return array{espace: string, prefixe: string}
     */
    protected static function espace(Request $request): array
    {
        return match (true) {
            str_starts_with($request->path(), '/admin') => ['espace' => 'admin', 'prefixe' => '/admin'],
            str_starts_with($request->path(), '/client') => ['espace' => 'client', 'prefixe' => '/client'],
            default => ['espace' => 'agent', 'prefixe' => ''],
        };
    }

    /**
     * Mois demandé au format AAAA-MM, sinon le mois courant (heure locale).
     *
     * @return array{0: int, 1: int}
     */
    protected static function mois(?string $valeur): array
    {
        if ($valeur !== null && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $valeur, $m) === 1 && (int) $m[1] >= 2020 && (int) $m[1] <= 2100) {
            return [(int) $m[1], (int) $m[2]];
        }
        $maintenant = Clock::now()->setTimezone(Clock::local());

        return [(int) $maintenant->format('Y'), (int) $maintenant->format('n')];
    }

    /**
     * Date locale AAAA-MM-JJ valide, sinon la valeur par défaut.
     */
    protected static function date(?string $valeur, string $defaut): string
    {
        if ($valeur !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) === 1) {
            [$a, $m, $j] = array_map('intval', explode('-', $valeur));
            if (checkdate($m, $j, $a)) {
                return $valeur;
            }
        }

        return $defaut;
    }
}
