CREATE TABLE IF NOT EXISTS sites (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id             INT UNSIGNED NOT NULL,
  code                  VARCHAR(20)  NOT NULL,          -- slug utilisé dans /borne/{code}
  nom                   VARCHAR(120) NOT NULL,
  adresse               VARCHAR(255) NOT NULL,
  code_postal           VARCHAR(10)  NOT NULL,
  ville                 VARCHAR(80)  NOT NULL,
  latitude              DECIMAL(9,6) NOT NULL,
  longitude             DECIMAL(9,6) NOT NULL,
  rayon_metres          SMALLINT UNSIGNED NOT NULL DEFAULT 150,
  mode_pointage         ENUM('dynamique','statique') NOT NULL DEFAULT 'dynamique',
  badge_nfc_actif       TINYINT(1)   NOT NULL DEFAULT 0,  -- pointage par badge autorisé
  badge_pin_requis      TINYINT(1)   NOT NULL DEFAULT 1,
  qr_statique_version   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  plan_document_id      BIGINT UNSIGNED NULL,
  telephone_poste       VARCHAR(20)  NULL,
  consignes             TEXT         NULL,
  qualifications_requises JSON       NULL,              -- [id type_qualification]
  actif                 TINYINT(1)   NOT NULL DEFAULT 1,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sites_code (code),
  INDEX idx_sites_client (client_id, actif),
  CONSTRAINT fk_sites_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bornes (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id              INT UNSIGNED NOT NULL,
  libelle              VARCHAR(80)  NOT NULL,           -- ex. « Poste PC sécurité »
  appareil_jeton_hash  CHAR(64)     NULL,               -- cookie d'appareil haché
  cle_version          SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- rotation de la clé dérivée
  lecteur_nfc          ENUM('aucun','usb','web_nfc') NOT NULL DEFAULT 'aucun',
  lecteur_nfc_ok       TINYINT(1)   NULL,                -- état remonté par le battement de cœur
  statut               ENUM('en_attente','active','revoquee') NOT NULL DEFAULT 'en_attente',
  derniere_activite    DATETIME     NULL,
  derniere_ip          VARBINARY(16) NULL,
  user_agent           VARCHAR(255) NULL,
  enrolement_code_hash CHAR(64)     NULL,               -- code à usage unique, haché
  enrolement_expire_le DATETIME     NULL,
  enrolee_le           DATETIME     NULL,
  enrolee_par          INT UNSIGNED NULL,
  revoquee_le          DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_borne_jeton (appareil_jeton_hash),
  INDEX idx_bornes_site (site_id, statut),
  CONSTRAINT fk_bornes_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(80)  NOT NULL UNIQUE,
  chef_agent_id INT UNSIGNED NULL,                      -- FK ajoutée après création d'agents
  actif         TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;
