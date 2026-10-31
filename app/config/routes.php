<?php

declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\Agent;
use App\Controllers\Api;
use App\Controllers\AuthController;
use App\Controllers\BorneController;
use App\Controllers\Client;
use App\Controllers\DemandeController;
use App\Controllers\FichierController;
use App\Controllers\NotificationController;
use App\Core\Router;

return static function (Router $r): void {
    // Authentification ------------------------------------------------------------------------
    $r->group(['middleware' => ['csrf']], static function (Router $r): void {
        $r->get('/connexion', [AuthController::class, 'afficherConnexion']);
        $r->post('/connexion', [AuthController::class, 'connexion'], ['throttle:connexion,30,900']);
        $r->get('/connexion/2fa', [AuthController::class, 'afficherDeuxFacteurs']);
        $r->post('/connexion/2fa', [AuthController::class, 'deuxFacteurs'], ['throttle:2fa,30,900']);
        $r->get('/mot-de-passe/oubli', [AuthController::class, 'afficherOubli']);
        $r->post('/mot-de-passe/oubli', [AuthController::class, 'oubli'], ['throttle:oubli,10,3600']);
        $r->get('/mot-de-passe/reinitialiser/{jeton}', [AuthController::class, 'afficherReinitialisation']);
        $r->post('/mot-de-passe/reinitialiser/{jeton}', [AuthController::class, 'reinitialisation'], ['throttle:reinit,20,3600']);
        $r->get('/invitation/{jeton}', [AuthController::class, 'afficherInvitation']);
        $r->post('/invitation/{jeton}', [AuthController::class, 'invitation'], ['throttle:invitation,20,3600']);
    });

    $r->group(['middleware' => ['auth', 'csrf']], static function (Router $r): void {
        $r->post('/deconnexion', [AuthController::class, 'deconnexion']);
        $r->get('/compte/2fa', [AuthController::class, 'afficherEnrolement']);
        $r->post('/compte/2fa', [AuthController::class, 'enrolement']);
        $r->get('/compte/reauthentifier', [AuthController::class, 'afficherReauthentification']);
        $r->post('/compte/reauthentifier', [AuthController::class, 'reauthentification'], ['throttle:reauth,10,900']);
        $r->get('/compte/mot-de-passe', [AuthController::class, 'afficherMotDePasse']);
        $r->post('/compte/mot-de-passe', [AuthController::class, 'motDePasse']);
        $r->get('/consentements', [AuthController::class, 'afficherConsentements']);
        $r->post('/consentements', [AuthController::class, 'consentements']);
        $r->get('/fichier/{jeton}', [FichierController::class, 'servir']);
    });

    // Borne de pointage : authentifiée par son cookie d'appareil, sans session utilisateur -----
    $r->get('/borne/{code}', [BorneController::class, 'afficher']);
    $r->post('/borne/{code}/enrolement', [BorneController::class, 'enrolement'], ['throttle:borne_enrolement,10,900']);
    $r->group(['middleware' => ['borne']], static function (Router $r): void {
        $r->post('/borne/{code}/battement', [BorneController::class, 'battement']);
        $r->post('/borne/{code}/badge', [BorneController::class, 'badge'], ['throttle:borne_badge,120,60']);
        $r->post('/borne/{code}/badge/sync', [BorneController::class, 'synchroniser'], ['throttle:borne_sync,30,60']);
    });

    // Espace agent (agents et chefs d'équipe) --------------------------------------------------
    $r->group(['middleware' => ['auth', 'consentement', 'csrf', 'role:agent,chef']], static function (Router $r): void {
        $r->get('/', [Agent\AccueilController::class, 'index']);
        $r->get('/plus', [Agent\AccueilController::class, 'plus']);
        $r->get('/hors-ligne', [Agent\AccueilController::class, 'horsLigne']);
        $r->get('/pointer', [Agent\PointageController::class, 'scanner']);
        $r->get('/historique', [Agent\PointageController::class, 'historique']);
        $r->get('/rondes', [Agent\RondeController::class, 'index']);
        $r->get('/rondes/{id}', [Agent\RondeController::class, 'voir']);
        $r->get('/anomalie', [Agent\RondeController::class, 'anomalie']);
        $r->get('/planning', [Agent\PlanningController::class, 'index']);
        $r->get('/profil', [Agent\ProfilController::class, 'index']);
        $r->post('/profil/demande', [Agent\ProfilController::class, 'demande']);
        $r->get('/profil/badge', [Agent\ProfilController::class, 'badge']);
        $r->post('/profil/badge/pin', [Agent\ProfilController::class, 'pin'], ['throttle:pin,10,900']);
        $r->post('/profil/badge/perte', [Agent\ProfilController::class, 'perte']);
        $r->get('/profil/donnees', [Agent\ProfilController::class, 'donnees']);
        $r->post('/profil/donnees/geolocalisation', [Agent\ProfilController::class, 'geolocalisation']);
        $r->get('/documents', [Agent\DocumentController::class, 'index']);
        $r->get('/paie', [Agent\DocumentController::class, 'paie']);
        $r->get('/paie/{id}/telecharger', [Agent\DocumentController::class, 'telechargerPaie']);
        $r->get('/societe/{id}', [Agent\DocumentController::class, 'societe']);
        $r->post('/societe/{id}/accuse', [Agent\DocumentController::class, 'accuser']);
        $r->get('/contact', [DemandeController::class, 'index']);
        $r->get('/contact/nouveau', [DemandeController::class, 'nouveau']);
        $r->post('/contact', [DemandeController::class, 'creer'], ['throttle:contact,20,3600']);
        $r->get('/contact/{id}', [DemandeController::class, 'voir']);
        $r->post('/contact/{id}/message', [DemandeController::class, 'repondre']);
        $r->get('/notifications', [NotificationController::class, 'index']);
        $r->post('/notifications/lues', [NotificationController::class, 'toutLire']);
        $r->get('/notifications/{id}', [NotificationController::class, 'ouvrir']);
    });

    // API JSON de l'application agent -----------------------------------------------------------
    $r->group(['prefix' => '/api/v1', 'middleware' => ['auth', 'consentement', 'csrf', 'role:agent,chef']], static function (Router $r): void {
        $r->get('/etat', [Api\AgentApiController::class, 'etat']);
        $r->post('/pointages', [Api\AgentApiController::class, 'pointer'], ['throttle:pointage,30,60']);
        $r->post('/pointages/{id}/annuler', [Api\AgentApiController::class, 'annuler']);
        $r->post('/rondes', [Api\AgentApiController::class, 'demarrerRonde']);
        $r->get('/rondes/{id}', [Api\AgentApiController::class, 'ronde']);
        $r->post('/rondes/{id}/passages', [Api\AgentApiController::class, 'passage'], ['throttle:passage,120,60']);
        $r->post('/rondes/{id}/cloture', [Api\AgentApiController::class, 'cloturer']);
        $r->post('/anomalies', [Api\AgentApiController::class, 'anomalie'], ['throttle:anomalie,30,600']);
        $r->post('/sync', [Api\AgentApiController::class, 'synchroniser'], ['throttle:sync,30,60']);
        $r->get('/planning', [Api\AgentApiController::class, 'planning']);
        $r->get('/notifications', [Api\AgentApiController::class, 'notifications']);
    });

    // Back-office : chefs d'équipe, RH, administrateurs ------------------------------------------
    $r->group(['prefix' => '/admin', 'middleware' => ['auth', 'csrf', 'role:chef,rh,admin']], static function (Router $r): void {
        $r->get('', [Admin\TableauController::class, 'index']);
        $r->get('/notifications', [NotificationController::class, 'index']);
        $r->post('/notifications/lues', [NotificationController::class, 'toutLire']);
        $r->get('/notifications/{id}', [NotificationController::class, 'ouvrir']);

        // Agents
        $r->get('/agents', [Admin\AgentController::class, 'index'], ['perm:agents.voir']);
        $r->get('/agents/nouveau', [Admin\AgentController::class, 'nouveau'], ['perm:agents.gerer']);
        $r->post('/agents', [Admin\AgentController::class, 'creer'], ['perm:agents.gerer']);
        $r->get('/agents/{id}', [Admin\AgentController::class, 'voir'], ['perm:agents.voir']);
        $r->get('/agents/{id}/modifier', [Admin\AgentController::class, 'editer'], ['perm:agents.gerer']);
        $r->post('/agents/{id}', [Admin\AgentController::class, 'enregistrer'], ['perm:agents.gerer']);
        $r->post('/agents/{id}/statut', [Admin\AgentController::class, 'statut'], ['perm:agents.gerer']);
        $r->post('/agents/{id}/inviter', [Admin\AgentController::class, 'inviter'], ['perm:agents.gerer']);
        $r->post('/agents/{id}/nir', [Admin\AgentController::class, 'nir'], ['perm:agents.nir.reveler', '2fa', 'throttle:nir,20,3600']);
        $r->post('/agents/{id}/documents', [Admin\AgentController::class, 'deposerDocument'], ['perm:agents.documents.gerer']);
        $r->post('/agents/{id}/documents/{document}/supprimer', [Admin\AgentController::class, 'supprimerDocument'], ['perm:agents.documents.gerer']);
        $r->post('/agents/{id}/carte-pro', [Admin\AgentController::class, 'cartePro'], ['perm:agents.gerer']);
        $r->post('/agents/{id}/qualifications', [Admin\AgentController::class, 'ajouterQualification'], ['perm:agents.gerer']);
        $r->post('/agents/{id}/qualifications/{qualification}/supprimer', [Admin\AgentController::class, 'supprimerQualification'], ['perm:agents.gerer']);
        $r->post('/agents/{id}/badge', [Admin\BadgeController::class, 'emettre'], ['perm:badges.gerer']);
        $r->post('/rgpd/{id}/export', [Admin\RgpdController::class, 'exporter'], ['perm:rgpd.exporter', '2fa']);
        $r->get('/rgpd/archive/{jeton}', [Admin\RgpdController::class, 'telecharger'], ['perm:rgpd.exporter', '2fa']);
        $r->get('/echeances', [Admin\EcheanceController::class, 'index'], ['perm:echeances.voir']);
        $r->get('/echeances/export.csv', [Admin\EcheanceController::class, 'exporter'], ['perm:echeances.voir']);
        $r->get('/demandes-profil', [Admin\DemandeProfilController::class, 'index'], ['perm:demandes_profil.traiter']);
        $r->post('/demandes-profil/{id}', [Admin\DemandeProfilController::class, 'traiter'], ['perm:demandes_profil.traiter']);

        // Badges
        $r->get('/badges', [Admin\BadgeController::class, 'index'], ['perm:badges.gerer']);
        $r->post('/badges/{id}/associer', [Admin\BadgeController::class, 'associer'], ['perm:badges.gerer']);
        $r->get('/badges/{id}/etat', [Admin\BadgeController::class, 'etat'], ['perm:badges.gerer']);
        $r->post('/badges/{id}/pin', [Admin\BadgeController::class, 'reinitialiserPin'], ['perm:badges.gerer']);
        $r->post('/badges/{id}/statut', [Admin\BadgeController::class, 'statut'], ['perm:badges.gerer,badges.declarer_perte']);

        // Sites, bornes, points de contrôle, modèles de ronde
        $r->get('/sites', [Admin\SiteController::class, 'index'], ['perm:sites.voir']);
        $r->get('/sites/nouveau', [Admin\SiteController::class, 'nouveau'], ['perm:sites.gerer']);
        $r->post('/sites', [Admin\SiteController::class, 'creer'], ['perm:sites.gerer']);
        $r->get('/sites/{id}', [Admin\SiteController::class, 'voir'], ['perm:sites.voir']);
        $r->get('/sites/{id}/modifier', [Admin\SiteController::class, 'editer'], ['perm:sites.gerer']);
        $r->post('/sites/{id}', [Admin\SiteController::class, 'enregistrer'], ['perm:sites.gerer']);
        $r->post('/sites/{id}/responsables', [Admin\SiteController::class, 'responsables'], ['perm:sites.gerer']);
        $r->get('/sites/{id}/qr-statique.pdf', [Admin\SiteController::class, 'qrStatique'], ['perm:sites.gerer']);
        $r->post('/sites/{id}/qr-statique/regenerer', [Admin\SiteController::class, 'regenererQrStatique'], ['perm:sites.gerer']);
        $r->post('/sites/{id}/bornes', [Admin\SiteController::class, 'creerBorne'], ['perm:sites.gerer']);
        $r->post('/bornes/{id}/code', [Admin\SiteController::class, 'codeBorne'], ['perm:sites.gerer']);
        $r->post('/bornes/{id}/modifier', [Admin\SiteController::class, 'modifierBorne'], ['perm:sites.gerer']);
        $r->post('/bornes/{id}/revoquer', [Admin\SiteController::class, 'revoquerBorne'], ['perm:sites.gerer']);
        $r->post('/sites/{id}/points', [Admin\PointControleController::class, 'creer'], ['perm:sites.gerer']);
        $r->get('/sites/{id}/etiquettes.pdf', [Admin\PointControleController::class, 'etiquettes'], ['perm:sites.gerer']);
        $r->get('/points/{id}', [Admin\PointControleController::class, 'voir'], ['perm:sites.voir']);
        $r->post('/points/{id}', [Admin\PointControleController::class, 'enregistrer'], ['perm:sites.gerer']);
        $r->post('/points/{id}/regenerer', [Admin\PointControleController::class, 'regenerer'], ['perm:sites.gerer']);
        $r->post('/points/{id}/actif', [Admin\PointControleController::class, 'basculer'], ['perm:sites.gerer']);
        $r->get('/sites/{id}/modeles-ronde/nouveau', [Admin\ModeleRondeController::class, 'nouveau'], ['perm:sites.gerer']);
        $r->post('/sites/{id}/modeles-ronde', [Admin\ModeleRondeController::class, 'creer'], ['perm:sites.gerer']);
        $r->get('/modeles-ronde/{id}', [Admin\ModeleRondeController::class, 'editer'], ['perm:sites.gerer']);
        $r->post('/modeles-ronde/{id}', [Admin\ModeleRondeController::class, 'enregistrer'], ['perm:sites.gerer']);

        // Clients
        $r->get('/clients', [Admin\ClientController::class, 'index'], ['perm:clients.gerer']);
        $r->get('/clients/nouveau', [Admin\ClientController::class, 'nouveau'], ['perm:clients.gerer']);
        $r->post('/clients', [Admin\ClientController::class, 'creer'], ['perm:clients.gerer']);
        $r->get('/clients/{id}', [Admin\ClientController::class, 'editer'], ['perm:clients.gerer']);
        $r->post('/clients/{id}', [Admin\ClientController::class, 'enregistrer'], ['perm:clients.gerer']);

        // Planning
        $r->get('/planning', [Admin\PlanningController::class, 'index'], ['perm:planning.voir']);
        $r->post('/planning/vacations', [Admin\PlanningController::class, 'enregistrer'], ['perm:planning.gerer']);
        $r->post('/planning/vacations/{id}/supprimer', [Admin\PlanningController::class, 'supprimer'], ['perm:planning.gerer']);
        $r->post('/planning/dupliquer', [Admin\PlanningController::class, 'dupliquer'], ['perm:planning.gerer']);
        $r->post('/planning/publier', [Admin\PlanningController::class, 'publier'], ['perm:planning.gerer']);

        // Suivi des pointages et corrections
        $r->get('/pointages', [Admin\PointageController::class, 'index'], ['perm:pointages.suivre']);
        $r->get('/pointages/export.csv', [Admin\PointageController::class, 'exporter'], ['perm:pointages.suivre']);
        $r->post('/pointages/{id}/statuer', [Admin\PointageController::class, 'statuer'], ['perm:pointages.corriger.proposer,pointages.corriger.valider']);
        $r->post('/pointages/{id}/heure', [Admin\PointageController::class, 'modifierHeure'], ['perm:pointages.corriger.proposer,pointages.corriger.valider']);
        $r->post('/services/{id}/depart', [Admin\PointageController::class, 'ajouterDepart'], ['perm:pointages.corriger.proposer,pointages.corriger.valider']);
        $r->post('/services', [Admin\PointageController::class, 'ajouterService'], ['perm:pointages.corriger.proposer,pointages.corriger.valider']);
        $r->post('/corrections/{id}/valider', [Admin\PointageController::class, 'validerCorrection'], ['perm:pointages.corriger.valider']);
        $r->post('/corrections/{id}/refuser', [Admin\PointageController::class, 'refuserCorrection'], ['perm:pointages.corriger.valider']);

        // Rondes et anomalies
        $r->get('/rondes', [Admin\RondeController::class, 'index'], ['perm:rondes.suivre']);
        $r->get('/rondes/{id}', [Admin\RondeController::class, 'voir'], ['perm:rondes.suivre']);
        $r->get('/rapports-rondes', [Admin\RondeController::class, 'rapport'], ['perm:rapports_rondes.exporter']);
        $r->get('/anomalies', [Admin\AnomalieController::class, 'index'], ['perm:rondes.suivre']);
        $r->get('/anomalies/{id}', [Admin\AnomalieController::class, 'voir'], ['perm:rondes.suivre']);
        $r->post('/anomalies/{id}', [Admin\AnomalieController::class, 'traiter'], ['perm:anomalies.traiter']);

        // Heures, paie, rapports
        $r->get('/heures', [Admin\HeuresController::class, 'index'], ['perm:heures.voir']);
        $r->get('/heures/export-paie.csv', [Admin\HeuresController::class, 'exporter'], ['perm:heures.voir']);
        $r->post('/heures/cloturer', [Admin\HeuresController::class, 'cloturer'], ['perm:heures.gerer']);
        $r->post('/heures/rouvrir', [Admin\HeuresController::class, 'rouvrir'], ['perm:heures.gerer']);
        $r->get('/paie', [Admin\PaieController::class, 'index'], ['perm:paie.gerer']);
        $r->post('/paie/import', [Admin\PaieController::class, 'importer'], ['perm:paie.gerer']);
        $r->get('/paie/import/{id}', [Admin\PaieController::class, 'voir'], ['perm:paie.gerer']);
        $r->post('/paie/import/{id}/associer', [Admin\PaieController::class, 'associer'], ['perm:paie.gerer']);
        $r->post('/paie/import/{id}/publier', [Admin\PaieController::class, 'publier'], ['perm:paie.gerer']);
        $r->post('/paie/import/{id}/annuler', [Admin\PaieController::class, 'annuler'], ['perm:paie.gerer']);
        $r->post('/paie/fiches/{id}/depublier', [Admin\PaieController::class, 'depublier'], ['perm:paie.gerer']);
        $r->get('/rapports', [Admin\RapportController::class, 'index'], ['perm:rapports.voir']);
        $r->post('/rapports', [Admin\RapportController::class, 'generer'], ['perm:rapports.gerer']);
        $r->post('/rapports/{id}/envoyer', [Admin\RapportController::class, 'envoyer'], ['perm:rapports.gerer']);
        $r->get('/rapports/{id}/telecharger', [Admin\RapportController::class, 'telecharger'], ['perm:rapports.voir']);

        // Documents société
        $r->get('/societe', [Admin\SocieteController::class, 'index'], ['perm:societe.gerer']);
        $r->get('/societe/nouveau', [Admin\SocieteController::class, 'nouveau'], ['perm:societe.gerer']);
        $r->post('/societe', [Admin\SocieteController::class, 'creer'], ['perm:societe.gerer']);
        $r->get('/societe/{id}', [Admin\SocieteController::class, 'voir'], ['perm:societe.gerer']);
        $r->post('/societe/{id}', [Admin\SocieteController::class, 'enregistrer'], ['perm:societe.gerer']);
        $r->post('/societe/{id}/version', [Admin\SocieteController::class, 'version'], ['perm:societe.gerer']);
        $r->post('/societe/{id}/publier', [Admin\SocieteController::class, 'publier'], ['perm:societe.gerer']);
        $r->post('/societe/{id}/archiver', [Admin\SocieteController::class, 'archiver'], ['perm:societe.gerer']);
        $r->post('/societe/{id}/relancer', [Admin\SocieteController::class, 'relancer'], ['perm:societe.gerer']);

        // Demandes de contact
        $r->get('/demandes', [Admin\DemandeController::class, 'index'], ['perm:demandes.traiter']);
        $r->get('/demandes/{id}', [Admin\DemandeController::class, 'voir'], ['perm:demandes.traiter']);
        $r->post('/demandes/{id}/message', [Admin\DemandeController::class, 'repondre'], ['perm:demandes.traiter']);
        $r->post('/demandes/{id}/statut', [Admin\DemandeController::class, 'statut'], ['perm:demandes.traiter']);

        // Administration
        $r->get('/utilisateurs', [Admin\UtilisateurController::class, 'index'], ['perm:utilisateurs.gerer']);
        $r->get('/utilisateurs/nouveau', [Admin\UtilisateurController::class, 'nouveau'], ['perm:utilisateurs.gerer']);
        $r->post('/utilisateurs', [Admin\UtilisateurController::class, 'creer'], ['perm:utilisateurs.gerer']);
        $r->get('/utilisateurs/{id}', [Admin\UtilisateurController::class, 'editer'], ['perm:utilisateurs.gerer']);
        $r->post('/utilisateurs/{id}', [Admin\UtilisateurController::class, 'enregistrer'], ['perm:utilisateurs.gerer']);
        $r->post('/utilisateurs/{id}/inviter', [Admin\UtilisateurController::class, 'inviter'], ['perm:utilisateurs.gerer']);
        $r->post('/utilisateurs/{id}/2fa', [Admin\UtilisateurController::class, 'reinitialiserDeuxFacteurs'], ['perm:utilisateurs.gerer', '2fa']);
        $r->get('/roles', [Admin\RoleController::class, 'index'], ['perm:roles.gerer']);
        $r->post('/roles', [Admin\RoleController::class, 'enregistrer'], ['perm:roles.gerer', '2fa']);
        $r->get('/parametres', [Admin\ParametreController::class, 'index'], ['perm:parametres.gerer']);
        $r->post('/parametres', [Admin\ParametreController::class, 'enregistrer'], ['perm:parametres.gerer']);
        $r->get('/majorations', [Admin\MajorationController::class, 'index'], ['perm:majorations.gerer']);
        $r->post('/majorations/regles', [Admin\MajorationController::class, 'ajouterRegle'], ['perm:majorations.gerer']);
        $r->post('/majorations/regles/{id}/desactiver', [Admin\MajorationController::class, 'desactiverRegle'], ['perm:majorations.gerer']);
        $r->post('/majorations/feries', [Admin\MajorationController::class, 'ajouterFerie'], ['perm:majorations.gerer']);
        $r->post('/majorations/feries/supprimer', [Admin\MajorationController::class, 'supprimerFerie'], ['perm:majorations.gerer']);
        $r->get('/audit', [Admin\AuditController::class, 'index'], ['perm:audit.voir']);
        $r->get('/audit/export.csv', [Admin\AuditController::class, 'exporter'], ['perm:audit.voir']);
        $r->post('/audit/verifier', [Admin\AuditController::class, 'verifier'], ['perm:audit.voir']);
    });

    // Portail client --------------------------------------------------------------------------
    $r->group(['prefix' => '/client', 'middleware' => ['auth', 'csrf', 'role:client', 'perm:client.portail']], static function (Router $r): void {
        $r->get('', [Client\PortailController::class, 'index']);
        $r->get('/sites/{id}', [Client\PortailController::class, 'site']);
        $r->get('/rondes', [Client\PortailController::class, 'rondes']);
        $r->get('/rondes/{id}', [Client\PortailController::class, 'ronde']);
        $r->get('/anomalies', [Client\PortailController::class, 'anomalies']);
        $r->get('/anomalies/{id}', [Client\PortailController::class, 'anomalie']);
        $r->get('/rapports', [Client\PortailController::class, 'rapports']);
        $r->get('/contact', [DemandeController::class, 'index']);
        $r->get('/contact/nouveau', [DemandeController::class, 'nouveau']);
        $r->post('/contact', [DemandeController::class, 'creer'], ['throttle:contact,20,3600']);
        $r->get('/contact/{id}', [DemandeController::class, 'voir']);
        $r->post('/contact/{id}/message', [DemandeController::class, 'repondre']);
        $r->get('/notifications', [NotificationController::class, 'index']);
        $r->post('/notifications/lues', [NotificationController::class, 'toutLire']);
        $r->get('/notifications/{id}', [NotificationController::class, 'ouvrir']);
    });
};
