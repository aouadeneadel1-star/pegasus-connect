CREATE TABLE IF NOT EXISTS jours_feries (
  date      DATE PRIMARY KEY,
  libelle   VARCHAR(60) NOT NULL,
  zone      VARCHAR(20) NOT NULL DEFAULT 'metropole'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS regles_majoration (
  id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(20)  NOT NULL,               -- nuit, dimanche, ferie, hs25, hs50
  libelle      VARCHAR(80)  NOT NULL,
  taux         DECIMAL(5,2) NOT NULL,               -- en %
  plage_debut  TIME         NULL,
  plage_fin    TIME         NULL,
  seuil_heures DECIMAL(5,2) NULL,                   -- pour HS
  cumulable    TINYINT(1)   NOT NULL DEFAULT 1,
  date_effet   DATE         NOT NULL,
  actif        TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_rm_code_effet (code, date_effet)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS periodes_cloturees (
  annee       SMALLINT UNSIGNED NOT NULL,
  mois        TINYINT UNSIGNED  NOT NULL,
  cloturee_par INT UNSIGNED NOT NULL,
  cloturee_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (annee, mois)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rapports_mensuels (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id              INT UNSIGNED NOT NULL,
  annee                 SMALLINT UNSIGNED NOT NULL,
  mois                  TINYINT UNSIGNED  NOT NULL,
  version               TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  minutes_planifiees    INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_pointees      INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_nuit          INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_dimanche      INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_ferie         INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_hs25          INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_hs50          INT UNSIGNED NOT NULL DEFAULT 0,
  nb_retards            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  minutes_retard        INT UNSIGNED NOT NULL DEFAULT 0,
  nb_absences           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_rondes             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_points_controles   INT UNSIGNED NOT NULL DEFAULT 0,
  carte_pro_numero      VARCHAR(40)  NULL,           -- instantané au moment de la génération
  document_id           BIGINT UNSIGNED NULL,
  sha256                CHAR(64)     NULL,
  statut                ENUM('en_file','genere','envoye','erreur') NOT NULL DEFAULT 'en_file',
  lot_id                CHAR(36)     NULL,           -- génération en masse
  genere_le             DATETIME     NULL,
  genere_par            INT UNSIGNED NOT NULL,
  envoye_le             DATETIME     NULL,
  UNIQUE KEY uq_rm (agent_id, annee, mois, version),
  INDEX idx_rm_lot (lot_id, statut),
  CONSTRAINT fk_rmens_agent FOREIGN KEY (agent_id)    REFERENCES agents(id),
  CONSTRAINT fk_rmens_doc   FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS taches_fond (                          -- file des traitements par lots (cron)
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type        VARCHAR(40)  NOT NULL,                -- rapports_masse, zip, export_rgpd…
  parametres  JSON         NOT NULL,
  progression TINYINT UNSIGNED NOT NULL DEFAULT 0,
  statut      ENUM('attente','en_cours','termine','erreur') NOT NULL DEFAULT 'attente',
  resultat_document_id BIGINT UNSIGNED NULL,
  erreur      TEXT         NULL,
  cree_par    INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tf_statut (statut, created_at)
) ENGINE=InnoDB;

-- DEMANDES, NOTIFICATIONS, E-MAILS, PARAMÈTRES, AUDIT
