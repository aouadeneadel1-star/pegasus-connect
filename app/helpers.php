<?php

declare(strict_types=1);

use App\Core\Clock;
use App\Core\Csrf;

/**
 * Échappement HTML : toute donnée affichée dans une vue passe par cette fonction.
 */
function e(string|int|float|null $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/**
 * URL d'un fichier de public/assets avec sa date de modification, pour invalider le cache navigateur.
 */
function asset(string $chemin): string
{
    static $racine = null;
    $racine ??= dirname(__DIR__) . '/public/assets/';
    $fichier = $racine . $chemin;

    return '/assets/' . $chemin . (is_file($fichier) ? '?v=' . filemtime($fichier) : '');
}

function icone(string $nom, string $classe = ''): string
{
    return sprintf(
        '<svg class="icon%s" aria-hidden="true" focusable="false"><use href="%s#i-%s"></use></svg>',
        $classe === '' ? '' : ' ' . e($classe),
        e(asset('img/icones.svg')),
        e($nom),
    );
}

/**
 * Date stockée en UTC, affichée en heure de Paris.
 */
function date_locale(DateTimeImmutable|string|null $date): ?DateTimeImmutable
{
    if ($date === null || $date === '') {
        return null;
    }

    $date = is_string($date) ? new DateTimeImmutable($date, Clock::utc()) : $date;

    return $date->setTimezone(Clock::local());
}

/**
 * Dates calendaires (naissance, échéances) : aucune conversion de fuseau.
 */
function date_courte(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }

    $objet = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

    return $objet === false ? '' : $objet->format('d/m/Y');
}

function date_heure(DateTimeImmutable|string|null $date): string
{
    $locale = date_locale($date);

    return $locale === null ? '' : $locale->format('d/m/Y H:i');
}

function heure(DateTimeImmutable|string|null $date): string
{
    $locale = date_locale($date);

    return $locale === null ? '' : $locale->format('H:i');
}

function date_longue(DateTimeImmutable $date): string
{
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    return $jours[(int) $date->format('w')] . ' ' . (int) $date->format('j') . ' ' . nom_mois((int) $date->format('n')) . ' ' . $date->format('Y');
}

function nom_mois(int $mois): string
{
    return ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][$mois] ?? '';
}

function jour_court(DateTimeImmutable $date): string
{
    return ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'][(int) $date->format('w')];
}

/**
 * 599 → « 9 h 59 »
 */
function duree(?int $minutes): string
{
    if ($minutes === null) {
        return '—';
    }

    $signe = $minutes < 0 ? '− ' : '';
    $minutes = abs($minutes);

    return sprintf('%s%d h %02d', $signe, intdiv($minutes, 60), $minutes % 60);
}

/**
 * 8550 minutes → « 142,50 »
 */
function heures_decimales(int $minutes): string
{
    return number_format($minutes / 60, 2, ',', ' ');
}

function nombre(int|float $valeur, int $decimales = 0): string
{
    return number_format($valeur, $decimales, ',', ' ');
}

function taille_fichier(int $octets): string
{
    return $octets >= 1048576 ? nombre($octets / 1048576, 1) . ' Mo' : nombre(max(1, (int) round($octets / 1024))) . ' Ko';
}

function selectionne(bool $condition): string
{
    return $condition ? ' selected' : '';
}

function coche(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/**
 * Valeur à réafficher dans un formulaire : saisie précédente, sinon valeur existante.
 *
 * @param array<string, mixed> $ancien
 * @param array<string, mixed>|null $existant
 */
function valeur(array $ancien, ?array $existant, string $champ, string $defaut = ''): string
{
    if (array_key_exists($champ, $ancien)) {
        return (string) $ancien[$champ];
    }

    return (string) ($existant[$champ] ?? $defaut);
}

/**
 * Message d'erreur d'un champ, relié au champ par aria-describedby.
 *
 * @param array<string, string> $erreurs
 */
function erreur_champ(array $erreurs, string $champ): string
{
    if (!isset($erreurs[$champ])) {
        return '';
    }

    return '<p class="field-error" id="erreur-' . e($champ) . '">' . icone('alert', 'icon-sm') . e($erreurs[$champ]) . '</p>';
}

/**
 * Attributs ARIA d'un champ en erreur.
 *
 * @param array<string, string> $erreurs
 */
function aria_erreur(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? ' aria-invalid="true" aria-describedby="erreur-' . e($champ) . '"' : '';
}

function badge(string $libelle, string $classe = ''): string
{
    return '<span class="badge' . ($classe === '' ? '' : ' ' . e($classe)) . '">' . e($libelle) . '</span>';
}

/**
 * @param array{libelle: string, classe: string} $statut résultat de Echeance::statut()
 */
function badge_echeance(array $statut): string
{
    return badge($statut['libelle'], $statut['classe']);
}

function badge_compte(string $statut): string
{
    return match ($statut) {
        'actif' => badge('Actif', 'badge-success'),
        'invite' => badge('Invité', 'badge-info'),
        'suspendu' => badge('Suspendu', 'badge-warning'),
        'desactive' => badge('Désactivé'),
        default => badge($statut),
    };
}

/**
 * Lien de pagination qui conserve les filtres de la page.
 *
 * @param array<string, scalar|null> $filtres
 */
function url_page(string $chemin, array $filtres, int $page): string
{
    $parametres = array_filter([...$filtres, 'page' => $page], static fn ($valeur): bool => $valeur !== null && $valeur !== '' && $valeur !== 0);

    return $chemin . ($parametres === [] ? '' : '?' . http_build_query($parametres));
}
