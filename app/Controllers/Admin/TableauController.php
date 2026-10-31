<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DemandeModificationRepository;
use App\Repositories\DemandeRepository;

/**
 * Tableau de bord : indicateurs essentiels du jour, limités au périmètre de l'utilisateur.
 * Les tableaux de bord détaillés (graphiques, tendances) sont prévus dans un lot ultérieur.
 */
final class TableauController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $maintenant = Clock::now();
        $debutJour = $maintenant->setTimezone(Clock::local())->setTime(0, 0)->setTimezone(Clock::utc());
        $finJour = $debutJour->modify('+1 day');
        $donnees = ['indicateurs' => [], 'enService' => [], 'aVerifier' => [], 'anomalies' => [], 'echeances' => []];

        if ($utilisateur->peut('pointages.suivre')) {
            $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'pointages.suivre');
            $donnees['enService'] = $this->factory->servicesTravail()->enCours($perimetre);
            $donnees['aVerifier'] = array_slice($this->factory->pointages()->aVerifier($perimetre, 8), 0, 8);
            $donnees['indicateurs'][] = ['valeur' => count($donnees['enService']), 'libelle' => 'Agents en service', 'lien' => '/admin/pointages', 'etat' => ''];
            $nbAVerifier = $this->factory->pointages()->nombreAVerifier($perimetre);
            $donnees['indicateurs'][] = ['valeur' => $nbAVerifier, 'libelle' => 'Pointages à vérifier', 'lien' => '/admin/pointages?statut=a_verifier', 'etat' => $nbAVerifier > 0 ? 'warn' : 'ok'];

            $sites = $perimetre->estGlobal() ? array_column($this->factory->sites()->choix($perimetre), 'id') : $perimetre->siteIds;
            $vacations = $this->factory->vacations()->pourSites($sites, $debutJour, $finJour);
            $sansArrivee = $this->factory->pointages()->vacationsSansArrivee($debutJour, $maintenant->modify('-' . $this->factory->parametres()->int('pointage.delai_absence_min', 30) . ' minutes'));
            $sansArrivee = array_filter($sansArrivee, static fn (array $v): bool => in_array((int) $v['site_id'], $sites, true));
            $donnees['indicateurs'][] = ['valeur' => count(array_filter($vacations, static fn (array $v): bool => $v['statut'] === 'publiee')), 'libelle' => 'Vacations aujourd\'hui', 'lien' => '/admin/planning', 'etat' => ''];
            $donnees['indicateurs'][] = ['valeur' => count($sansArrivee), 'libelle' => 'Arrivées non pointées', 'lien' => '/admin/pointages', 'etat' => $sansArrivee === [] ? 'ok' : 'bad'];
            $donnees['absences'] = array_values($sansArrivee);
        }

        if ($utilisateur->peut('rondes.suivre')) {
            $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'rondes.suivre');
            $rondes = $this->factory->rondes()->lister($perimetre, $debutJour, $finJour, [], 1)['lignes'];
            $manquees = count(array_filter($rondes, static fn (array $r): bool => in_array($r['statut'], ['manquee', 'incomplete'], true)));
            $donnees['indicateurs'][] = ['valeur' => count(array_filter($rondes, static fn (array $r): bool => $r['statut'] === 'terminee')) . ' / ' . count(array_filter($rondes, static fn (array $r): bool => $r['statut'] !== 'annulee')), 'libelle' => 'Rondes terminées aujourd\'hui', 'lien' => '/admin/rondes', 'etat' => $manquees > 0 ? 'warn' : ''];
            $nbAnomalies = $this->factory->anomalies()->nombreATraiter($perimetre);
            $donnees['indicateurs'][] = ['valeur' => $nbAnomalies, 'libelle' => 'Anomalies à traiter', 'lien' => '/admin/anomalies?statut=a_traiter', 'etat' => $nbAnomalies > 0 ? 'warn' : 'ok'];
            $donnees['anomalies'] = $this->factory->anomalies()->lister($perimetre, ['statut' => 'a_traiter'], false, 5);
        }

        if ($utilisateur->peut('echeances.voir')) {
            $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'echeances.voir');
            $echeances = $this->factory->conformite()->echeances($perimetre, $maintenant->setTimezone(Clock::local())->modify('+30 days')->format('Y-m-d'));
            $donnees['echeances'] = array_slice($echeances, 0, 6);
            $donnees['indicateurs'][] = ['valeur' => count($echeances), 'libelle' => 'Échéances sous 30 jours', 'lien' => '/admin/echeances', 'etat' => $echeances === [] ? 'ok' : 'warn'];
        }

        if ($utilisateur->peut('demandes.traiter')) {
            $nombre = $this->factory->repository(DemandeRepository::class)->nombreNouvelles(array_keys(DemandeRepository::CATEGORIES));
            $donnees['indicateurs'][] = ['valeur' => $nombre, 'libelle' => 'Nouvelles demandes', 'lien' => '/admin/demandes', 'etat' => $nombre > 0 ? 'warn' : ''];
        }
        if ($utilisateur->peut('demandes_profil.traiter')) {
            $nombre = $this->factory->repository(DemandeModificationRepository::class)->nombreEnAttente();
            if ($nombre > 0) {
                $donnees['indicateurs'][] = ['valeur' => $nombre, 'libelle' => 'Modifications de profil', 'lien' => '/admin/demandes-profil', 'etat' => 'warn'];
            }
        }
        if ($utilisateur->peut('pointages.corriger.valider')) {
            $nombre = count($this->factory->corrections()->enAttente($this->factory->autorisation()->perimetre($utilisateur, 'pointages.corriger.valider')));
            if ($nombre > 0) {
                $donnees['indicateurs'][] = ['valeur' => $nombre, 'libelle' => 'Corrections à valider', 'lien' => '/admin/pointages?vue=corrections', 'etat' => 'warn'];
            }
        }
        if ($utilisateur->peut('sites.gerer')) {
            $muettes = $this->factory->bornes()->silencieuses($maintenant->modify('-' . $this->factory->parametres()->int('borne.alerte_silence_min', 30) . ' minutes'));
            if ($muettes !== []) {
                $donnees['indicateurs'][] = ['valeur' => count($muettes), 'libelle' => 'Bornes hors ligne', 'lien' => '/admin/sites', 'etat' => 'bad'];
            }
        }

        return $this->vue('admin/tableau', $donnees);
    }
}
