CREATE TABLE IF NOT EXISTS demandes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  demandeur_id    INT UNSIGNED NOT NULL,
  categorie       ENUM('rh','paie','planning','urgence','client','autre') NOT NULL,
  objet           VARCHAR(150) NOT NULL,
  priorite        ENUM('normale','haute','urgente') NOT NULL DEFAULT 'normale',
  statut          ENUM('nouvelle','en_cours','attente_demandeur','resolue','close') NOT NULL DEFAULT 'nouvelle',
  assignee_a      INT UNSIGNED NULL,
  resolue_le      DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_dem_file (statut, categorie, created_at),
  INDEX idx_dem_demandeur (demandeur_id, created_at),
  CONSTRAINT fk_dem_util FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_messages (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  demande_id      INT UNSIGNED NOT NULL,
  auteur_id       INT UNSIGNED NOT NULL,
  message         TEXT         NOT NULL,
  document_id     BIGINT UNSIGNED NULL,
  interne         TINYINT(1)   NOT NULL DEFAULT 0,   -- note non visible du demandeur
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dm_demande (demande_id, created_at),
  CONSTRAINT fk_dm_dem FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  type            VARCHAR(40)  NOT NULL,
  titre           VARCHAR(150) NOT NULL,
  message         VARCHAR(500) NULL,
  lien            VARCHAR(255) NULL,
  lue_le          DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_util (utilisateur_id, lue_le, created_at),
  CONSTRAINT fk_notif_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS file_emails (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destinataire    VARCHAR(190) NOT NULL,
  sujet           VARCHAR(200) NOT NULL,
  gabarit         VARCHAR(60)  NOT NULL,
  donnees         JSON         NOT NULL,
  statut          ENUM('attente','envoye','echec') NOT NULL DEFAULT 'attente',
  tentatives      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  derniere_erreur VARCHAR(255) NULL,
  envoyer_apres   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  envoye_le       DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fe_file (statut, envoyer_apres)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parametres (
  cle          VARCHAR(80)  PRIMARY KEY,           -- ex. pointage.rayon_defaut_m
  valeur       JSON         NOT NULL,
  modifie_par  INT UNSIGNED NULL,
  modifie_le   DATETIME     NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NULL,
  role_code       VARCHAR(20)  NULL,
  action          VARCHAR(60)  NOT NULL,           -- ex. agent.nir.reveler
  objet_type      VARCHAR(40)  NULL,
  objet_id        BIGINT UNSIGNED NULL,
  details         TEXT         NULL,               -- JSON, jamais de donnée sensible en clair
  ip              VARBINARY(16) NULL,
  user_agent      VARCHAR(255) NULL,
  created_at      DATETIME(3)  NOT NULL,
  hash_precedent  CHAR(64)     NOT NULL,
  hash            CHAR(64)     NOT NULL,           -- SHA-256(hash_precedent | contenu canonique)
  INDEX idx_audit_action (action, created_at),
  INDEX idx_audit_objet (objet_type, objet_id),
  INDEX idx_audit_util (utilisateur_id, created_at)
) ENGINE=InnoDB;
