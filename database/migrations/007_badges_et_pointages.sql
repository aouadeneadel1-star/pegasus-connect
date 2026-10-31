CREATE TABLE IF NOT EXISTS badges (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero                VARCHAR(20)  NOT NULL,           -- n° imprimé, ex. B-000153
  uid_hash              CHAR(64)     NULL,               -- HMAC-SHA256 de l'UID normalisé
  uid_fin               CHAR(4)      NULL,               -- 4 derniers caractères hex (affichage)
  agent_id              INT UNSIGNED NULL,
  statut                ENUM('stock','actif','perdu','vole','desactive','restitue') NOT NULL DEFAULT 'stock',
  pin_hash              VARCHAR(255) NULL,
  pin_a_changer         TINYINT(1)   NOT NULL DEFAULT 1,
  echecs_pin            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verrouille_jusqu_a    DATETIME     NULL,
  photo_document_id     BIGINT UNSIGNED NULL,
  carte_pro_numero      VARCHAR(40)  NULL,               -- instantané imprimé sur la carte
  emis_le               DATETIME     NULL,
  emis_par              INT UNSIGNED NULL,
  desactive_le          DATETIME     NULL,
  motif_desactivation   VARCHAR(255) NULL,
  derniere_utilisation  DATETIME     NULL,
  association_borne_id  INT UNSIGNED NULL,               -- association en attente sur une borne
  association_expire_le DATETIME     NULL,
  -- un seul badge actif par agent
  agent_actif           INT UNSIGNED AS (IF(statut = 'actif', agent_id, NULL)) STORED,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_badge_numero (numero),
  UNIQUE KEY uq_badge_uid (uid_hash),
  UNIQUE KEY uq_badge_un_actif (agent_actif),
  INDEX idx_badge_agent (agent_id, statut),
  CONSTRAINT fk_badge_agent FOREIGN KEY (agent_id)          REFERENCES agents(id),
  CONSTRAINT fk_badge_photo FOREIGN KEY (photo_document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pointages (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id              INT UNSIGNED NOT NULL,
  site_id               INT UNSIGNED NOT NULL,
  borne_id              INT UNSIGNED NULL,
  vacation_id           BIGINT UNSIGNED NULL,
  type                  ENUM('arrivee','depart') NOT NULL,
  mode                  ENUM('qr_dynamique','qr_statique','badge_nfc','manuel') NOT NULL,
  badge_id              INT UNSIGNED NULL,               -- mode badge_nfc
  horodatage            DATETIME(3)  NOT NULL,      -- horodatage retenu (serveur, ou appareil borné si hors ligne)
  horodatage_reception  DATETIME(3)  NOT NULL,      -- réception serveur
  horodatage_appareil   DATETIME(3)  NULL,
  hors_ligne            TINYINT(1)   NOT NULL DEFAULT 0,
  latitude              DECIMAL(9,6) NULL,
  longitude             DECIMAL(9,6) NULL,
  precision_m           SMALLINT UNSIGNED NULL,
  distance_site_m       INT UNSIGNED NULL,
  geoloc_statut         ENUM('ok','hors_zone','imprecise','refusee','indisponible') NOT NULL,
  appareil_id           INT UNSIGNED NULL,
  uuid_client           CHAR(36)     NOT NULL,
  jeton_fenetre         INT UNSIGNED NULL,
  statut                ENUM('valide','a_verifier','rejete','annule','corrige') NOT NULL DEFAULT 'valide',
  indicateurs           SET('doublon','hors_zone','imprecise','vitesse','appareil_partage',
                            'carte_pro_expiree','sync_tardive','hors_affectation','pin_hors_ligne','hors_planning','retard','depart_anticipe') NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ptg_uuid (uuid_client),
  INDEX idx_ptg_agent (agent_id, horodatage),
  INDEX idx_ptg_site (site_id, horodatage),
  INDEX idx_ptg_statut (statut, horodatage),
  CONSTRAINT fk_ptg_agent FOREIGN KEY (agent_id)    REFERENCES agents(id),
  CONSTRAINT fk_ptg_site  FOREIGN KEY (site_id)     REFERENCES sites(id),
  CONSTRAINT fk_ptg_borne FOREIGN KEY (borne_id)    REFERENCES bornes(id),
  CONSTRAINT fk_ptg_vac   FOREIGN KEY (vacation_id) REFERENCES vacations(id),
  CONSTRAINT fk_ptg_badge FOREIGN KEY (badge_id)    REFERENCES badges(id),
  CONSTRAINT fk_ptg_app   FOREIGN KEY (appareil_id) REFERENCES appareils(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id             INT UNSIGNED NOT NULL,
  site_id              INT UNSIGNED NOT NULL,
  vacation_id          BIGINT UNSIGNED NULL,
  pointage_arrivee_id  BIGINT UNSIGNED NOT NULL,
  pointage_depart_id   BIGINT UNSIGNED NULL,
  debut                DATETIME(3)  NOT NULL,
  fin                  DATETIME(3)  NULL,
  duree_minutes        INT UNSIGNED NULL,
  statut               ENUM('ouvert','clos','depart_manquant','corrige','annule') NOT NULL DEFAULT 'ouvert',
  -- garantit un seul service ouvert par agent (NULL non indexé en unicité)
  agent_ouvert         INT UNSIGNED AS (IF(statut = 'ouvert', agent_id, NULL)) STORED,
  UNIQUE KEY uq_srv_un_ouvert (agent_ouvert),
  INDEX idx_srv_agent (agent_id, debut),
  INDEX idx_srv_site (site_id, debut),
  INDEX idx_srv_statut (statut),
  CONSTRAINT fk_srv_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_srv_site  FOREIGN KEY (site_id)  REFERENCES sites(id),
  CONSTRAINT fk_srv_arr   FOREIGN KEY (pointage_arrivee_id) REFERENCES pointages(id),
  CONSTRAINT fk_srv_dep   FOREIGN KEY (pointage_depart_id)  REFERENCES pointages(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS corrections_pointage (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pointage_id    BIGINT UNSIGNED NULL,
  service_id     BIGINT UNSIGNED NULL,
  action         ENUM('ajout','modification','annulation','validation') NOT NULL,
  avant          JSON         NULL,
  apres          JSON         NULL,
  motif_code     VARCHAR(30)  NOT NULL,            -- oubli, panne_borne, erreur_type…
  motif_texte    VARCHAR(255) NOT NULL,
  statut         ENUM('proposee','appliquee','refusee') NOT NULL,
  demandee_par   INT UNSIGNED NOT NULL,
  validee_par    INT UNSIGNED NULL,
  validee_le     DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_corr_statut (statut, created_at),
  INDEX idx_corr_service (service_id)
) ENGINE=InnoDB;
