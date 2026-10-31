-- Permissions atomiques et droits par défaut de chaque rôle (§ 2.2 du cahier des charges).
-- Les droits d'un rôle ne sont insérés que s'ils manquent : les réglages faits depuis
-- l'écran « Rôles et droits » sont conservés quand le script est rejoué.

INSERT INTO permissions (code, module, libelle, verrouillee) VALUES
('profil.voir', 'terrain', 'Consulter son profil et ses échéances', 0),
('pointage.effectuer', 'terrain', 'Pointer son arrivée et son départ', 0),
('rondes.effectuer', 'terrain', 'Effectuer une ronde', 0),
('anomalies.signaler', 'terrain', 'Signaler une anomalie', 0),
('documents.voir_siens', 'terrain', 'Consulter ses documents personnels', 0),
('paie.voir_siennes', 'terrain', 'Consulter ses fiches de paie', 0),
('planning.voir_sien', 'terrain', 'Consulter son planning', 0),
('societe.lire', 'terrain', 'Lire les documents de la société', 0),
('badge.gerer_sien', 'terrain', 'Changer son PIN et déclarer la perte de son badge', 0),
('demandes.creer', 'contact', 'Envoyer une demande à l''administration', 0),
('agents.voir', 'agents', 'Consulter les fiches agents', 0),
('agents.gerer', 'agents', 'Créer et modifier les fiches agents', 0),
('agents.nir.reveler', 'agents', 'Afficher le numéro de sécurité sociale', 1),
('agents.documents.gerer', 'agents', 'Déposer les documents d''un agent', 0),
('echeances.voir', 'agents', 'Suivre les échéances de conformité', 0),
('demandes_profil.traiter', 'agents', 'Valider les demandes de modification de profil', 0),
('badges.gerer', 'badges', 'Émettre, remplacer et désactiver les badges', 0),
('badges.declarer_perte', 'badges', 'Déclarer la perte d''un badge de son équipe', 0),
('planning.voir', 'planning', 'Consulter le planning', 0),
('planning.gerer', 'planning', 'Créer et publier le planning', 0),
('pointages.suivre', 'pointages', 'Suivre les présences et les pointages', 0),
('pointages.corriger.proposer', 'pointages', 'Proposer une correction de pointage', 0),
('pointages.corriger.valider', 'pointages', 'Valider ou appliquer une correction de pointage', 0),
('rondes.suivre', 'rondes', 'Suivre les rondes et les passages', 0),
('anomalies.traiter', 'rondes', 'Traiter les anomalies', 0),
('sites.voir', 'sites', 'Consulter les sites', 0),
('sites.gerer', 'sites', 'Gérer les sites, bornes et points de contrôle', 0),
('clients.gerer', 'sites', 'Gérer les clients et leurs accès', 0),
('heures.voir', 'heures', 'Consulter les heures et majorations', 0),
('heures.gerer', 'heures', 'Exporter les variables de paie et clôturer un mois', 0),
('paie.gerer', 'paie', 'Importer et publier les fiches de paie', 0),
('societe.gerer', 'documents', 'Publier les documents de la société', 0),
('rapports.voir', 'rapports', 'Consulter les rapports mensuels', 0),
('rapports.gerer', 'rapports', 'Générer et envoyer les rapports mensuels', 0),
('rapports_rondes.exporter', 'rapports', 'Exporter les rapports de rondes', 0),
('demandes.traiter', 'contact', 'Traiter les demandes de contact', 0),
('utilisateurs.gerer', 'administration', 'Gérer les comptes utilisateurs', 0),
('roles.gerer', 'administration', 'Modifier les droits des rôles', 1),
('parametres.gerer', 'administration', 'Modifier les paramètres', 0),
('majorations.gerer', 'administration', 'Modifier les règles de majoration et les jours fériés', 0),
('audit.voir', 'administration', 'Consulter le journal d''audit', 1),
('rgpd.exporter', 'administration', 'Exporter les données personnelles d''un agent', 1),
('client.portail', 'client', 'Accéder au portail client', 0)
ON DUPLICATE KEY UPDATE module = VALUES(module), libelle = VALUES(libelle), verrouillee = VALUES(verrouillee);

INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'profil.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'profil.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'pointage.effectuer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'pointage.effectuer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'rondes.effectuer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'rondes.effectuer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'anomalies.signaler';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'anomalies.signaler';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'documents.voir_siens';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'documents.voir_siens';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'paie.voir_siennes';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'paie.voir_siennes';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'planning.voir_sien';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'planning.voir_sien';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'societe.lire';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'societe.lire';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'badge.gerer_sien';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'badge.gerer_sien';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 1, id, 'soi' FROM permissions WHERE code = 'demandes.creer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'soi' FROM permissions WHERE code = 'demandes.creer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 5, id, 'client' FROM permissions WHERE code = 'demandes.creer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'agents.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'agents.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'agents.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'agents.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'agents.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'agents.nir.reveler';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'agents.nir.reveler';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'agents.documents.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'agents.documents.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'echeances.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'echeances.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'echeances.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'demandes_profil.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'demandes_profil.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'badges.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'badges.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'badges.declarer_perte';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'planning.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'planning.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'planning.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'planning.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'planning.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'planning.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'pointages.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'pointages.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'pointages.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'pointages.corriger.proposer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'pointages.corriger.valider';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'pointages.corriger.valider';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'rondes.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'rondes.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'rondes.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 5, id, 'client' FROM permissions WHERE code = 'rondes.suivre';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'anomalies.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'anomalies.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'sites.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'sites.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'sites.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 5, id, 'client' FROM permissions WHERE code = 'sites.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'sites.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'clients.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'heures.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'heures.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'heures.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'heures.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'heures.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'paie.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'paie.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'societe.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'societe.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'rapports.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'rapports.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'rapports.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'rapports.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'rapports.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'rapports_rondes.exporter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'rapports_rondes.exporter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'rapports_rondes.exporter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 5, id, 'client' FROM permissions WHERE code = 'rapports_rondes.exporter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 2, id, 'equipe' FROM permissions WHERE code = 'demandes.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'demandes.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'demandes.traiter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'utilisateurs.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'roles.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'parametres.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'majorations.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'majorations.gerer';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 3, id, 'tout' FROM permissions WHERE code = 'audit.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'audit.voir';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 4, id, 'tout' FROM permissions WHERE code = 'rgpd.exporter';
INSERT IGNORE INTO role_permissions (role_id, permission_id, portee) SELECT 5, id, 'client' FROM permissions WHERE code = 'client.portail';
