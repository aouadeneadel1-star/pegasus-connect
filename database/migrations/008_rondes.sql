CREATE TABLE IF NOT EXISTS points_controle (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id             INT UNSIGNED NOT NULL,
  identifiant         VARCHAR(12)  NOT NULL,       -- ex. PC-0142
  nom                 VARCHAR(120) NOT NULL,
  description         VARCHAR(255) NULL,
  zone                VARCHAR(60)  NULL,           -- étage / zone
  ordre               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  photo_document_id   BIGINT UNSIGNED NULL,
  latitude            DECIMAL(9,6) NULL,
  longitude           DECIMAL(9,6) NULL,
  rayon_metres        SMALLINT UNSIGNED NULL,      -- NULL = valeur par défaut (50 m)
  plan_x              DECIMAL(5,2) NULL,           -- % largeur du plan
  plan_y              DECIMAL(5,2) NULL,           -- % hauteur du plan
  qr_version          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  actif               TINYINT(1)   NOT NULL DEFAULT 1,
  desactive_le        DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pc_ident (identifiant),
  INDEX idx_pc_site (site_id, actif, ordre),
  CONSTRAINT fk_pc_site  FOREIGN KEY (site_id) REFERENCES sites(id),
  CONSTRAINT fk_pc_photo FOREIGN KEY (photo_document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rondes_modeles (
  id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id                    INT UNSIGNED NOT NULL,
  nom                        VARCHAR(80)  NOT NULL,
  heure_debut                TIME         NOT NULL,   -- heure locale Europe/Paris
  heure_fin                  TIME         NOT NULL,   -- peut être < heure_debut (nuit)
  frequence_minutes          SMALLINT UNSIGNED NOT NULL,
  jours_semaine              TINYINT UNSIGNED NOT NULL DEFAULT 127,  -- masque bits lun=1 … dim=64
  duree_max_minutes          SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  tolerance_demarrage_min    SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  ordre_impose               TINYINT(1)   NOT NULL DEFAULT 0,
  temps_min_entre_points_s   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  actif                      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rm_site (site_id, actif),
  CONSTRAINT fk_rm_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rondes_modeles_points (
  modele_id    INT UNSIGNED NOT NULL,
  point_id     INT UNSIGNED NOT NULL,
  ordre        SMALLINT UNSIGNED NOT NULL,
  obligatoire  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (modele_id, point_id),
  CONSTRAINT fk_rmp_m FOREIGN KEY (modele_id) REFERENCES rondes_modeles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rmp_p FOREIGN KEY (point_id)  REFERENCES points_controle(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rondes (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id               INT UNSIGNED NOT NULL,
  modele_id             INT UNSIGNED NULL,          -- NULL = ronde libre
  agent_id              INT UNSIGNED NULL,          -- attribuée au démarrage
  prevue_le             DATETIME     NULL,
  demarree_le           DATETIME(3)  NULL,
  cloturee_le           DATETIME(3)  NULL,
  statut                ENUM('a_venir','en_cours','terminee','incomplete','manquee','annulee') NOT NULL DEFAULT 'a_venir',
  nb_points_prevus      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_points_controles   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  points_prevus         JSON         NULL,          -- instantané [{point_id,ordre,obligatoire}]
  commentaire_cloture   VARCHAR(500) NULL,
  cloture_auto          TINYINT(1)   NOT NULL DEFAULT 0,
  uuid_client           CHAR(36)     NULL,
  -- une seule ronde en cours par agent
  agent_en_cours        INT UNSIGNED AS (IF(statut = 'en_cours', agent_id, NULL)) STORED,
  UNIQUE KEY uq_ronde_uuid (uuid_client),
  UNIQUE KEY uq_ronde_une_en_cours (agent_en_cours),
  INDEX idx_ronde_site (site_id, prevue_le),
  INDEX idx_ronde_agent (agent_id, demarree_le),
  INDEX idx_ronde_statut (statut, prevue_le),
  CONSTRAINT fk_ronde_site   FOREIGN KEY (site_id)   REFERENCES sites(id),
  CONSTRAINT fk_ronde_modele FOREIGN KEY (modele_id) REFERENCES rondes_modeles(id),
  CONSTRAINT fk_ronde_agent  FOREIGN KEY (agent_id)  REFERENCES agents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passages_ronde (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ronde_id              BIGINT UNSIGNED NOT NULL,
  point_id              INT UNSIGNED NOT NULL,
  agent_id              INT UNSIGNED NOT NULL,
  horodatage            DATETIME(3)  NOT NULL,
  horodatage_reception  DATETIME(3)  NOT NULL,
  horodatage_appareil   DATETIME(3)  NULL,
  hors_ligne            TINYINT(1)   NOT NULL DEFAULT 0,
  latitude              DECIMAL(9,6) NULL,
  longitude             DECIMAL(9,6) NULL,
  precision_m           SMALLINT UNSIGNED NULL,
  distance_point_m      INT UNSIGNED NULL,
  appareil_id           INT UNSIGNED NULL,
  qr_version            SMALLINT UNSIGNED NOT NULL,
  uuid_client           CHAR(36)     NOT NULL,
  indicateurs           SET('hors_zone','imprecise','ordre','trop_rapide','scan_groupe','sync_tardive') NULL,
  statut                ENUM('valide','a_verifier','rejete') NOT NULL DEFAULT 'valide',
  UNIQUE KEY uq_pass_uuid (uuid_client),
  INDEX idx_pass_point (point_id, horodatage),
  INDEX idx_pass_agent (agent_id, horodatage),
  INDEX idx_pass_ronde (ronde_id, horodatage),
  CONSTRAINT fk_pass_ronde FOREIGN KEY (ronde_id) REFERENCES rondes(id),
  CONSTRAINT fk_pass_point FOREIGN KEY (point_id) REFERENCES points_controle(id),
  CONSTRAINT fk_pass_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anomalies (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id                 INT UNSIGNED NOT NULL,
  ronde_id                BIGINT UNSIGNED NULL,
  passage_id              BIGINT UNSIGNED NULL,
  point_id                INT UNSIGNED NULL,
  agent_id                INT UNSIGNED NOT NULL,
  categorie               ENUM('intrusion','degradation','incendie','technique','acces_ouvert',
                               'eclairage','personne','colis_suspect','autre') NOT NULL,
  gravite                 ENUM('info','mineure','majeure','critique') NOT NULL,
  description             TEXT         NOT NULL,
  visible_client          TINYINT(1)   NOT NULL DEFAULT 1,
  statut                  ENUM('ouverte','en_cours','resolue','close') NOT NULL DEFAULT 'ouverte',
  traitee_par             INT UNSIGNED NULL,
  traitee_le              DATETIME     NULL,
  commentaire_traitement  TEXT         NULL,
  uuid_client             CHAR(36)     NOT NULL,
  created_at              DATETIME(3)  NOT NULL,
  UNIQUE KEY uq_ano_uuid (uuid_client),
  INDEX idx_ano_site (site_id, created_at),
  INDEX idx_ano_statut (statut, gravite),
  INDEX idx_ano_ronde (ronde_id),
  CONSTRAINT fk_ano_site  FOREIGN KEY (site_id)  REFERENCES sites(id),
  CONSTRAINT fk_ano_ronde FOREIGN KEY (ronde_id) REFERENCES rondes(id),
  CONSTRAINT fk_ano_pass  FOREIGN KEY (passage_id) REFERENCES passages_ronde(id),
  CONSTRAINT fk_ano_point FOREIGN KEY (point_id) REFERENCES points_controle(id),
  CONSTRAINT fk_ano_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anomalies_photos (
  anomalie_id  BIGINT UNSIGNED NOT NULL,
  document_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (anomalie_id, document_id),
  CONSTRAINT fk_ap_ano FOREIGN KEY (anomalie_id) REFERENCES anomalies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;
