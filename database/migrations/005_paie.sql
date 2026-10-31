CREATE TABLE IF NOT EXISTS imports_paie (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annee           SMALLINT UNSIGNED NOT NULL,
  mois            TINYINT UNSIGNED  NOT NULL,
  nb_fichiers     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_associes     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_erreurs      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  statut          ENUM('analyse','a_valider','publie','annule') NOT NULL DEFAULT 'analyse',
  cree_par        INT UNSIGNED NOT NULL,
  publie_par      INT UNSIGNED NULL,
  publie_le       DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_periode (annee, mois)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS imports_paie_lignes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_id     INT UNSIGNED NOT NULL,
  nom_fichier   VARCHAR(255) NOT NULL,
  document_id   BIGINT UNSIGNED NULL,
  agent_id      INT UNSIGNED NULL,
  methode       ENUM('matricule','nom','manuel') NULL,
  statut        ENUM('associe','ambigu','introuvable','doublon','invalide','deja_publie') NOT NULL,
  message       VARCHAR(255) NULL,
  INDEX idx_ipl_import (import_id, statut),
  CONSTRAINT fk_ipl_import FOREIGN KEY (import_id) REFERENCES imports_paie(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fiches_paie (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id      INT UNSIGNED NOT NULL,
  annee         SMALLINT UNSIGNED NOT NULL,
  mois          TINYINT UNSIGNED  NOT NULL,
  version       TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  document_id   BIGINT UNSIGNED NOT NULL,
  import_id     INT UNSIGNED NULL,
  statut        ENUM('publiee','depubliee','remplacee') NOT NULL DEFAULT 'publiee',
  publiee_le    DATETIME     NOT NULL,
  premiere_consultation DATETIME NULL,
  UNIQUE KEY uq_fp (agent_id, annee, mois, version),
  INDEX idx_fp_agent (agent_id, annee, mois, statut),
  CONSTRAINT fk_fp_agent FOREIGN KEY (agent_id)    REFERENCES agents(id),
  CONSTRAINT fk_fp_doc   FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;
