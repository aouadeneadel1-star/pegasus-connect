<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Paramètres de fonctionnement (annexe A du cahier des charges). Chaque paramètre a un type
 * et des bornes : une valeur hors bornes est refusée plutôt que tronquée.
 */
final class ParametreController extends Controller
{
    /** clé => [libellé, type, min, max, groupe] */
    private const DEFINITIONS = [
        'pointage.rayon_defaut_m' => ['Rayon par défaut autour d\'un site (m)', 'int', 20, 2000, 'Pointage'],
        'pointage.precision_max_m' => ['Précision GPS maximale acceptée (m)', 'int', 10, 1000, 'Pointage'],
        'pointage.tolerance_retard_min' => ['Tolérance de retard (min)', 'int', 0, 60, 'Pointage'],
        'pointage.tolerance_depart_min' => ['Tolérance de départ anticipé (min)', 'int', 0, 60, 'Pointage'],
        'pointage.delai_absence_min' => ['Alerte d\'absence après (min)', 'int', 5, 240, 'Pointage'],
        'pointage.depart_manquant_apres_fin_h' => ['Départ manquant : délai après la fin de vacation (h)', 'int', 1, 12, 'Pointage'],
        'pointage.depart_manquant_sans_vacation_h' => ['Départ manquant sans vacation (h)', 'int', 6, 24, 'Pointage'],
        'pointage.doublon_min' => ['Scan ignoré comme doublon pendant (min)', 'int', 1, 30, 'Pointage'],
        'pointage.annulation_min' => ['Délai d\'annulation par l\'agent (min)', 'int', 0, 10, 'Pointage'],
        'pointage.vitesse_max_kmh' => ['Vitesse de déplacement jugée incohérente (km/h)', 'int', 50, 1000, 'Pointage'],
        'pointage.alerte_carte_pro_expiree' => ['Alerter si un agent pointe avec une carte pro non valide', 'bool', null, null, 'Pointage'],
        'pointage.bloquer_carte_pro_expiree' => ['Refuser le pointage si la carte pro n\'est pas valide', 'bool', null, null, 'Pointage'],
        'hors_ligne.retard_max_h' => ['Synchronisation tardive au-delà de (h)', 'int', 1, 72, 'Pointage'],
        'rondes.rayon_point_m' => ['Rayon par défaut autour d\'un point (m)', 'int', 5, 500, 'Rondes'],
        'rondes.temps_min_entre_points_s' => ['Temps minimal entre deux points (s)', 'int', 0, 600, 'Rondes'],
        'rondes.scan_groupe_points' => ['Scan groupé : nombre de points', 'int', 2, 20, 'Rondes'],
        'rondes.scan_groupe_secondes' => ['Scan groupé : en moins de (s)', 'int', 5, 300, 'Rondes'],
        'rondes.exiger_service_ouvert' => ['Exiger un service ouvert pour démarrer une ronde', 'bool', null, null, 'Rondes'],
        'borne.alerte_silence_min' => ['Alerte borne hors ligne après (min)', 'int', 10, 1440, 'Bornes et badges'],
        'badge.pin_essais_max' => ['Codes PIN erronés avant verrouillage', 'int', 3, 10, 'Bornes et badges'],
        'badge.verrouillage_min' => ['Durée du verrouillage du badge (min)', 'int', 1, 1440, 'Bornes et badges'],
        'badge.hors_affectation' => ['Badge présenté hors affectation', 'choix', ['accepter_a_verifier' => 'Accepter, à vérifier', 'refuser' => 'Refuser'], null, 'Bornes et badges'],
        'badge.hors_ligne_conservation_h' => ['Conservation des badgeages hors ligne (h)', 'int', 1, 72, 'Bornes et badges'],
        'badge.confirmation_affichage_s' => ['Durée d\'affichage de la confirmation (s)', 'int', 2, 15, 'Bornes et badges'],
        'heures.reference_hebdo_h' => ['Durée hebdomadaire de référence (h)', 'int', 20, 48, 'Heures et planning'],
        'planning.repos_quotidien_min_h' => ['Repos quotidien minimal (h)', 'int', 9, 24, 'Heures et planning'],
        'planning.max_jour_h' => ['Durée maximale de travail par jour (h)', 'int', 6, 24, 'Heures et planning'],
        'planning.max_semaine_h' => ['Durée maximale par semaine (h)', 'int', 35, 60, 'Heures et planning'],
        'planning.publication_par_chef' => ['Les chefs d\'équipe peuvent publier le planning', 'bool', null, null, 'Heures et planning'],
        'corrections.validation_directe_chef' => ['Les chefs d\'équipe appliquent leurs corrections sans validation RH', 'bool', null, null, 'Heures et planning'],
        'geoloc.retention_jours' => ['Conservation des coordonnées GPS (jours)', 'int', 7, 365, 'Données personnelles'],
        'rgpd.version_notice' => ['Version de la notice d\'information (la changer redemande l\'acceptation)', 'texte', 2, 20, 'Données personnelles'],
        'societe.raison_sociale' => ['Raison sociale', 'texte', 2, 150, 'Société'],
        'contact.telephone_permanence' => ['Téléphone de la permanence', 'texte', 0, 20, 'Société'],
        'contact.email_rh' => ['E-mail du service RH', 'email', 0, 190, 'Société'],
        'contact.email_paie' => ['E-mail du service paie', 'email', 0, 190, 'Société'],
    ];

    public function index(Request $request): Response
    {
        $valeurs = $this->factory->parametres()->tous();
        $groupes = [];
        foreach (self::DEFINITIONS as $cle => [$libelle, $type, $min, $max, $groupe]) {
            $groupes[$groupe][] = ['cle' => $cle, 'libelle' => $libelle, 'type' => $type, 'min' => $min, 'max' => $max, 'valeur' => $valeurs[$cle] ?? null];
        }

        return $this->vue('admin/parametres', ['groupes' => $groupes]);
    }

    public function enregistrer(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $saisie = $request->raw('parametres');
        $saisie = is_array($saisie) ? $saisie : [];
        $actuels = $this->factory->parametres()->tous();
        $erreurs = [];
        $modifies = [];

        foreach (self::DEFINITIONS as $cle => [$libelle, $type, $min, $max]) {
            $brut = $saisie[$cle] ?? null;
            $valeur = match ($type) {
                'bool' => $brut === '1',
                'int' => is_numeric($brut) && (int) $brut >= $min && (int) $brut <= $max ? (int) $brut : null,
                'choix' => is_string($brut) && array_key_exists($brut, $min) ? $brut : null,
                'email' => is_string($brut) && ($brut === '' || filter_var($brut, FILTER_VALIDATE_EMAIL) !== false) ? trim($brut) : null,
                default => is_string($brut) && mb_strlen(trim($brut)) >= $min && mb_strlen(trim($brut)) <= $max ? trim($brut) : null,
            };
            if ($valeur === null) {
                $erreurs[] = $libelle;
                continue;
            }
            if (($actuels[$cle] ?? null) !== $valeur) {
                $this->factory->parametres()->definir($cle, $valeur, $utilisateur->id);
                $modifies[$cle] = $valeur;
            }
        }

        if ($modifies !== []) {
            $this->factory->audit()->journaliser('parametres.modifier', null, null, ['parametres' => array_keys($modifies)]);
        }
        if ($erreurs !== []) {
            return $this->redirigerAvecErreur('/admin/parametres', 'Valeurs refusées (hors limites) : ' . implode(', ', $erreurs) . '.');
        }

        return $this->rediriger('/admin/parametres', $modifies === [] ? 'Aucun changement.' : sprintf('%d paramètre(s) modifié(s).', count($modifies)));
    }
}
